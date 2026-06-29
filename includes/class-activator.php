<?php
/**
 * Plugin activation: create custom tables and seed options.
 *
 * @package WPAgentify
 */

namespace WPAgentify;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles activation tasks.
 */
class Activator {

	/**
	 * Run on activation.
	 *
	 * @return void
	 */
	public static function activate() {
		self::create_tables();
		add_option( 'wpagentify_db_version', WPAGENTIFY_VERSION );
		add_option( 'wpagentify_installed_at', time() );

		if ( ! get_option( 'wpagentify_site_token' ) ) {
			add_option( 'wpagentify_onboarded', 0 );
		}

		flush_rewrite_rules();
	}

	/**
	 * Create the wpagf_* tables.
	 *
	 * @return void
	 */
	public static function create_tables() {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$charset = $wpdb->get_charset_collate();
		$prefix  = $wpdb->prefix . 'wpagf_';

		$facts = "CREATE TABLE {$prefix}site_facts (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			fact_key VARCHAR(191) NOT NULL,
			fact_group VARCHAR(64) NOT NULL DEFAULT 'overview',
			fact_value LONGTEXT NULL,
			updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			UNIQUE KEY fact_key (fact_key),
			KEY fact_group (fact_group)
		) {$charset};";

		$conversations = "CREATE TABLE {$prefix}conversations (
			id CHAR(36) NOT NULL,
			wp_user_id BIGINT UNSIGNED NOT NULL,
			title VARCHAR(255) NOT NULL DEFAULT '',
			model VARCHAR(64) NOT NULL DEFAULT '',
			created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			KEY wp_user_id (wp_user_id),
			KEY updated_at (updated_at)
		) {$charset};";

		$messages = "CREATE TABLE {$prefix}messages (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			conversation_id CHAR(36) NOT NULL,
			role VARCHAR(16) NOT NULL DEFAULT 'user',
			content LONGTEXT NULL,
			steps_json LONGTEXT NULL,
			attachments_json LONGTEXT NULL,
			usage_json LONGTEXT NULL,
			created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			KEY conversation_id (conversation_id),
			KEY created_at (created_at)
		) {$charset};";

		$checkpoints = "CREATE TABLE {$prefix}checkpoints (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			conversation_id CHAR(36) NOT NULL,
			message_id BIGINT UNSIGNED NOT NULL,
			label VARCHAR(255) NOT NULL DEFAULT '',
			snapshot_json LONGTEXT NULL,
			created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			KEY conversation_id (conversation_id)
		) {$charset};";

		dbDelta( $facts );
		dbDelta( $conversations );
		dbDelta( $messages );
		dbDelta( $checkpoints );
	}
}
