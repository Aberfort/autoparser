<?php
/**
 * ParserService
 * -------------
 *  • URL порожній  → AI-прогнози (2 промпти).
 *  • URL заданий   → RSS / XML-парсинг + AI-рерайт.
 */

namespace ScAutoParser\Parser;

use GuzzleHttp\Client;
use ScAutoParser\AI\PredictionService;
use ScAutoParser\AI\RewriteService;
use ScAutoParser\Core\Logger;
use ScAutoParser\Feed\Feed;
use ScAutoParser\Feed\FeedRepository;
use ScAutoParser\Feed\PostMapRepository;
use ScAutoParser\Publisher\GutenbergPublisher;
use ScAutoParser\Fixtures\FixturesService;
use ScAutoParser\AI\ProviderFactory;
use ScAutoParser\AI\RewriteService as DynamicRewrite;
use ScAutoParser\AI\PredictionService as DynamicPredict;
use Symfony\Component\DomCrawler\Crawler;
use ScAutoParser\Util\UrlCanonicalizer;

class ParserService
{

    /* ---------- дефолтні шаблони ---------- */

    private const DEFAULT_DETAIL_PROMPT = <<<PROMPT
Напиши профессиональный прогноз на матч:
Матч: {{team1}} vs {{team2}}
Время: {{time}}
Турнир: {{league}}
Дата: {{date}}
Структура:
Вступление (до 500 символов, захватывающее)

<h2> Команда №1 — последние матчи, мотивация
<h2> Команда №2 — аналогично
<h2> Личные встречи — краткий анализ
<h2> Ориентировочные составы — ключевые игроки, если известны
<h2> Прогноз сайта — вывод и логичная ставка (например, ТБ 2.5, П1 и т.п.)
Стиль — как у опытного футбольного аналитика. Без воды. Без общих фраз. Только факты, логика и конкретика.
PROMPT;

    /* ---------- DI ---------- */
    public function __construct(
        private FeedRepository $feeds,
        private PostMapRepository $maps,
        private RewriteService $aiRewrite,
        private PredictionService $aiPredict,
        private FixturesService $fixtures,
        private GutenbergPublisher $publisher,
        private Client $http,
        private Logger $log,
    ) {
    }

    /* ================= PUBLIC ================= */

    public function run(?int $feed_id = null): void
    {
        $feeds = $feed_id
            ? [$this->feeds->find($feed_id)]
            : array_filter(
                $this->feeds->all(),
                static fn(Feed $f) => $f->active
            );

        foreach ($feeds as $feed) {
            if ($feed) {
                $this->run_feed($feed);
            }
        }
    }

    /* ================= INTERNAL ================= */

    private function run_feed(Feed $feed): void
    {
        $provider  = ProviderFactory::make($feed->ai_provider ?? 'gemini');
        $rewriter  = new DynamicRewrite($provider);
        $predictor = new DynamicPredict($provider);

        $this->feeds->update_status($feed->id, 'running');
        $this->log->info(
            "🚀 {$feed->name} (ID: {$feed->id}) started",
            ['feed_id' => $feed->id, 'feed_name' => $feed->name]
        );

        /* AI-only feed (url == '') */
        if ($feed->url === '') {
            $this->handle_ai_predictions($feed, $predictor);

            return;
        }

        /* Import only items newer than last_ts,             *
         *   except those whose previous post is deleted.   */
        $cutOff = (int)$feed->last_ts;

        $this->handle_rss($feed, $rewriter, $cutOff);
    }

