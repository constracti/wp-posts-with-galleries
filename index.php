<?php

/*
 * Plugin Name: KGR Posts with Galleries
 * Plugin URI: https://github.com/constracti/wp-posts-with-galleries
 * Description: Displays posts with galleries in a tools page list.
 * Version: 1.1.1
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
			$galleries = [];
			preg_match_all( $regex, $post->post_content, $matches, PREG_SET_ORDER );
			foreach ( $matches as $m ) {
				$atts = shortcode_parse_atts( $m[3] );
				$ids = isset( $atts['ids'] ) ? array_map( 'intval', explode( ',', $atts['ids'] ) ) : get_posts( [
					'post_type' => 'attachment',
					'post_mime_type' => 'image',
					'post_parent' => $post->ID,
					'nopaging' => TRUE,
					'fields' => 'ids',
				] ) ;
				$galleries[] = $ids;
			}
			return [
				'post' => $post,
				'galleries' => $galleries,
			];
		}, $posts );
	}

	function get_columns(): array {
		return [
			'id' => esc_html__( 'ID', 'kgr-posts-with-galleries' ),
			'title' => esc_html__( 'Title', 'kgr-posts-with-galleries' ),
			'date' => esc_html__( 'Date', 'kgr-posts-with-galleries' ),
			'galleries' => esc_html__( 'Galleries', 'kgr-posts-with-galleries' ),
			'photos' => esc_html__( 'Photos', 'kgr-posts-with-galleries' ),
			'filesize' => esc_html__( 'Filesize', 'kgr-posts-with-galleries' ),
		];
	}

	protected function get_sortable_columns(): array {
		return [
			'id' => ['id', FALSE],
		];
	}

	function column_id( array $item ): string {
		$post = $item['post'];
		$href = admin_url( 'post.php' );
		$href = add_query_arg( [
			'post' => $post->ID,
			'action' => 'edit',
		], $href );
		return sprintf( '<a href="%s">%d</a>', $href, $post->ID );
	}

	function column_title( array $item ): string {
		$post = $item['post'];
		return sprintf( '<a href="%s">%s</a>', post_permalink( $post ), $post->post_title );
	}

	function column_date( array $item ): string {
		$post = $item['post'];
		return get_the_date( get_option( 'date_format' ), $post );
	}

	function column_galleries( array $item ): string {
		return strval( count( $item['galleries'] ) );
	}

	function column_photos( array $item ): string {
		return sprintf( '%d=%s',
			array_sum( array_map( 'count', $item['galleries'] ) ),
			implode( '+', array_map( 'count', $item['galleries'] ) ),
		);
	}

	function column_filesize( array $item ): string {
		$upload = wp_get_upload_dir()['basedir'] . '/';
		$filesize = 0;
		foreach ( $item['galleries'] as $ids ) {
			foreach ( $ids as $id ) {
				$meta = wp_get_attachment_metadata( $id );
				//var_dump( $meta );
				$sizes = [];
				$errors = [];
				$paths['main'] = $meta['file']; // includes subdir; size via $meta['filesize']
				$subdir = dirname( $meta['file'] ) . '/';
				if ( isset( $meta['filesize'] ) && ( $fs = $meta['filesize'] ) !== 0 ) {
					$sizes['main'] = $fs;
					$filesize += $fs;
				} else {
					//$errors[] = 'main: size not found';
					if ( ( $fs = filesize( $upload . $meta['file'] ) ) !== FALSE ) {
						$sizes['main'] = $fs;
						$filesize += $fs;
					} else {
						$errors[] = 'main: file not found';
					}
				}
				if ( isset( $meta['original_image'] ) ) {
					$paths['original'] = $meta['original_image']; // does not include subdir; size via filesize()
					if ( ( $fs = filesize( $upload . $subdir . $meta['original_image'] ) ) !== FALSE )
						$sizes['original'] = $fs;
					else
						$errors[] = 'original: file not found';
				}
				foreach ( $meta['sizes'] as $size => $meta ) {
					$paths[$size] = $meta['file']; // does not include subdir; size via $meta['filesize']
					if ( isset( $meta['filesize'] ) && ( $fs = $meta['filesize'] ) !== 0 ) {
						$sizes[$size] = $fs;
						$filesize += $fs;
					} else {
						//$errors[] = $size . ': size not found';
						if ( ( $fs = filesize( $upload . $subdir . $meta['file'] ) ) !== FALSE ) {
							$sizes[$size] = $fs;
							$filesize += $fs;
						} else {
							$errors[] = $size . ': file not found';
						}
					}
				}
				if ( !empty( $errors ) ) {
					echo '<pre>' . "\n";
					print_r( $paths );
					print_r( $sizes );
					print_r( $errors );
					echo '</pre>' . "\n";
				}
			}
		}
		return sprintf( '%.1f MB', $filesize / (1024 * 1024) );
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
		echo sprintf( '<input type="hidden" name="page" value="%s">', $slug ) . "\n";
		if ( isset( $_GET['orderby'] ) )
			echo sprintf( '<input type="hidden" name="orderby" value="%s">', $_GET['orderby'] ) . "\n";
		if ( isset( $_GET['order'] ) )
			echo sprintf( '<input type="hidden" name="order" value="%s">', $_GET['order'] ) . "\n";
		$table = new KGR_Posts_With_Galleries();
		$table->prepare_items();
		$table->display();
		echo '</form>' . "\n";
		echo '</div>' . "\n";
?>
<style>
.column-id,
.column-date,
.column-galleries,
.column-filesize {
	width: 100px;
}
.column-photos {
	width: 200px;
}
.column-filesize {
	text-align: right;
}
</style>
<?php
	} );
} );
