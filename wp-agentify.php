<?php
/**
 * Plugin Name:       WP-AGENTIFY
 * Plugin URI:        https://wp-agentify.com
 * Description:       Automate your WordPress completely with AI. An agentic AI assistant that analyzes your site and can read/write your content according to plan limits and RBAC.
 * Version:           0.1.0
 * Requires at least: 6.2
 * Requires PHP:      8.0
 * Author:            WP-AGENTIFY
 * Author URI:        https://wp-agentify.com
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       wp-agentify
 * Domain Path:       /languages
 *
 * @package WPAgentify
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // No direct access.
}

define( 'WPAGENTIFY_VERSION', '0.1.0' );
define( 'WPAGENTIFY_FILE', __FILE__ );
define( 'WPAGENTIFY_DIR', plugin_dir_path( __FILE__ ) );
define( 'WPAGENTIFY_URL', plugin_dir_url( __FILE__ ) );
define( 'WPAGENTIFY_BASENAME', plugin_basename( __FILE__ ) );

// Default backend API base. Override with the WPAGENTIFY_BACKEND_URL constant in wp-config.php.
if ( ! defined( 'WPAGENTIFY_BACKEND_URL' ) ) {
	define( 'WPAGENTIFY_BACKEND_URL', 'https://api.wp-agentify.com' );
}

require_once WPAGENTIFY_DIR . 'includes/class-autoloader.php';
\WPAgentify\Autoloader::register();

register_activation_hook( __FILE__, array( '\WPAgentify\Activator', 'activate' ) );
register_deactivation_hook( __FILE__, array( '\WPAgentify\Deactivator', 'deactivate' ) );

/**
 * Boot the plugin.
 */
function wpagentify() {
	return \WPAgentify\Plugin::instance();
}

add_action( 'plugins_loaded', 'wpagentify' );