    /* ---------- AI-прогнози ---------- */
    private function handle_ai_predictions(
        Feed $feed,
        DynamicPredict $predictor
    ): void {
        $rows = $this->fixtures->todayTop($feed->limit);

        if ( ! $rows) {
            $this->feeds->update_status(
                $feed->id,
                'ok',
                __('Матчів немає', 'sc-autoparser')
            );

            return;
        }

        $posted = 0;

        foreach ($rows as $row) {
            $team1    = $row['team1'];
            $team2    = $row['team2'];
            $dateTime = $row['datetime'];          // «20.05.2025 22:00»
            $league   = $row['league'];

            $matchStamp = (new \DateTimeImmutable($dateTime))
                ->format('Ymd');
            $virtualUrl = sprintf(
                'match://%s-%s-%s',
                sanitize_title($team1),
                sanitize_title($team2),
                $matchStamp
            );
            $virtualUrl = UrlCanonicalizer::normalize($virtualUrl);
            if ($this->maps->exists((int)$feed->id, $virtualUrl)) {
                continue;
            }

            $tpl    = $feed->detail_prompt ?: self::DEFAULT_DETAIL_PROMPT;
            $prompt = strtr($tpl, [
                '{{team1}}'    => $team1,
                '{{team2}}'    => $team2,
                '{{time}}'     => date('H:i', strtotime($dateTime)),
                '{{datetime}}' => $dateTime,
                '{{league}}'   => $league,
                '{{date}}'     => wp_date('d.m.Y'),
                '{{teams}}'    => "$team1 vs $team2",
            ]);

            try {
                $html = $predictor->getForecast($prompt, []);
            } catch (\Throwable $e) {
                $this->log->warning(
                    'Skip forecast: ' . $e->getMessage(),
                    ['feed_id' => $feed->id]
                );
                continue;
            }

            $post_id = $this->publisher->publishForecast(
                $feed,
                $team1,
                $team2,
                $dateTime,
                $html,
                "$team1 vs $team2, $dateTime, $league"
            );

            $this->maps->add($feed->id, $virtualUrl, $post_id);

            ++$posted;
            $this->log->info("✅ Forecast #$post_id", ['feed_id' => $feed->id]);
        }

        $msg = $posted
            ? sprintf(
                _n(
                    'Додано %d прогноз',
                    'Додано %d прогнози',
                    $posted,
                    'sc-autoparser'
                ),
                $posted
            )
            : __('Матчів немає', 'sc-autoparser');

        $this->feeds->update_status($feed->id, 'ok', $msg);
    }

    /* ---------- RSS / XML-постинг ---------- */

