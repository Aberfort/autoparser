<?php

namespace AutoParser\Admin;

use AutoParser\Feed\Feed;
use AutoParser\Feed\FeedRepository;

/**
 * OPML export for RSS-sourced feeds.
 *
 * Served via admin-post.php rather than the REST API: a REST route
 * always wraps its callback's return value as JSON, which would need
 * extra work (rest_pre_serve_request) to bypass just to serve a raw XML
 * download — admin-post.php is the simpler, standard WP way to do that.
 */
class OpmlHandler {

	public const ACTION = 'autoparser_export_opml';

	public function __construct( private FeedRepository $feeds ) {
	}

	public function register(): void {
		add_action( 'admin_post_' . self::ACTION, array( $this, 'export' ) );
	}

	public function export(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Недостатньо прав.', 'autoparser' ), 403 );
		}
		check_admin_referer( self::ACTION );

		$feeds = array_filter( $this->feeds->all(), static fn( Feed $f ) => '' !== $f->url );

		nocache_headers();
		header( 'Content-Type: text/x-opml+xml; charset=UTF-8' );
		header( 'Content-Disposition: attachment; filename="autoparser-feeds.opml"' );

		echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
		echo '<opml version="2.0">' . "\n";
		echo '<head><title>' . esc_html( get_bloginfo( 'name' ) ) . ' — Autoparser</title></head>' . "\n";
		echo '<body>' . "\n";

		foreach ( $feeds as $feed ) {
			printf(
				"\t" . '<outline text="%1$s" title="%1$s" type="rss" xmlUrl="%2$s"/>' . "\n",
				esc_attr( $feed->name ),
				esc_attr( $feed->url )
			);
		}

		echo '</body></opml>';
		exit;
	}
}
