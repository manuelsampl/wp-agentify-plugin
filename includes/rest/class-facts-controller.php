<?php
/**
 * Facts controller: read/write the agent's structured site knowledge.
 *
 * @package WPAgentify
 */

namespace WPAgentify\Rest;

use WPAgentify\Rest_Manager;
use WPAgentify\DB\Facts_Repository;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Routes: /facts, /facts/{group}.
 */
class Facts_Controller {

	/**
	 * Facts repository.
	 *
	 * @var Facts_Repository
	 */
	private $repo;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->repo = new Facts_Repository();
	}

	/**
	 * Register routes.
	 *
	 * @return void
	 */
	public function register_routes() {
		register_rest_route(
			Rest_Manager::NAMESPACE,
			'/facts',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( $this, 'get_all' ),
					'permission_callback' => array( Rest_Manager::class, 'can_use' ),
				),
				array(
					'methods'             => 'PUT',
					'callback'            => array( $this, 'upsert' ),
					'permission_callback' => array( Rest_Manager::class, 'can_manage' ),
				),
			)
		);

		register_rest_route(
			Rest_Manager::NAMESPACE,
			'/facts/(?P<group>[a-z0-9_-]+)',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_group' ),
				'permission_callback' => array( Rest_Manager::class, 'can_use' ),
			)
		);
	}

	/**
	 * Get all facts.
	 *
	 * @return \WP_REST_Response
	 */
	public function get_all() {
		return rest_ensure_response( $this->repo->all() );
	}

	/**
	 * Get facts of a group.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function get_group( $request ) {
		$group = sanitize_key( (string) $request->get_param( 'group' ) );
		return rest_ensure_response( $this->repo->all( $group ) );
	}

	/**
	 * Upsert facts.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function upsert( $request ) {
		$facts = (array) $request->get_param( 'facts' );
		$clean = array();

		foreach ( $facts as $fact ) {
			if ( empty( $fact['key'] ) ) {
				continue;
			}
			$clean[] = array(
				'key'   => sanitize_text_field( $fact['key'] ),
				'value' => $fact['value'] ?? null,
				'group' => isset( $fact['group'] ) ? sanitize_key( $fact['group'] ) : 'overview',
			);
		}

		$this->repo->set_many( $clean );

		return rest_ensure_response( array( 'ok' => true, 'count' => count( $clean ) ) );
	}
}