    private function handle_rss(
        Feed $feed,
        DynamicRewrite $rewriter,
        int $cutOff
    ): void {
        $rows     = $this->discover_urls($feed, $cutOff);   // already limited
        $posted   = 0;
        $maxTsNew = 0;                                    // track newest ts

        foreach ($rows as $row) {
            $url    = UrlCanonicalizer::normalize($row['link']);
            $rssImg = $row['img'];
            $ts     = (int)$row['ts'];

            /* Skip duplicates (alive posts) */
            if ($this->maps->exists((int)$feed->id, $url)) {
                continue;
            }

            try {
                $html = (string)$this->http->get(
                    $url,
                    [
                        'headers' => [
                            'User-Agent' => \ScAutoParser\Core\Helpers::random_ua(
                            ),
                        ],
                        'timeout' => 15,
                    ]
                )->getBody();

                $crawler = new Crawler($html);
                $content = $this->extractBoundedContent(
                    $crawler,
                    $feed->selector,
                    $feed->selector_end
                );

                $titleOriginal = $crawler->filter('title')->text('');
                $teams         = $this->parse_teams_from_title($titleOriginal);

                /* Local prompt */
                $rawPrompt = (string)$feed->prompt;
                [$titlePrompt, $bodyPrompt] =
                    array_pad(explode('---', $rawPrompt, 2), 2, '');

                $titlePrompt = trim($titlePrompt)
                    ?: 'Перепиши цей заголовок унікально, зберігши мову та зміст.';
                $bodyPrompt  = trim($bodyPrompt);

                $title = trim($rewriter->rewrite($titleOriginal, $titlePrompt))
                    ?: $titleOriginal;

                $rewritten = $bodyPrompt
                    ? $rewriter->rewrite($content, $bodyPrompt)
                    : $content;

                /* Thumbnail */
                $thumbId = null;
                if ($feed->thumbnail_mode === 'first') {
                    if ($rssImg) {
                        $thumbId = \ScAutoParser\Core\Helpers::sideload_image(
                            $rssImg,
                            $feed->image_dir
                        );
                    }
                    if ( ! $thumbId) {
                        $thumbId = $this->extract_first_image(
                            $crawler,
                            $feed->image_dir
                        );
                    }
                }

                /* Publish */
                if ($feed->predict_only && $teams && count($teams) === 2) {
                    [$team1, $team2] = array_map(
                        static fn($t) => trim(
                            preg_replace(
                                '/\s+(?:прогноз|анонс|preview|prediction|ставк[аи]).*$/ui',
                                '',
                                $t
                            )
                        ),
                        $teams
                    );

                    $post_id = $this->publisher->publishForecast(
                        $feed,
                        $team1,
                        $team2,
                        $ts ? date('d.m.Y H:i', $ts) : wp_date('d.m.Y H:i'),
                        $rewritten,
                        $title
                    );
                } else {
                    $post_id = $this->publisher->publish(
                        $feed,
                        $title ?: $feed->name,
                        $rewritten
                    );
                }

                if ($thumbId) {
                    set_post_thumbnail($post_id, $thumbId);
                }

                /* Map + log */
                $this->maps->add($feed->id, $url, $post_id);

                $this->log->info(
                    "✅ Created post #{$post_id} for «{$feed->name}»",
                    [
                        'feed_id'   => $feed->id,
                        'post_id'   => $post_id,
                        'url'       => $url,
                        'thumb'     => $thumbId ? 'set' : 'none',
                        'thumb_src' => $thumbId && $rssImg ? 'RSS'
                            : ($thumbId ? 'HTML' : '-'),
                    ]
                );

                $maxTsNew = max($maxTsNew, $ts);
                ++$posted;
            } catch (\Throwable $e) {
                $this->feeds->update_status(
                    $feed->id,
                    'error',
                    $e->getMessage()
                );
                $this->log->error(
                    "❌ RSS error on «{$feed->name}»: {$e->getMessage()}",
                    ['feed_id' => $feed->id, 'url' => $url]
                );
            }
        }
        /* Persist newest timestamp */

        /* Persist newest timestamp – only if we really saw a dated, newer item */
        if ($maxTsNew > $cutOff) {
            $this->feeds->update_last_ts($feed->id, $maxTsNew);
        }

        $msg = $posted
            ? sprintf(
                _n('Added %d post', 'Added %d posts', $posted, 'sc-autoparser'),
                $posted
            )
            : __('No new items found', 'sc-autoparser');

        $this->feeds->update_status($feed->id, 'ok', $msg);
        $this->log->info(
            ($posted ? '🎉' : 'ℹ️') . " {$feed->name}: {$msg}",
            [
                'feed_id'   => $feed->id,
                'checked'   => count($rows),
                'new_count' => $posted,
            ]
        );
    }

    /* ---------- helpers ---------- */

    private function extractBoundedContent(
        Crawler $crawler,
        string $startSelector,
        ?string $endSelector = null
    ): string {
        $startNode = $crawler->filter($startSelector)->first();
        if ($startNode->count() === 0) {
            throw new \RuntimeException(
                "Start selector not found: {$startSelector}"
            );
        }

        if (empty($endSelector)) {
            return $startNode->html();
        }

        $html     = '';
        $document = $crawler->getNode(0)->ownerDocument;
        $node     = $startNode->getNode(0);

        while ($node) {
            if ((new Crawler($node))->filter($endSelector)->count() > 0
                && $node !== $startNode->getNode(0)) {
                break;
            }

            $html .= $document->saveHTML($node);
            $node = $node->nextSibling;
        }

        return $html;
    }

    /* =======================================================================
     *  URL-discoverer  (RSS + XML-sitemap + sitemapindex)
     * =====================================================================*/

    private function discover_urls(Feed $feed, int $cutOff): array
    {
        try {
            $raw = $this->fetchRss($feed->url);
            $xml = new \SimpleXMLElement($raw);

            return match ($xml->getName()) {
                'rss', 'feed' => $this->discover_from_rss($xml, $feed, $cutOff),
                'urlset' => $this->discover_from_urlset($xml, $feed, $cutOff),
                'sitemapindex' => $this->discover_from_index(
                    $xml,
                    $feed,
                    $cutOff
                ),
                default => $this->logUnknownRoot($xml->getName(), $feed),
            };
        } catch (\Throwable $e) {
            $this->log->error(
                '❌ XML load failed: ' . $feed->url,
                ['feed_id' => $feed->id, 'msg' => $e->getMessage()]
            );

            return [];
        }
    }


