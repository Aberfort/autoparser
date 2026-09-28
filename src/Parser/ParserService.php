<?php
/**
 * ParserService
 * -------------
 *  • URL порожній  → AI-прогнози (2 промпти).
 *  • URL заданий   → RSS / XML-парсинг + AI-рерайт.
 */

namespace AutoParser\Parser;

use GuzzleHttp\Client;
use AutoParser\Core\Logger;
use AutoParser\Core\UsageTracker;
use AutoParser\Feed\Feed;
use AutoParser\Feed\FeedRepository;
use AutoParser\Feed\PostMapRepository;
use AutoParser\Publisher\GutenbergPublisher;
use AutoParser\Modules\Predictions\FixturesService;
use AutoParser\AI\ProviderFactory;
use AutoParser\AI\RewriteService as DynamicRewrite;
use AutoParser\AI\PredictionService as DynamicPredict;
use Symfony\Component\DomCrawler\Crawler;
use AutoParser\Util\UrlCanonicalizer;

class ParserService {


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
		private FixturesService $fixtures,
		private GutenbergPublisher $publisher,
		private Client $http,
		private Logger $log,
		private UsageTracker $usage,
	) {
	}

	/* ================= PUBLIC ================= */

	public function run( ?int $feed_id = null ): void {
		$feeds = $feed_id
			? array( $this->feeds->find( $feed_id ) )
			: array_filter(
				$this->feeds->all(),
				static fn( Feed $f ) => $f->active
			);

		foreach ( $feeds as $feed ) {
			if ( $feed ) {
				$this->run_feed( $feed );
			}
		}
	}

	/**
	 * Dry-run: fetch a URL and extract content the same way a real feed
	 * run would (including the fallback-selector chain), without any AI
	 * rewrite, publishing, or dedup side effects. Used by the REST
	 * preview endpoint and the WP-CLI test-selector command, so a
	 * selector can be checked before a feed is even saved.
	 *
	 * @return array{title: string, content: string, content_length: int}
	 * @throws \RuntimeException On fetch failure, or if no content
	 *                           selector could be resolved.
	 */
	public function previewExtract( string $url, string $selector = '', ?string $selectorEnd = null ): array {
		$html = (string) $this->http->get(
			$url,
			array(
				'headers' => array( 'User-Agent' => \AutoParser\Core\Helpers::random_ua() ),
				'timeout' => 15,
			)
		)->getBody();

		$crawler = new Crawler( $html );
		$content = $this->extractBoundedContent( $crawler, $selector, $selectorEnd );
		$title   = $crawler->filter( 'title' )->text( '' );

		return array(
			'title'          => $title,
			'content'        => $content,
			'content_length' => mb_strlen( wp_strip_all_tags( $content ) ),
		);
	}

	/* ================= INTERNAL ================= */

	private function run_feed( Feed $feed ): void {
		if ( $this->requires_predictions_module( $feed ) && ! $this->predictions_module_enabled() ) {
			$this->feeds->update_status(
				$feed->id,
				'error',
				__( 'Модуль AI-прогнозів вимкнено в Налаштуваннях.', 'autoparser' )
			);
			$this->log->warning(
				"⛔ {$feed->name}: predictions module is disabled, skipping run",
				array( 'feed_id' => $feed->id )
			);

			return;
		}

		$provider  = ProviderFactory::make( $feed->ai_provider ?? 'gemini' );
		$rewriter  = new DynamicRewrite( $provider );
		$predictor = new DynamicPredict( $provider );

		$this->feeds->update_status( $feed->id, 'running' );
		$this->log->info(
			"🚀 {$feed->name} (ID: {$feed->id}) started",
			array(
				'feed_id'   => $feed->id,
				'feed_name' => $feed->name,
			)
		);

		/**
		 * Fires right before a feed run starts (AI-only or RSS).
		 *
		 * @param Feed $feed The feed about to run.
		 */
		do_action( 'autoparser_before_run_feed', $feed );

		/* AI-only feed (url == '') */
		if ( $feed->url === '' ) {
			$result = $this->handle_ai_predictions( $feed, $predictor );
		} else {
			/*
			Import only items newer than last_ts,             *
			 *   except those whose previous post is deleted.   */
			$cutOff = (int) $feed->last_ts;

			$result = $this->handle_rss( $feed, $rewriter, $cutOff );
		}

		/**
		 * Fires after a feed run finishes, successfully or not.
		 *
		 * @param Feed  $feed   The feed that just ran.
		 * @param array $result { 'posted' => int, 'status' => string }
		 */
		do_action( 'autoparser_after_run_feed', $feed, $result );
	}

	/**
	 * TRUE if this feed's output depends on the (optional, off-by-default)
	 * predictions/forecast module — either it's a pure AI-forecast feed
	 * (no URL) or an RSS feed configured to publish as a forecast.
	 */
	private function requires_predictions_module( Feed $feed ): bool {
		return '' === $feed->url || $feed->predict_only;
	}

	private function predictions_module_enabled(): bool {
		$settings = get_option( 'autoparser_settings', array() );

		return ! empty( $settings['predictions_enabled'] );
	}

	/* ---------- AI-прогнози ---------- */
	private function handle_ai_predictions(
		Feed $feed,
		DynamicPredict $predictor
	): array {
		$rows = $this->fixtures->todayTop( $feed->limit );

		if ( ! $rows ) {
			$this->feeds->update_status(
				$feed->id,
				'ok',
				__( 'Матчів немає', 'autoparser' )
			);

			return array(
				'posted' => 0,
				'status' => 'ok',
			);
		}

		$posted = 0;

		foreach ( $rows as $row ) {
			$team1    = $row['team1'];
			$team2    = $row['team2'];
			$dateTime = $row['datetime'];          // «20.05.2025 22:00»
			$league   = $row['league'];

			$matchStamp = ( new \DateTimeImmutable( $dateTime ) )
				->format( 'Ymd' );
			$virtualUrl = sprintf(
				'match://%s-%s-%s',
				sanitize_title( $team1 ),
				sanitize_title( $team2 ),
				$matchStamp
			);
			$virtualUrl = UrlCanonicalizer::normalize( $virtualUrl );
			if ( $this->maps->exists( (int) $feed->id, $virtualUrl ) ) {
				continue;
			}

			$tpl    = $feed->detail_prompt ?: self::DEFAULT_DETAIL_PROMPT;
			$prompt = strtr(
				$tpl,
				array(
					'{{team1}}'    => $team1,
					'{{team2}}'    => $team2,
					'{{time}}'     => date( 'H:i', strtotime( $dateTime ) ),
					'{{datetime}}' => $dateTime,
					'{{league}}'   => $league,
					'{{date}}'     => wp_date( 'd.m.Y' ),
					'{{teams}}'    => "$team1 vs $team2",
				)
			);

			/**
			 * Filters the prompt sent to the AI provider.
			 *
			 * @param string $prompt The built prompt.
			 * @param Feed   $feed   The feed being processed.
			 * @param string $type   'title' | 'body' | 'forecast'.
			 */
			$prompt = apply_filters( 'autoparser_rewrite_prompt', $prompt, $feed, 'forecast' );

			try {
				$html = $predictor->getForecast( $prompt, array() );
				$this->usage->record( $feed->ai_provider ?: 'gemini', 'forecast' );
			} catch ( \Throwable $e ) {
				$this->log->warning(
					'Skip forecast: ' . $e->getMessage(),
					array( 'feed_id' => $feed->id )
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

			$this->maps->add( $feed->id, $virtualUrl, $post_id );

			++$posted;
			$this->log->info( "✅ Forecast #$post_id", array( 'feed_id' => $feed->id ) );
		}

		$msg = $posted
			? sprintf(
				_n(
					'Додано %d прогноз',
					'Додано %d прогнози',
					$posted,
					'autoparser'
				),
				$posted
			)
			: __( 'Матчів немає', 'autoparser' );

		$this->feeds->update_status( $feed->id, 'ok', $msg );

		return array(
			'posted' => $posted,
			'status' => 'ok',
		);
	}

	/* ---------- RSS / XML-постинг ---------- */

	private function handle_rss(
		Feed $feed,
		DynamicRewrite $rewriter,
		int $cutOff
	): array {
		$rows     = $this->discover_urls( $feed, $cutOff );   // already limited
		$posted   = 0;
		$maxTsNew = 0;                                    // track newest ts

		foreach ( $rows as $row ) {
			$url    = UrlCanonicalizer::normalize( $row['link'] );
			$rssImg = $row['img'];
			$ts     = (int) $row['ts'];

			/* Skip duplicates (alive posts) */
			if ( $this->maps->exists( (int) $feed->id, $url ) ) {
				continue;
			}

			try {
				/**
				 * Filters the User-Agent used to fetch a feed's source page.
				 *
				 * @param string $user_agent
				 * @param string $url
				 */
				$user_agent = apply_filters(
					'autoparser_user_agent',
					\AutoParser\Core\Helpers::random_ua(),
					$url
				);

				/**
				 * Filters the Guzzle request options used to fetch a feed's source page.
				 *
				 * @param array $options
				 * @param string $url
				 * @param Feed  $feed
				 */
				$http_options = apply_filters(
					'autoparser_http_options',
					array(
						'headers' => array( 'User-Agent' => $user_agent ),
						'timeout' => 15,
					),
					$url,
					$feed
				);

				$html = (string) $this->http->get( $url, $http_options )->getBody();

				$crawler = new Crawler( $html );
				$content = $this->extractBoundedContent(
					$crawler,
					$feed->selector,
					$feed->selector_end
				);

				/**
				 * Filters the extracted content before it's sent for AI rewrite.
				 *
				 * @param string  $content
				 * @param Feed    $feed
				 * @param Crawler $crawler
				 */
				$content = apply_filters( 'autoparser_extracted_content', $content, $feed, $crawler );

				$titleOriginal = $crawler->filter( 'title' )->text( '' );
				$teams         = $this->parse_teams_from_title( $titleOriginal );

				/* Local prompt */
				$rawPrompt                  = (string) $feed->prompt;
				[$titlePrompt, $bodyPrompt] =
					array_pad( explode( '---', $rawPrompt, 2 ), 2, '' );

				$titlePrompt = trim( $titlePrompt )
					?: 'Перепиши цей заголовок унікально, зберігши мову та зміст.';
				$bodyPrompt  = trim( $bodyPrompt );

				/** @see handle_ai_predictions() for the filter doc. */
				$titlePrompt = apply_filters( 'autoparser_rewrite_prompt', $titlePrompt, $feed, 'title' );
				$bodyPrompt  = apply_filters( 'autoparser_rewrite_prompt', $bodyPrompt, $feed, 'body' );

				$title = trim( $rewriter->rewrite( $titleOriginal, $titlePrompt ) )
					?: $titleOriginal;

				$rewritten = $bodyPrompt
					? $rewriter->rewrite( $content, $bodyPrompt )
					: $content;

				$this->usage->record( $feed->ai_provider ?: 'gemini', 'rewrite' );

				/* Thumbnail */
				$thumbId = null;
				if ( $feed->thumbnail_mode === 'first' ) {
					if ( $rssImg ) {
						$thumbId = \AutoParser\Core\Helpers::sideload_image(
							$rssImg,
							$feed->image_dir
						);
					}
					if ( ! $thumbId ) {
						$thumbId = $this->extract_first_image(
							$crawler,
							$feed->image_dir
						);
					}
				}

				/* Publish */
				if ( $feed->predict_only && $teams && count( $teams ) === 2 ) {
					[$team1, $team2] = array_map(
						static fn( $t ) => trim(
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
						$ts ? date( 'd.m.Y H:i', $ts ) : wp_date( 'd.m.Y H:i' ),
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

				if ( $thumbId ) {
					set_post_thumbnail( $post_id, $thumbId );
				}

				/* Map + log */
				$this->maps->add( $feed->id, $url, $post_id );

				$this->log->info(
					"✅ Created post #{$post_id} for «{$feed->name}»",
					array(
						'feed_id'   => $feed->id,
						'post_id'   => $post_id,
						'url'       => $url,
						'thumb'     => $thumbId ? 'set' : 'none',
						'thumb_src' => $thumbId && $rssImg ? 'RSS'
							: ( $thumbId ? 'HTML' : '-' ),
					)
				);

				$maxTsNew = max( $maxTsNew, $ts );
				++$posted;
			} catch ( \Throwable $e ) {
				$this->feeds->update_status(
					$feed->id,
					'error',
					$e->getMessage()
				);
				$this->log->error(
					"❌ RSS error on «{$feed->name}»: {$e->getMessage()}",
					array(
						'feed_id' => $feed->id,
						'url'     => $url,
					)
				);
			}
		}
		/* Persist newest timestamp */

		/* Persist newest timestamp – only if we really saw a dated, newer item */
		if ( $maxTsNew > $cutOff ) {
			$this->feeds->update_last_ts( $feed->id, $maxTsNew );
		}

		$msg = $posted
			? sprintf(
				_n( 'Added %d post', 'Added %d posts', $posted, 'autoparser' ),
				$posted
			)
			: __( 'No new items found', 'autoparser' );

		$this->feeds->update_status( $feed->id, 'ok', $msg );
		$this->log->info(
			( $posted ? '🎉' : 'ℹ️' ) . " {$feed->name}: {$msg}",
			array(
				'feed_id'   => $feed->id,
				'checked'   => count( $rows ),
				'new_count' => $posted,
			)
		);

		return array(
			'posted' => $posted,
			'status' => 'ok',
		);
	}

	/* ---------- helpers ---------- */

	/**
	 * Selectors tried, in order, when the feed's own selector is empty or
	 * not found on the page — covers most WordPress/news themes so a feed
	 * doesn't hard-require knowing the right CSS selector up front.
	 */
	private const FALLBACK_CONTENT_SELECTORS = array(
		'article',
		'main',
		'.entry-content',
		'.post-content',
		'.content',
		'#content',
		'body',
	);

	private function extractBoundedContent(
		Crawler $crawler,
		string $startSelector,
		?string $endSelector = null
	): string {
		$startNode = $this->findStartNode( $crawler, $startSelector );

		if ( empty( $endSelector ) ) {
			return $startNode->html();
		}

		$html     = '';
		$document = $crawler->getNode( 0 )->ownerDocument;
		$node     = $startNode->getNode( 0 );

		while ( $node ) {
			if ( ( new Crawler( $node ) )->filter( $endSelector )->count() > 0
				&& $node !== $startNode->getNode( 0 ) ) {
				break;
			}

			$html .= $document->saveHTML( $node );
			$node  = $node->nextSibling;
		}

		return $html;
	}

	/**
	 * Finds the content start node: the feed's configured selector if it
	 * matches, otherwise the first of FALLBACK_CONTENT_SELECTORS to match
	 * (ending in 'body', which always matches valid HTML).
	 *
	 * @throws \RuntimeException Only if the configured selector is set but
	 *                           not found, and even 'body' is missing (i.e.
	 *                           $html isn't a parseable HTML document).
	 */
	private function findStartNode( Crawler $crawler, string $startSelector ): Crawler {
		if ( '' !== $startSelector ) {
			$node = $crawler->filter( $startSelector )->first();
			if ( $node->count() > 0 ) {
				return $node;
			}
		}

		foreach ( self::FALLBACK_CONTENT_SELECTORS as $fallback ) {
			$node = $crawler->filter( $fallback )->first();
			if ( $node->count() > 0 ) {
				return $node;
			}
		}

		throw new \RuntimeException(
			'' !== $startSelector
				? "Start selector not found: {$startSelector}"
				: 'No content selector configured and automatic detection failed.'
		);
	}

	/**
	 * Whether a discovered item is recent enough to import, given whether
	 * its previously-imported post was since deleted. Pure/static and
	 * shared by discover_from_urlset() and discover_from_rss() (it used
	 * to be copy-pasted in both) so it can be unit-tested without a
	 * database or WordPress runtime — see tests/Unit/ParserServiceTest.php.
	 *
	 * @param bool $wasDeleted TRUE if a mapping exists but the post is gone/trashed.
	 * @param int  $ts         Item's timestamp, or 0 if it has none.
	 * @param int  $cutOff     Feed's last-seen timestamp ($feed->last_ts), or 0 on first import.
	 */
	public static function shouldIncludeByRecency( bool $wasDeleted, int $ts, int $cutOff ): bool {
		if ( $wasDeleted ) {
			return true;
		}

		if ( $ts > 0 && $ts > $cutOff ) {
			return true;
		}

		return 0 === $ts && 0 === $cutOff;
	}

	/*
	=======================================================================
	 *  URL-discoverer  (RSS + XML-sitemap + sitemapindex)
	 * =====================================================================*/

	private function discover_urls( Feed $feed, int $cutOff ): array {
		try {
			$raw = $this->fetchRss( $feed->url );
			$xml = new \SimpleXMLElement( $raw );

			return match ( $xml->getName() ) {
				'rss', 'feed' => $this->discover_from_rss( $xml, $feed, $cutOff ),
				'urlset' => $this->discover_from_urlset( $xml, $feed, $cutOff ),
				'sitemapindex' => $this->discover_from_index(
					$xml,
					$feed,
					$cutOff
				),
				default => $this->logUnknownRoot( $xml->getName(), $feed ),
			};
		} catch ( \Throwable $e ) {
			$this->log->error(
				'❌ XML load failed: ' . $feed->url,
				array(
					'feed_id' => $feed->id,
					'msg'     => $e->getMessage(),
				)
			);

			return array();
		}
	}


	/* ---------- <urlset> ---------- */
	private function discover_from_urlset(
		\SimpleXMLElement $set,
		Feed $feed,
		int $cutOff
	): array {
		$ns   = $set->getNamespaces( true )[''] ?? '';
		$urls = $ns ? $set->children( $ns )->url : $set->url;

		$out = array();
		foreach ( $urls as $u ) {
			$rawDate = (string) $u->lastmod;
			$ts      = $rawDate ? strtotime( $rawDate ) : 0;

			$link = UrlCanonicalizer::normalize( (string) $u->loc );

			$wasDeleted = $this->maps->isDeleted( (int) $feed->id, $link );

			// Already alive in DB → пропускаємо
			if ( $this->maps->exists( (int) $feed->id, $link ) ) {
				continue;
			}

			if ( ! self::shouldIncludeByRecency( $wasDeleted, $ts, $cutOff ) ) {
				continue;
			}

			if ( $feed->predict_only ) {
				$l = mb_strtolower( $link );
				if ( ! str_contains( $l, 'prognoz' ) && ! str_contains(
					$l,
					'прогноз'
				) ) {
					continue;
				}
			}

			$out[] = array(
				'ts'   => $ts,
				'link' => $link,
				'img'  => null,
			);
		}
		usort( $out, static fn( $a, $b ) => $b['ts'] <=> $a['ts'] );

		return array_slice( $out, 0, $feed->limit );
	}

	/* ---------- <sitemapindex> ---------- */
	private function discover_from_index(
		\SimpleXMLElement $idx,
		Feed $feed,
		int $cutOff
	): array {
		$ns    = $idx->getNamespaces( true )[''] ?? '';
		$nodes = $ns ? $idx->children( $ns )->sitemap : $idx->sitemap;

		$submaps = array();
		foreach ( $nodes as $sm ) {
			$submaps[] = (string) $sm->loc;
		}

		$out = array();
		foreach ( $submaps as $url ) {
			try {
				$xml = new \SimpleXMLElement( $this->fetchRss( $url ) );
				if ( $xml->getName() === 'urlset' ) {
					$out = array_merge(
						$out,
						$this->discover_from_urlset( $xml, $feed, $cutOff )
					);
				}
			} catch ( \Throwable $e ) {
				$this->log->warning(
					"Skip bad sitemap {$url}: " . $e->getMessage(),
					array( 'feed_id' => $feed->id )
				);
			}
			if ( count( $out ) >= $feed->limit ) {
				break;
			}
		}

		usort( $out, static fn( $a, $b ) => $b['ts'] <=> $a['ts'] );

		return array_slice( $out, 0, $feed->limit );
	}

	/* ---------- RSS / Atom ---------- */
	private function discover_from_rss(
		\SimpleXMLElement $rss,
		Feed $feed,
		int $cutOff
	): array {
		$months = array(
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
		);

		$out = array();
		foreach ( $rss->channel->item as $item ) {
			$fixed = preg_replace_callback(
				'/\s([А-Яа-яІіЇїЄєA-Za-z]{3})\s/u',
				static fn( $m ) => ' ' . ( $months[ $m[1] ] ?? $m[1] ) . ' ',
				(string) $item->pubDate,
				1
			);
			$ts    = strtotime( $fixed ) ?: 0;

			$link = UrlCanonicalizer::normalize( (string) $item->link );

			$wasDeleted = $this->maps->isDeleted( (int) $feed->id, $link );

			// Already alive in DB → пропускаємо
			if ( $this->maps->exists( (int) $feed->id, $link ) ) {
				continue;
			}

			if ( ! self::shouldIncludeByRecency( $wasDeleted, $ts, $cutOff ) ) {
				continue;
			}

			if ( $feed->predict_only && stripos(
				(string) $item->title,
				'прогноз'
			) === false ) {
				continue;
			}

			$img = null;
			if ( isset( $item->enclosure['url'] ) ) {
				$img = (string) $item->enclosure['url'];
			} elseif ( $item->children( 'media', true )->content ) {
				$img = (string) $item->children( 'media', true )
					->content->attributes()->url;
			}

			$out[] = array(
				'ts'   => $ts,
				'link' => $link,
				'img'  => $img ?: null,
			);
		}
		usort( $out, static fn( $a, $b ) => $b['ts'] <=> $a['ts'] );

		return array_slice( $out, 0, $feed->limit );
	}


	/* ---------- unknown root helper ---------- */
	private function logUnknownRoot( string $tag, Feed $feed ): array {
		$this->log->warning(
			"⛔️ Unknown XML root <{$tag}>: {$feed->url}",
			array( 'feed_id' => $feed->id )
		);

		return array();
	}

	/* ---------- fetchRss + helpers ---------- */
	private function fetchRss( string $url ): string {
		$ua = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) '
				. 'AppleWebKit/537.36 (KHTML, like Gecko) '
				. 'Chrome/124.0 Safari/537.36';

		$res = $this->http->get(
			$url,
			array(
				'headers'     => array(
					'User-Agent'      => $ua,
					'Accept'          => 'application/rss+xml,application/xml;q=0.9,*/*;q=0.8',
					'Accept-Language' => 'en-US,en;q=0.9',
				),
				'http_errors' => false,
				'timeout'     => 15,
			)
		);

		$code = $res->getStatusCode();
		if ( $code === 200 ) {
			return (string) $res->getBody();
		}

		$settings = get_option( 'autoparser_settings', array() );

		/* Third-party fallback proxy — opt-in only, see Settings > "Резервний проксі". */
		if ( in_array( $code, array( 403, 503 ), true ) && ! empty( $settings['enable_fallback_proxy'] ) ) {
			$proxyUrl = 'https://r.jina.ai/' . $url;
			$proxyRes = $this->http->get(
				$proxyUrl,
				array(
					'timeout'     => 15,
					'http_errors' => false,
				)
			);
			if ( $proxyRes->getStatusCode() === 200 ) {
				return (string) $proxyRes->getBody();
			}
		}

		throw new \RuntimeException( "HTTP $code while fetching {$url}" );
	}

	private function extract_first_image( Crawler $c, string $dir ): ?int {
		try {
			$src = $c->filterXPath( '//img/@src' )->first()->text();

			return \AutoParser\Core\Helpers::sideload_image( $src, $dir );
		} catch ( \Throwable ) {
			return null;
		}
	}

	private function parse_teams_from_title( string $title ): ?array {
		$clean = trim( preg_replace( '/\s+/u', ' ', $title ) );

		$stop = '(?:прогноз|анонс|preview|prediction|ставк[аи])';

		if ( preg_match(
			"/^(.+?)\s*[–—\\-:|]\\s*([^-–—:|]+?)(?=\\s+$stop\\b|$)/ui",
			$clean,
			$m
		) ) {
			return array( trim( $m[1] ), trim( $m[2] ) );
		}

		if ( preg_match(
			"/^(.+?)\\s+v(?:s|\\.)\\s+(.+?)(?=\\s+$stop\\b|$)/ui",
			$clean,
			$m
		) ) {
			return array( trim( $m[1] ), trim( $m[2] ) );
		}

		return null;
	}
}
