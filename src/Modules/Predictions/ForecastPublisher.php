<?php

namespace AutoParser\Modules\Predictions;

use AutoParser\Feed\Feed;

/**
 * Публікація прогноз-постів (ACF content-builder) + Yoast SEO-мета.
 *
 * Extracted out of GutenbergPublisher, which now only ever handles the
 * "normal" parse_blocks() RSS path — this class owns everything specific
 * to the (optional, off-by-default) predictions/forecast module.
 */
class ForecastPublisher {

	public function publish(
		Feed $feed,
		string $team1,
		string $team2,
		string $datetime,   // «12.05.2025 21:00»
		string $forecastHtml,
		string $rawLine     // рядок із 1-го промпту
	): int {
		$ts   = strtotime( $datetime );
		$date = $ts
			? gmdate( 'd.m.Y', $ts )
			: $datetime;

		$site  = get_bloginfo( 'name' );
		$title = sprintf(
			'%s – %s ⇒ прогноз на матч на %s от %s',
			$team1,
			$team2,
			$date,
			$site
		);

		$h1   = sprintf( 'Прогноз на матч %s – %s', $team1, $team2 );
		$html = '<h1>' . esc_html( $h1 ) . "</h1>\n" . $forecastHtml;

		$postId = $this->insert( $feed, $title, $html );

		/* ───── Yoast SEO для прогнозів ───── */
		$tokens = array(
			'{{team1}}'    => $team1,
			'{{team2}}'    => $team2,
			'{{date}}'     => $date,
			'{{sitename}}' => $site,
			'{{title}}'    => $title,
			'{{excerpt}}'  => $rawLine,
		);

		// якщо адміністратор нічого не вказав — використовуємо дефолт
		$metaTitleTpl = $feed->meta_title ?: '{{team1}} - {{team2}} ⇒ прогноз на матч на {{date}} от {{sitename}}';
		$metaDescTpl  = $feed->meta_description ?: 'Прогноз и анонс матча {{team1}} - {{team2}} {{date}} ⚡️ Лучшие прогнозы, анонсы футбольных матчей от {{sitename}}';

		update_post_meta(
			$postId,
			'_yoast_wpseo_title',
			strtr( $metaTitleTpl, $tokens )
		);
		update_post_meta(
			$postId,
			'_yoast_wpseo_metadesc',
			strtr( $metaDescTpl, $tokens )
		);

		return $postId;
	}

	private function insert( Feed $feed, string $title, string $html ): int {
		$postArgs = array(
			'post_title'   => wp_strip_all_tags( $title ),
			'post_status'  => $feed->status,
			'post_type'    => $feed->post_type,
			'post_author'  => $feed->author_id,
			'tax_input'    => array( 'category' => $feed->categories ),
			'post_content' => '',
		);

		/** @see \AutoParser\Publisher\GutenbergPublisher::insert() for the filter doc. */
		$postArgs = apply_filters( 'autoparser_post_args', $postArgs, $feed, $title, $html );
		$postId   = wp_insert_post( $postArgs );

		if ( function_exists( 'add_row' ) ) {
			$sectionRow = array(
				'acf_fc_layout' => 'section',
				'widgets'       => array(
					array(
						'acf_fc_layout' => 'main-baner',
					),
					array(
						'acf_fc_layout' => 'content',
						'content'       => $html,
					),
					array(
						'acf_fc_layout' => 'buttons-area',
						'alignment'     => 'center',

						'buttons'       => array(
							array(
								'label' => __(
									'Зробити ставку',
									'autoparser'
								),
								'url'   => '[get-url-api url=reg_url]',
								'style' => 'gradient',
							),
						),
					),
				),
			);
			add_row( 'content_builder', $sectionRow, $postId );
		}

		/** @see \AutoParser\Publisher\GutenbergPublisher::insert() for the action doc. */
		do_action( 'autoparser_post_published', $postId, $feed );

		return $postId;
	}
}