    /* ---------- <urlset> ---------- */
    private function discover_from_urlset(
        \SimpleXMLElement $set,
        Feed $feed,
        int $cutOff
    ): array {
        $ns   = $set->getNamespaces(true)[''] ?? '';
        $urls = $ns ? $set->children($ns)->url : $set->url;

        $out = [];
        foreach ($urls as $u) {
            $rawDate = (string)$u->lastmod;
            $ts      = $rawDate ? strtotime($rawDate) : 0;

            $link = UrlCanonicalizer::normalize((string)$u->loc);

            $wasDeleted = $this->maps->isDeleted((int)$feed->id, $link);

            // 1) Already alive in DB → пропускаємо
            if ($this->maps->exists((int)$feed->id, $link)) {
                continue;
            }

            // 2) Видалений раніше пост → завжди беремо, дата неважлива
            if ($wasDeleted) {
                /* include */
            } // 3) Новіший за cut-off → беремо
            elseif ($ts > 0 && $ts > $cutOff) {
                /* include */
            } // 4) Без дати, але це перший імпорт (cutOff == 0) → беремо
            elseif ($ts == 0 && $cutOff == 0) {
                /* include */
            } // Інакше — занадто старе, пропускаємо
            else {
                continue;
            }

            if ($feed->predict_only) {
                $l = mb_strtolower($link);
                if ( ! str_contains($l, 'prognoz') && ! str_contains(
                        $l,
                        'прогноз'
                    )) {
                    continue;
                }
            }

            $out[] = ['ts' => $ts, 'link' => $link, 'img' => null];
        }
        usort($out, static fn($a, $b) => $b['ts'] <=> $a['ts']);

        return array_slice($out, 0, $feed->limit);
    }

    /* ---------- <sitemapindex> ---------- */
    private function discover_from_index(
        \SimpleXMLElement $idx,
        Feed $feed,
        int $cutOff
    ): array {
        $ns    = $idx->getNamespaces(true)[''] ?? '';
        $nodes = $ns ? $idx->children($ns)->sitemap : $idx->sitemap;

        $submaps = [];
        foreach ($nodes as $sm) {
            $submaps[] = (string)$sm->loc;
        }

        $out = [];
        foreach ($submaps as $url) {
            try {
                $xml = new \SimpleXMLElement($this->fetchRss($url));
                if ($xml->getName() === 'urlset') {
                    $out = array_merge(
                        $out,
                        $this->discover_from_urlset($xml, $feed, $cutOff)
                    );
                }
            } catch (\Throwable $e) {
                $this->log->warning(
                    "Skip bad sitemap {$url}: " . $e->getMessage(),
                    ['feed_id' => $feed->id]
                );
            }
            if (count($out) >= $feed->limit) {
                break;
            }
        }

        usort($out, static fn($a, $b) => $b['ts'] <=> $a['ts']);

        return array_slice($out, 0, $feed->limit);
    }

