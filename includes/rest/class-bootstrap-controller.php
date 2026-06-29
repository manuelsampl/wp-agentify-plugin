<?php
/**
 * Bootstrap controller: app state, connection, and site analysis trigger.
 *
 * @package WPAgentify
 */

namespace WPAgentify\Rest;

use WPAgentify\Rest_Manager;
use WPAgentify\Site_Analyzer;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Routes: /bootstrap, /connect, /analyze.
 */
class Bootstrap_Controller {

	/**
	 * Register routes.
	 *
	 * @return void
	 */
	public function register_routes() {
		register_rest_route(
			Rest_Manager::NAMESPACE,
			'/bootstrap',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'bootstrap' ),
				'permission_callback' => array( Rest_Manager::class, 'can_use' ),
			)
		);

		register_rest_route(
			Rest_Manager::NAMESPACE,
			'/connect',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'connect' ),
				'permission_callback' => array( Rest_Manager::class, 'can_manage' ),
				'args'                => array(
					'site_token' => array( 'required' => true, 'type' => 'string' ),
				),
			)
		);

		register_rest_route(
			Rest_Manager::NAMESPACE,
			'/analyze',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'analyze' ),
				'permission_callback' => array( Rest_Manager::class, 'can_manage' ),
			)
		);

		register_rest_route(
			Rest_Manager::NAMESPACE,
			'/onboarding/complete',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'onboarding_complete' ),
				'permission_callback' => array( Rest_Manager::class, 'can_manage' ),
			)
		);
	}

	/**
	 * Return current app state for the SPA.
	 *
	 * @return \WP_REST_Response
	 */
	public function bootstrap() {
		$user = wp_get_current_user();

		return rest_ensure_response(
			array(
				'connected'   => '' !== get_option( 'wpagentify_site_token', '' ),
				'onboarded'   => (bool) get_option( 'wpagentify_onboarded', 0 ),
				'site'        => array(
					'title' => get_bloginfo( 'name' ),
					'url'   => home_url(),
					'locale'=> get_locale(),
				),
				'user'        => array(
					'id'      => $user->ID,
					'name'    => $user->display_name,
					'email'   => $user->user_email,
					'avatar'  => get_avatar_url( $user->ID ),
					'isAdmin' => current_user_can( 'manage_options' ),
				),
				'capabilities'=> array(
					'manage' => current_user_can( 'manage_options' ),
				),
			)
		);
	}

	/**
	 * Store the backend-issued site token.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function connect( $request ) {
		$token = sanitize_text_field( (string) $request->get_param( 'site_token' ) );
		update_option( 'wpagentify_site_token', $token, false );

		return rest_ensure_response( array( 'connected' => '' !== $token ) );
	}

	/**
	 * Mark the WordPress side as onboarded (set after the SaaS wizard completes).
	 *
	 * @return \WP_REST_Response
	 */
	public function onboarding_complete() {
		update_option( 'wpagentify_onboarded', 1 );

		return rest_ensure_response( array( 'onboarded' => true ) );
	}

	/**
	 * Run the site analysis and persist facts.
	 *
	 * @return \WP_REST_Response
	 */
	public function analyze() {
		$analyzer = new Site_Analyzer();
		$facts    = $analyzer->analyze_and_store();

		return rest_ensure_response(
			array(
				'ok'    => true,
				'facts' => $facts,
			)
		);
	}
}
