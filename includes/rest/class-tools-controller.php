<?php
/**
 * Tool controller: executes agent tool calls on this WordPress site.
 *
 * @package WPAgentify
 */

namespace WPAgentify\Rest;

use WPAgentify\Rest_Manager;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Route: /tool — bearer-authenticated by the stored site token. Executes the
 * named tool (pages, posts, SEO) and returns a result for the agent.
 */
class Tools_Controller {

	public function register_routes() {
		register_rest_route( Rest_Manager::NAMESPACE, '/tool', array(
			'methods'             => 'POST',
			'callback'            => array( $this, 'run' ),
			'permission_callback' => array( $this, 'auth' ),
		) );
	}

	public function auth( $request ) {
		$auth   = (string) $request->get_header( 'authorization' );
		$token  = trim( str_ireplace( 'bearer', '', $auth ) );
		return $token !== '' && hash_equals( (string) get_option( 'wpagentify_site_token', '' ), $token );
	}

	public function run( $request ) {
		$tool = sanitize_text_field( (string) $request->get_param( 'tool' ) );
		$args = (array) $request->get_param( 'args' );

		switch ( $tool ) {
			case 'list_pages':
				$q = get_posts( array( 'post_type' => 'page', 'numberposts' => 100 ) );
				return rest_ensure_response( array_map( fn( $p ) => array( 'id' => $p->ID, 'title' => $p->post_title, 'url' => get_permalink( $p ) ), $q ) );
			case 'list_posts':
				$q = get_posts( array( 'post_type' => 'post', 'numberposts' => 100 ) );
				return rest_ensure_response( array_map( fn( $p ) => array( 'id' => $p->ID, 'title' => $p->post_title, 'url' => get_permalink( $p ) ), $q ) );
			case 'get_post':
				$p = get_post( (int) ( $args['id'] ?? 0 ) );
				return rest_ensure_response( $p ? array( 'id' => $p->ID, 'title' => $p->post_title, 'content' => $p->post_content, 'excerpt' => $p->post_excerpt ) : array() );
			case 'create_page':
			case 'create_post':
				$id = wp_insert_post( array( 'post_type' => $tool === 'create_page' ? 'page' : 'post', 'post_status' => ( $args['publish'] ?? false ) ? 'publish' : 'draft', 'post_title' => sanitize_text_field( $args['title'] ?? 'Untitled' ), 'post_content' => wp_kses_post( $args['content'] ?? '' ), 'post_excerpt' => sanitize_text_field( $args['excerpt'] ?? '' ) ) );
				return rest_ensure_response( array( 'id' => $id, 'edit' => admin_url( 'post.php?post=' . $id . '&action=edit' ) ) );
			case 'update_post':
				wp_update_post( array( 'ID' => (int) $args['id'], 'post_content' => wp_kses_post( $args['content'] ?? '' ), 'post_title' => sanitize_text_field( $args['title'] ?? get_the_title( (int) $args['id'] ) ) ) );
				return rest_ensure_response( array( 'id' => (int) $args['id'], 'ok' => true ) );
			case 'delete_post':
				return rest_ensure_response( array( 'ok' => (bool) wp_trash_post( (int) ( $args['id'] ?? 0 ) ) ) );
			case 'set_seo':
				$id = (int) ( $args['id'] ?? 0 );
				update_post_meta( $id, '_yoast_wpseo_title', sanitize_text_field( $args['title'] ?? '' ) );
				update_post_meta( $id, '_yoast_wpseo_metadesc', sanitize_text_field( $args['description'] ?? '' ) );
				update_post_meta( $id, 'rank_math_title', sanitize_text_field( $args['title'] ?? '' ) );
				update_post_meta( $id, 'rank_math_description', sanitize_text_field( $args['description'] ?? '' ) );
				return rest_ensure_response( array( 'id' => $id, 'ok' => true ) );
			case 'set_alt':
				update_post_meta( (int) $args['id'], '_wp_attachment_image_alt', sanitize_text_field( $args['alt'] ?? '' ) );
				return rest_ensure_response( array( 'ok' => true ) );
			case 'list_media':
				$q = get_posts( array( 'post_type' => 'attachment', 'numberposts' => 100, 'post_mime_type' => 'image' ) );
				return rest_ensure_response( array_map( fn( $p ) => array( 'id' => $p->ID, 'url' => wp_get_attachment_url( $p->ID ), 'alt' => get_post_meta( $p->ID, '_wp_attachment_image_alt', true ) ), $q ) );
			case 'list_plugins':
				if ( ! function_exists( 'get_plugins' ) ) { require_once ABSPATH . 'wp-admin/includes/plugin.php'; }
				return rest_ensure_response( array( 'active' => get_option( 'active_plugins', array() ), 'all' => array_keys( get_plugins() ) ) );
			case 'get_theme':
				$t = wp_get_theme();
				return rest_ensure_response( array( 'name' => $t->get( 'Name' ), 'template' => get_template(), 'is_block' => function_exists( 'wp_is_block_theme' ) && wp_is_block_theme() ) );
			case 'get_option':
				return rest_ensure_response( array( 'value' => get_option( sanitize_key( $args['key'] ?? '' ) ) ) );
			case 'set_option':
				return rest_ensure_response( array( 'ok' => update_option( sanitize_key( $args['key'] ?? '' ), $args['value'] ?? '' ) ) );
			case 'list_categories':
				return rest_ensure_response( array_map( fn( $c ) => array( 'id' => $c->term_id, 'name' => $c->name ), get_categories( array( 'hide_empty' => false ) ) ) );
			case 'sitemap':
				return rest_ensure_response( array( 'pages' => array_map( fn( $p ) => get_permalink( $p ), get_posts( array( 'post_type' => array( 'page', 'post' ), 'numberposts' => 200 ) ) ) ) );
			default:
				return new \WP_Error( 'unknown_tool', 'Unknown tool', array( 'status' => 400 ) );
		}
	}
}
