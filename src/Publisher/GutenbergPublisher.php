<?php

namespace AutoParser\Publisher;

use AutoParser\Feed\Feed;

/**
 * Публікація дописів (Gutenberg-блоки) + Yoast SEO-мета.
 */
class GutenbergPublisher {

	/** Публікує «звичайний» (RSS) пост */
	public function publish( Feed $feed, string $title, string $content ): int {
		$postId = $this->insert( $feed, $title, $content );

		/** ───── Yoast SEO (якщо адміністратор заповнив шаблони) ───── */
		if ( $feed->meta_title || $feed->meta_description ) {
			$excerpt = wp_trim_words( wp_strip_all_tags( $content ), 40, '…' );

			$tokens = array(
				'{{title}}'    => $title,
				'{{excerpt}}'  => $excerpt,
				'{{sitename}}' => get_bloginfo( 'name' ),
				'{{date}}'     => wp_date( 'd.m.Y' ),
			);

			if ( $feed->meta_title ) {
				update_post_meta(
					$postId,
					'_yoast_wpseo_title',
					strtr( $feed->meta_title, $tokens )
				);
			}

			if ( $feed->meta_description ) {
				update_post_meta(
					$postId,
					'_yoast_wpseo_metadesc',
					strtr( $feed->meta_description, $tokens )
				);
			}
		}

		return $postId;
	}

	private function insert( Feed $feed, string $title, string $html ): int {
		$postArgs = array(
			'post_title'  => wp_strip_all_tags( $title ),
			'post_status' => $feed->status,
			'post_type'   => $feed->post_type,
			'post_author' => $feed->author_id,
			'tax_input'   => array( 'category' => $feed->categories ),
		);

		$blocks = parse_blocks( $html );
		if ( empty( $blocks ) ) {
			$blocks = array(
				array(
					'blockName'   => 'core/paragraph',
					'attrs'       => array(),
					'innerHTML'   => wp_kses_post( $html ),
					'innerBlocks' => array(),
				),
			);
		}

		$postArgs['post_content'] = serialize_blocks( $blocks );

		/**
		 * Filters the wp_insert_post() args before a feed-generated post is inserted.
		 *
		 * @param array  $postArgs
		 * @param Feed   $feed
		 * @param string $title
		 * @param string $html Rewritten content before block-serialization.
		 */
		$postArgs = apply_filters( 'autoparser_post_args', $postArgs, $feed, $title, $html );
		$postId   = wp_insert_post( $postArgs );

		/**
		 * Fires after a feed-generated post has been inserted.
		 *
		 * @param int  $postId
		 * @param Feed $feed
		 */
		do_action( 'autoparser_post_published', $postId, $feed );

		return $postId;
	}
}
