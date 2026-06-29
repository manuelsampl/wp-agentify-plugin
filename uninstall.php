<?php
/**
 * Uninstall handler: remove plugin tables and options.
 *
 * @package WPAgentify
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

global $wpdb;

$tables = array(
	$wpdb->prefix . 'wpagf_site_facts',
	$wpdb->prefix . 'wpagf_conversations',
	$wpdb->prefix . 'wpagf_messages',
	$wpdb->prefix . 'wpagf_checkpoints',
);

foreach ( $tables as $table ) {
	$wpdb->query( "DROP TABLE IF EXISTS {$table}" ); // phpcs:ignore WordPress.DB.PreparedSQL
}

$options = array(
	'wpagentify_db_version',
	'wpagentify_installed_at',
	'wpagentify_onboarded',
	'wpagentify_site_token',
	'wpagentify_last_analysis',
);

foreach ( $options as $option ) {
	delete_option( $option );
}
