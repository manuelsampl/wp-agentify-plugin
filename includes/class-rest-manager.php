<?php
/**
 * Registers all REST routes under the wp-agentify/v1 namespace.
 *
 * @package WPAgentify
 */

namespace WPAgentify;

use WPAgentify\Rest\Bootstrap_Controller;
use WPAgentify\Rest\Facts_Controller;
use WPAgentify\Rest\Conversations_Controller;
use WPAgentify\Rest\Media_Controller;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Aggregates and registers the plugin's REST controllers.
 */
class Rest_Manager {

	const NAMESPACE = 'wp-agentify/v1';

	/**
	 * Controller instances.
	 *
	 * @var array
	 */
	private $controllers = array();

	/**
	 * Register all routes.
	 *
	 * @return void
	 */
	public function register_routes() {
		$this->controllers = array(
			new Bootstrap_Controller(),
			new Facts_Controller(),
			new Conversations_Controller(),
			new Media_Controller(),
		);

		foreach ( $this->controllers as $controller ) {
			$controller->register_routes();
		}
	}

	/**
	 * Permission callback: logged-in user who can use the plugin.
	 *
	 * @return bool
	 */
	public static function can_use() {
		return is_user_logged_in();
	}

	/**
	 * Permission callback: site administrator.
	 *
	 * @return bool
	 */
	public static function can_manage() {
		return current_user_can( 'manage_options' );
	}
}
