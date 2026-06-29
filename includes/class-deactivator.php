<?php
/**
 * Plugin deactivation handler.
 *
 * @package WPAgentify
 */

namespace WPAgentify;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles deactivation tasks. Tables are preserved; removed in uninstall.php.
 */
class Deactivator {

	/**
	 * Run on deactivation.
	 *
	 * @return void
	 */
	public static function deactivate() {
		wp_clear_scheduled_hook( 'wpagentify_cron_tick' );
		flush_rewrite_rules();
	}
}
