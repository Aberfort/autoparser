<?php

namespace ScAutoParser\Feed;

/**
 * Registers CPT `sc_feed` for convenient list-table in WP-Admin.
 */
class PostType {

	public function register(): void {

		$labels = array(
			'name'          => 'Авто-ленти',
			'singular_name' => 'Авто-лента',
			'add_new'       => 'Додати ленту',
			'edit_item'     => 'Редагувати ленту',
			'new_item'      => 'Нова лента',
			'menu_name'     => 'SC Автоленти',
		);

		register_post_type(
			'sc_feed',
			array(
				'labels'          => $labels,
				'public'          => false,
				'show_ui'         => true,
				'show_in_menu'    => false,
				'supports'        => array( 'title' ),
				'capability_type' => 'post',
			)
		);
	}
}
