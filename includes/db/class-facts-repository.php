<?php
/**
 * Repository for the structured site facts the agent reads.
 *
 * @package WPAgentify
 */

namespace WPAgentify\DB;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * CRUD for wpagf_site_facts.
 */
class Facts_Repository {

	/**
	 * Table name.
	 *
	 * @return string
	 */
	private function table() {
		global $wpdb;
		return $wpdb->prefix . 'wpagf_site_facts';
	}

	/**
	 * Get all facts, optionally filtered by group.
	 *
	 * @param string|null $group Optional group filter.
	 * @return array map of fact_key => decoded value.
	 */
	public function all( $group = null ) {
		global $wpdb;
		$table = $this->table();

		if ( $group ) {
			$rows = $wpdb->get_results(
				$wpdb->prepare( "SELECT fact_key, fact_value FROM {$table} WHERE fact_group = %s", $group ),
				ARRAY_A
			);
		} else {
			$rows = $wpdb->get_results( "SELECT fact_key, fact_value FROM {$table}", ARRAY_A );
		}

		$out = array();
		foreach ( (array) $rows as $row ) {
			$out[ $row['fact_key'] ] = json_decode( $row['fact_value'], true );
		}
		return $out;
	}

	/**
	 * Upsert a single fact.
	 *
	 * @param string $key   Fact key.
	 * @param mixed  $value Value (JSON-encoded).
	 * @param string $group Group bucket.
	 * @return void
	 */
	public function set( $key, $value, $group = 'overview' ) {
		global $wpdb;
		$table = $this->table();

		$wpdb->query(
			$wpdb->prepare(
				"INSERT INTO {$table} (fact_key, fact_group, fact_value, updated_at)
				 VALUES (%s, %s, %s, %s)
				 ON DUPLICATE KEY UPDATE fact_group = VALUES(fact_group), fact_value = VALUES(fact_value), updated_at = VALUES(updated_at)",
				$key,
				$group,
				wp_json_encode( $value ),
				current_time( 'mysql' )
			)
		);
	}

	/**
	 * Bulk upsert.
	 *
	 * @param array $facts List of [key, value, group].
	 * @return void
	 */
	public function set_many( array $facts ) {
		foreach ( $facts as $fact ) {
			$this->set( $fact['key'], $fact['value'], $fact['group'] ?? 'overview' );
		}
	}
}
