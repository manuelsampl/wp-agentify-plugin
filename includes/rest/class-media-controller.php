<?php
/**
 * Media controller: upload attachments to the WP media library + picker.
 *
 * @package WPAgentify
 */

namespace WPAgentify\Rest;

use WPAgentify\Rest_Manager;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Routes: /media (list), /media/upload.
 */
class Media_Controller {

	/**
	 * Register routes.
	 *
	 * @return void
	 */
	public function register_routes() {
		$ns = Rest_Manager::NAMESPACE;

		register_rest_route(
			$ns,
			'/media',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'index' ),
				'permission_callback' => array( $this, 'can_upload' ),
			)
		);

		register_rest_route(
			$ns,
			'/media/upload',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'upload' ),
				'permission_callback' => array( $this, 'can_upload' ),
			)
		);
	}

	/**
	 * Permission: user can upload files.
	 *
	 * @return bool
	 */
	public function can_upload() {
		return current_user_can( 'upload_files' );
	}

	/**
	 * List media items for the picker.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function index( $request ) {
		$query = new \WP_Query(
			array(
				'post_type'      => 'attachment',
				'post_status'    => 'inherit',
				'posts_per_page' => min( 60, (int) $request->get_param( 'per_page' ) ?: 30 ),
				'paged'          => max( 1, (int) $request->get_param( 'page' ) ?: 1 ),
				's'              => sanitize_text_field( (string) $request->get_param( 'search' ) ),
			)
		);

		$items = array();
		foreach ( $query->posts as $post ) {
			$items[] = $this->format_attachment( $post->ID );
		}

		return rest_ensure_response(
			array(
				'items' => $items,
				'total' => (int) $query->found_posts,
			)
		);
	}

	/**
	 * Handle a file upload into the media library.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function upload( $request ) {
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';

		$files = $request->get_file_params();
		if ( empty( $files['file'] ) ) {
			return new \WP_Error( 'no_file', 'No file uploaded', array( 'status' => 400 ) );
		}

		$attachment_id = media_handle_sideload( $files['file'], 0 );
		if ( is_wp_error( $attachment_id ) ) {
			return $attachment_id;
		}

		return rest_ensure_response( $this->format_attachment( $attachment_id ) );
	}

	/**
	 * Normalize an attachment for the SPA.
	 *
	 * @param int $id Attachment id.
	 * @return array
	 */
	private function format_attachment( $id ) {
		return array(
			'id'    => $id,
			'url'   => wp_get_attachment_url( $id ),
			'thumb' => wp_get_attachment_image_url( $id, 'thumbnail' ),
			'title' => get_the_title( $id ),
			'mime'  => get_post_mime_type( $id ),
			'alt'   => get_post_meta( $id, '_wp_attachment_image_alt', true ),
		);
	}
}
