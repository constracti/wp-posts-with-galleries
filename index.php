<?php

/*
 * Plugin Name: KGR Posts with Galleries
 * Plugin URI: https://github.com/constracti/wp-posts-with-galleries
 * Description: Displays posts with galleries in a tools page list.
 * Version: 1.0.0
 * Requires at least: 6.5.0
 * Requires PHP: 8.1
 * Author: constracti
 * Author URI: https://github.com/constracti
 * License: GPLv3
 * License URI: https://www.gnu.org/licenses/gpl-3.0.html
 * Text Domain: kgr-posts-with-galleries
 * Domain Path: /languages
 */

if ( !defined( 'ABSPATH' ) )
	exit;

if ( !class_exists( 'WP_List_Table' ) ) {
	require_once( ABSPATH . 'wp-admin/includes/class-wp-list-table.php' );
}


class KGR_Posts_With_Galleries extends WP_List_Table {

	function prepare_items(): void {
		// columns
		$columns = $this->get_columns();
		$hidden = [];
		$sortable = $this->get_sortable_columns();
		$this->_column_headers = [ $columns, $hidden, $sortable ];
		// pagination
		$total_items = count( get_posts( [
			's' => '[gallery',
			'post_type' => 'post',
			'nopaging' => TRUE,
			'fields' => 'ids',
		] ) );
		$paged = $this->get_pagenum();
		$per_page = 10;
		$this->set_pagination_args( [
			'total_items' => $total_items,
			'per_page' => $per_page,
			'total_pages' => ceil( $total_items / $per_page ),
		] );
		// items
		$posts = get_posts( [
			's' => '[gallery',
			'post_type' => 'post',
			'posts_per_page' => $per_page,
			'paged' => $paged,
			'orderby' => isset( $_GET['orderby'] ) ? $_GET['orderby'] : 'ID',
			'order' => isset( $_GET['order'] ) ? $_GET['order'] : 'DESC',
		] );
		$this->items = array_map( function( WP_Post $post ): array {
			$regex = '/' . get_shortcode_regex( ['gallery'] ) . '/';
			$gal = [];
			preg_match_all( $regex, $post->post_content, $matches, PREG_SET_ORDER );
			foreach ( $matches as $m ) {
				$atts = shortcode_parse_atts( $m[3] );
				$ids = isset( $atts['ids'] ) ? array_map( 'intval', explode( ',', $atts['ids'] ) ) : [];
				$gal[] = count( $ids );
			}
			return [
				'post' => $post,
				'galleries' => count( $gal ),
				'photos' => array_sum( $gal ),
				'details' => implode( '+', $gal ),
			];
		}, $posts );
	}

	function get_columns(): array {
		return [
			'id' => esc_html__( 'ID', 'kgr-posts-with-galleries' ),
			'title' => esc_html__( 'Title', 'kgr-posts-with-galleries' ),
			'galleries' => esc_html__( 'Galleries', 'kgr-posts-with-galleries' ),
			'photos' => esc_html__( 'Photos', 'kgr-posts-with-galleries' ),
			'details' => esc_html__( 'Details', 'kgr-posts-with-galleries' ),
		];
	}

	protected function get_sortable_columns(): array {
		return [
			'id' => ['id', FALSE],
		];
	}

	function column_id( array $item ): string {
		$post = $item['post'];
		return sprintf( '<a href="%s">%d</a>', admin_url(), $post->ID );
	}

	function column_title( array $item ): string {
		$post = $item['post'];
		return sprintf( '<a href="%s">%s</a>', post_permalink( $post ), $post->post_title );
	}

	function column_default( $item, $column_name ): string {
		return strval( $item[$column_name] );
	}
}

add_action( 'admin_menu', function() {
	$title = esc_html__( 'Posts with Galleries', 'kgr-posts-with-galleries' );
	$slug = 'kgr-posts-with-galleries';
	add_management_page( $title, $title, 'edit_pages', $slug, function(): void {
		$title = esc_html__( 'Posts with Galleries', 'kgr-posts-with-galleries' );
		$slug = 'kgr-posts-with-galleries';
		echo '<div class="wrap">' . "\n";
		echo sprintf( '<h1>%s</h1>', $title ) . "\n";
		echo '<form>' . "\n";
		$table = new KGR_Posts_With_Galleries();
		$table->prepare_items();
		$table->display();
		echo '</form>' . "\n";
		echo '</div>' . "\n";
?>
<style>
.column-id,
.column-galleries,
.column-photos {
	width: 100px;
}
.column-details {
	width: 200px;
}
</style>
<?php
	} );
} );
