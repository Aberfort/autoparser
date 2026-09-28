<?php

namespace AutoParser\Integrations;

use AutoParser\Feed\Feed;

/**
 * Sets a feed-generated post's language in WPML or Polylang, if either
 * is active and the feed has a language configured. Pure no-op
 * otherwise — the plugin has no hard dependency on either.
 */
class MultilingualSync {

	public function register(): void {
		add_action( 'autoparser_post_published', array( $this, 'sync' ), 10, 2 );
	}

	public function sync( int $post_id, Feed $feed ): void {
		if ( '' === $feed->language ) {
			return;
		}

		if ( function_exists( 'pll_set_post_language' ) ) {
			pll_set_post_language( $post_id, $feed->language );

			return;
		}

		if ( defined( 'ICL_SITEPRESS_VERSION' ) ) {
			do_action(
				'wpml_set_element_language_details',
				array(
					'element_id'    => $post_id,
					'element_type'  => 'post_' . $feed->post_type,
					'language_code' => $feed->language,
				)
			);
		}
	}
}
