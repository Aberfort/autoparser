<?php
add_filter( 'pre_set_site_transient_update_plugins', 'scap_update_check' );
function scap_update_check( $transient ) {
	if ( empty( $transient->checked ) ) {
		return $transient;
	}

	$remote = wp_remote_get( 'https://stage.t42it.info/wp-content/plugins/sc-autoparser/info.json' );
	if ( is_wp_error( $remote ) ) {
		return $transient;
	}

	$data = json_decode( wp_remote_retrieve_body( $remote ), true );
	if ( ! $data || empty( $data['new_version'] ) ) {
		return $transient;
	}

	$plugin_file = plugin_basename( SC_AUTOPARSER_DIR . 'sc-autoparser.php' );

	$current_version = isset( $transient->checked[ $plugin_file ] )
		? $transient->checked[ $plugin_file ]
		: SC_AUTOPARSER_VERSION;

	if ( version_compare( $current_version, $data['new_version'], '<' ) ) {
		$transient->response[ $plugin_file ] = (object) array(
			'slug'        => 'sc-autoparser',
			'plugin'      => $plugin_file,
			'new_version' => $data['new_version'],
			'url'         => 'https://stage.t42it.info/wp-content/plugins/sc-autoparser/readme.md',
			'package'     => $data['package'],
		);
	}

	return $transient;
}

add_filter( 'plugins_api', 'scap_plugins_api', 10, 3 );
function scap_plugins_api( $result, $action, $args ) {
	if ( 'plugin_information' !== $action || 'sc-autoparser' !== $args->slug ) {
		return $result;
	}

	$remote = wp_remote_get( 'https://stage.t42it.info/wp-content/plugins/sc-autoparser/info.json' );
	if ( is_wp_error( $remote ) ) {
		return $result;
	}

	$data = json_decode( wp_remote_retrieve_body( $remote ), true );
	if ( ! $data ) {
		return $result;
	}

	return (object) array(
		'name'          => 'SC Autoparser',
		'slug'          => 'sc-autoparser',
		'version'       => $data['new_version'],
		'tested'        => $data['tested'],
		'requires'      => $data['requires'],
		'last_updated'  => $data['last_updated'],
		'sections'      => $data['sections'],
		'download_link' => $data['package'],
	);
}