    /* ---------- RSS / Atom ---------- */
    private function discover_from_rss(
        \SimpleXMLElement $rss,
        Feed $feed,
        int $cutOff
    ): array {
        $months = [
            'Січ' => 'Jan',
            'Янв' => 'Jan',
            'Фев' => 'Feb',
            'Лют' => 'Feb',
            'Бер' => 'Mar',
            'Мар' => 'Mar',
            'Кві' => 'Apr',
            'Апр' => 'Apr',
            'Тра' => 'May',
            'Мая' => 'May',
            'Чер' => 'Jun',
            'Июн' => 'Jun',
            'Лип' => 'Jul',
            'Июл' => 'Jul',
            'Сер' => 'Aug',
            'Авг' => 'Aug',
            'Вер' => 'Sep',
            'Сен' => 'Sep',
            'Жов' => 'Oct',
            'Окт' => 'Oct',
            'Лис' => 'Nov',
            'Ноя' => 'Nov',
            'Гру' => 'Dec',
            'Дек' => 'Dec',
        ];

        $out = [];
        foreach ($rss->channel->item as $item) {
            $fixed = preg_replace_callback(
                '/\s([А-Яа-яІіЇїЄєA-Za-z]{3})\s/u',
                static fn($m) => ' ' . ($months[$m[1]] ?? $m[1]) . ' ',
                (string)$item->pubDate,
                1
            );
            $ts    = strtotime($fixed) ?: 0;

            $link = UrlCanonicalizer::normalize((string)$item->link);

            // ----------- FINAL include/skip logic -----------
            $wasDeleted = $this->maps->isDeleted((int)$feed->id, $link);

            // 1) Already alive in DB → пропускаємо
            if ($this->maps->exists((int)$feed->id, $link)) {
                continue;
            }

            // 2) Видалений раніше пост → завжди беремо, дата неважлива
            if ($wasDeleted) {
                /* include */
            } // 3) Новіший за cut-off → беремо
            elseif ($ts > 0 && $ts > $cutOff) {
                /* include */
            } // 4) Без дати, але це перший імпорт (cutOff == 0) → беремо
            elseif ($ts == 0 && $cutOff == 0) {
                /* include */
            } // Інакше — занадто старе, пропускаємо
            else {
                continue;
            }

            if ($feed->predict_only && stripos(
                                           (string)$item->title,
                                           'прогноз'
                                       ) === false) {
                continue;
            }

            $img = null;
            if (isset($item->enclosure['url'])) {
                $img = (string)$item->enclosure['url'];
            } elseif ($item->children('media', true)->content) {
                $img = (string)$item->children('media', true)
                    ->content->attributes()->url;
            }

            $out[] = ['ts' => $ts, 'link' => $link, 'img' => $img ?: null];
        }
        usort($out, static fn($a, $b) => $b['ts'] <=> $a['ts']);

        return array_slice($out, 0, $feed->limit);
    }


    /* ---------- unknown root helper ---------- */
    private function logUnknownRoot(string $tag, Feed $feed): array
    {
        $this->log->warning(
            "⛔️ Unknown XML root <{$tag}>: {$feed->url}",
            ['feed_id' => $feed->id]
        );

        return [];
    }

    /* ---------- fetchRss + helpers ---------- */
    private function fetchRss(string $url): string
    {
        $ua = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) '
              . 'AppleWebKit/537.36 (KHTML, like Gecko) '
              . 'Chrome/124.0 Safari/537.36';

        $res = $this->http->get($url, [
            'headers'     => [
                'User-Agent'      => $ua,
                'Accept'          => 'application/rss+xml,application/xml;q=0.9,*/*;q=0.8',
                'Accept-Language' => 'en-US,en;q=0.9',
            ],
            'http_errors' => false,
            'timeout'     => 15,
        ]);

        $code = $res->getStatusCode();
        if ($code === 200) {
            return (string)$res->getBody();
        }

        if (in_array($code, [403, 503], true)) {
            $proxyUrl = 'https://r.jina.ai/http:' . ltrim($url, 'https://');
            $proxyRes = $this->http->get($proxyUrl, [
                'timeout'     => 15,
                'http_errors' => false,
            ]);
            if ($proxyRes->getStatusCode() === 200) {
                return (string)$proxyRes->getBody();
            }
        }

        throw new \RuntimeException("HTTP $code while fetching {$url}");
    }

    private function extract_first_image(Crawler $c, string $dir): ?int
    {
        try {
            $src = $c->filterXPath('//img/@src')->first()->text();

            return \ScAutoParser\Core\Helpers::sideload_image($src, $dir);
        } catch (\Throwable) {
            return null;
        }
    }

    private function parse_teams_from_title(string $title): ?array
    {
        $clean = trim(preg_replace('/\s+/u', ' ', $title));

        $stop = '(?:прогноз|анонс|preview|prediction|ставк[аи])';

        if (preg_match(
            "/^(.+?)\s*[–—\\-:|]\\s*([^-–—:|]+?)(?=\\s+$stop\\b|$)/ui",
            $clean,
            $m
        )) {
            return [trim($m[1]), trim($m[2])];
        }

        if (preg_match(
            "/^(.+?)\\s+v(?:s|\\.)\\s+(.+?)(?=\\s+$stop\\b|$)/ui",
            $clean,
            $m
        )) {
            return [trim($m[1]), trim($m[2])];
        }

        return null;
    }
}
