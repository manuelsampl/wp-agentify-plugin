<?php
/**
 * Conversations controller: chat persistence in the WP DB.
 *
 * @package WPAgentify
 */

namespace WPAgentify\Rest;

use WPAgentify\Rest_Manager;
use WPAgentify\DB\Conversations_Repository;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Routes under /conversations.
 */
class Conversations_Controller {

	/**
	 * Repository.
	 *
	 * @var Conversations_Repository
	 */
	private $repo;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->repo = new Conversations_Repository();
	}

	/**
	 * Register routes.
	 *
	 * @return void
	 */
	public function register_routes() {
		$ns = Rest_Manager::NAMESPACE;
		$perm = array( Rest_Manager::class, 'can_use' );

		register_rest_route(
			$ns,
			'/conversations',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( $this, 'index' ),
					'permission_callback' => $perm,
				),
				array(
					'methods'             => 'POST',
					'callback'            => array( $this, 'create' ),
					'permission_callback' => $perm,
				),
			)
		);

		register_rest_route(
			$ns,
			'/conversations/(?P<id>[a-f0-9-]{36})',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( $this, 'show' ),
					'permission_callback' => $perm,
				),
				array(
					'methods'             => 'PATCH',
					'callback'            => array( $this, 'update' ),
					'permission_callback' => $perm,
				),
				array(
					'methods'             => 'DELETE',
					'callback'            => array( $this, 'destroy' ),
					'permission_callback' => $perm,
				),
			)
		);

		register_rest_route(
			$ns,
			'/conversations/(?P<id>[a-f0-9-]{36})/messages',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'add_message' ),
				'permission_callback' => $perm,
			)
		);
	}

	/**
	 * List conversations (paginated + search).
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function index( $request ) {
		$result = $this->repo->list(
			get_current_user_id(),
			sanitize_text_field( (string) $request->get_param( 'search' ) ),
			max( 1, (int) $request->get_param( 'page' ) ?: 1 ),
			min( 50, (int) $request->get_param( 'per_page' ) ?: 20 )
		);
		return rest_ensure_response( $result );
	}

	/**
	 * Create a conversation.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function create( $request ) {
		$id = $this->repo->create(
			get_current_user_id(),
			sanitize_text_field( (string) $request->get_param( 'title' ) ),
			sanitize_text_field( (string) $request->get_param( 'model' ) )
		);
		return rest_ensure_response( $this->repo->get( $id, get_current_user_id() ) );
	}

	/**
	 * Show a conversation with messages.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function show( $request ) {
		$conversation = $this->repo->get( (string) $request->get_param( 'id' ), get_current_user_id() );
		if ( ! $conversation ) {
			return new \WP_Error( 'not_found', 'Conversation not found', array( 'status' => 404 ) );
		}
		return rest_ensure_response( $conversation );
	}

	/**
	 * Rename a conversation.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function update( $request ) {
		$this->repo->rename(
			(string) $request->get_param( 'id' ),
			get_current_user_id(),
			sanitize_text_field( (string) $request->get_param( 'title' ) )
		);
		return rest_ensure_response( array( 'ok' => true ) );
	}

	/**
	 * Delete a conversation.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function destroy( $request ) {
		$this->repo->delete( (string) $request->get_param( 'id' ), get_current_user_id() );
		return rest_ensure_response( array( 'ok' => true ) );
	}

	/**
	 * Append a message.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function add_message( $request ) {
		$conversation_id = (string) $request->get_param( 'id' );
		$conversation    = $this->repo->get( $conversation_id, get_current_user_id() );
		if ( ! $conversation ) {
			return new \WP_Error( 'not_found', 'Conversation not found', array( 'status' => 404 ) );
		}

		$message_id = $this->repo->add_message(
			$conversation_id,
			array(
				'role'        => sanitize_text_field( (string) $request->get_param( 'role' ) ),
				'content'     => wp_kses_post( (string) $request->get_param( 'content' ) ),
				'steps'       => $request->get_param( 'steps' ),
				'attachments' => $request->get_param( 'attachments' ),
				'usage'       => $request->get_param( 'usage' ),
			)
		);

		return rest_ensure_response( array( 'id' => $message_id ) );
	}
}
