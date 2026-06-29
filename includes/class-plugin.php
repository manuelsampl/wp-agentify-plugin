<?php
/**
 * Main plugin bootstrap: admin menu, asset enqueue, REST registration.
 *
 * @package WPAgentify
 */

namespace WPAgentify;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Singleton plugin container.
 */
class Plugin {

	/**
	 * Singleton instance.
	 *
	 * @var Plugin|null
	 */
	private static $instance = null;

	/**
	 * REST API manager.
	 *
	 * @var Rest_Manager
	 */
	public $rest;

	/**
	 * Backend HTTP client.
	 *
	 * @var Backend_Client
	 */
	public $backend;

	/**
	 * Get/boot the singleton.
	 *
	 * @return Plugin
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
			self::$instance->boot();
		}
		return self::$instance;
	}

	/**
	 * Wire up hooks.
	 *
	 * @return void
	 */
	private function boot() {
		$this->backend = new Backend_Client();
		$this->rest    = new Rest_Manager();

		add_action( 'init', array( $this, 'load_textdomain' ) );
		add_action( 'admin_menu', array( $this, 'register_menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin' ) );
		add_action( 'rest_api_init', array( $this->rest, 'register_routes' ) );
		add_action( 'plugins_loaded', array( $this, 'maybe_upgrade_db' ) );
	}

	/**
	 * Load translations.
	 *
	 * @return void
	 */
	public function load_textdomain() {
		load_plugin_textdomain( 'wp-agentify', false, dirname( WPAGENTIFY_BASENAME ) . '/languages' );
	}

	/**
	 * Run table upgrades if the stored version is behind.
	 *
	 * @return void
	 */
	public function maybe_upgrade_db() {
		if ( get_option( 'wpagentify_db_version' ) !== WPAGENTIFY_VERSION ) {
			Activator::create_tables();
			update_option( 'wpagentify_db_version', WPAGENTIFY_VERSION );
		}
	}

	/**
	 * Register the top-level admin menu that hosts the React app.
	 *
	 * @return void
	 */
	public function register_menu() {
		$cap = 'manage_options';

		add_menu_page(
			__( 'WP-AGENTIFY', 'wp-agentify' ),
			__( 'WP-AGENTIFY', 'wp-agentify' ),
			$cap,
			'wp-agentify',
			array( $this, 'render_app' ),
			'dashicons-superhero',
			3
		);
	}

	/**
	 * Render the React mount point.
	 *
	 * @return void
	 */
	public function render_app() {
		echo '<div id="wpagentify-root" class="wpagentify-root"></div>';
	}

	/**
	 * Enqueue the built React bundle on the plugin admin page only.
	 *
	 * @param string $hook Current admin page hook.
	 * @return void
	 */
	public function enqueue_admin( $hook ) {
		if ( 'toplevel_page_wp-agentify' !== $hook ) {
			return;
		}

		$manifest_path = WPAGENTIFY_DIR . 'assets/app/.vite/manifest.json';
		$base_url      = WPAGENTIFY_URL . 'assets/app/';

		$js_handle = 'wpagentify-app';

		if ( is_readable( $manifest_path ) ) {
			$manifest = json_decode( (string) file_get_contents( $manifest_path ), true );
			$entry    = $manifest['index.html'] ?? reset( $manifest );

			if ( ! empty( $entry['css'] ) ) {
				foreach ( $entry['css'] as $i => $css ) {
					wp_enqueue_style( "wpagentify-app-$i", $base_url . $css, array(), WPAGENTIFY_VERSION );
				}
			}
			if ( ! empty( $entry['file'] ) ) {
				wp_enqueue_script( $js_handle, $base_url . $entry['file'], array(), WPAGENTIFY_VERSION, true );
				wp_script_add_data( $js_handle, 'type', 'module' );
			}
		} else {
			// Dev fallback notice if no build is present.
			wp_register_script( $js_handle, '', array(), WPAGENTIFY_VERSION, true );
			wp_enqueue_script( $js_handle );
		}

		wp_localize_script(
			$js_handle,
			'WPAGENTIFY',
			array(
				'restUrl'     => esc_url_raw( rest_url( 'wp-agentify/v1/' ) ),
				'nonce'       => wp_create_nonce( 'wp_rest' ),
				'backendUrl'  => WPAGENTIFY_BACKEND_URL,
				'adminUrl'    => admin_url(),
				'pluginUrl'   => WPAGENTIFY_URL,
				'isOnboarded' => (bool) get_option( 'wpagentify_onboarded', 0 ),
				'user'        => $this->current_user_payload(),
				'locale'      => get_user_locale(),
			)
		);
	}

	/**
	 * Minimal current-user payload for the SPA.
	 *
	 * @return array
	 */
	private function current_user_payload() {
		$user = wp_get_current_user();
		return array(
			'id'        => $user->ID,
			'name'      => $user->display_name,
			'email'     => $user->user_email,
			'avatar'    => get_avatar_url( $user->ID ),
			'isAdmin'   => current_user_can( 'manage_options' ),
		);
	}
}
