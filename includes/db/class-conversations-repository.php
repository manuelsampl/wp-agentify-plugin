<?php
/**
 * Repository for conversations and messages (persisted in the WP DB).
 *
 * @package WPAgentify
 */

namespace WPAgentify\DB;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * CRUD for wpagf_conversations and wpagf_messages.
 */
class Conversations_Repository {

	/**
	 * Conversations table.
	 *
	 * @return string
	 */
	private function conversations() {
		global $wpdb;
		return $wpdb->prefix . 'wpagf_conversations';
	}

	/**
	 * Messages table.
	 *
	 * @return string
	 */
	private function messages() {
		global $wpdb;
		return $wpdb->prefix . 'wpagf_messages';
	}

	/**
	 * List conversations for a user, paginated, with optional search.
	 *
	 * @param int    $user_id WP user id.
	 * @param string $search  Search term over title + message content.
	 * @param int    $page    1-based page.
	 * @param int    $per     Page size.
	 * @return array { items: array, total: int }
	 */
	public function list( $user_id, $search = '', $page = 1, $per = 20 ) {
		global $wpdb;
		$c      = $this->conversations();
		$m      = $this->messages();
		$offset = max( 0, ( $page - 1 ) * $per );

		if ( '' !== $search ) {
			$like  = '%' . $wpdb->esc_like( $search ) . '%';
			$where = $wpdb->prepare(
				"WHERE c.wp_user_id = %d AND ( c.title LIKE %s OR EXISTS (
					SELECT 1 FROM {$m} m WHERE m.conversation_id = c.id AND m.content LIKE %s
				) )",
				$user_id,
				$like,
				$like
			);
		} else {
			$where = $wpdb->prepare( 'WHERE c.wp_user_id = %d', $user_id );
		}

		$total = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$c} c {$where}" );
		$items = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT c.* FROM {$c} c {$where} ORDER BY c.updated_at DESC LIMIT %d OFFSET %d",
				$per,
				$offset
			),
			ARRAY_A
		);

		return array(
			'items' => $items ? $items : array(),
			'total' => $total,
		);
	}

	/**
	 * Create a conversation.
	 *
	 * @param int    $user_id WP user id.
	 * @param string $title   Title.
	 * @param string $model   Model id.
	 * @return string Conversation uuid.
	 */
	public function create( $user_id, $title, $model ) {
		global $wpdb;
		$id  = wp_generate_uuid4();
		$now = current_time( 'mysql' );

		$wpdb->insert(
			$this->conversations(),
			array(
				'id'         => $id,
				'wp_user_id' => $user_id,
				'title'      => $title,
				'model'      => $model,
				'created_at' => $now,
				'updated_at' => $now,
			)
		);

		return $id;
	}

	/**
	 * Get a conversation with its messages.
	 *
	 * @param string $id      Conversation id.
	 * @param int    $user_id Owner check.
	 * @return array|null
	 */
	public function get( $id, $user_id ) {
		global $wpdb;
		$conversation = $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM {$this->conversations()} WHERE id = %s AND wp_user_id = %d", $id, $user_id ),
			ARRAY_A
		);

		if ( ! $conversation ) {
			return null;
		}

		$conversation['messages'] = $wpdb->get_results(
			$wpdb->prepare( "SELECT * FROM {$this->messages()} WHERE conversation_id = %s ORDER BY id ASC", $id ),
			ARRAY_A
		);

		return $conversation;
	}

	/**
	 * Rename a conversation.
	 *
	 * @param string $id      Conversation id.
	 * @param int    $user_id Owner check.
	 * @param string $title   New title.
	 * @return void
	 */
	public function rename( $id, $user_id, $title ) {
		global $wpdb;
		$wpdb->update(
			$this->conversations(),
			array( 'title' => $title, 'updated_at' => current_time( 'mysql' ) ),
			array( 'id' => $id, 'wp_user_id' => $user_id )
		);
	}

	/**
	 * Delete a conversation and its messages.
	 *
	 * @param string $id      Conversation id.
	 * @param int    $user_id Owner check.
	 * @return void
	 */
	public function delete( $id, $user_id ) {
		global $wpdb;
		$owned = $wpdb->get_var(
			$wpdb->prepare( "SELECT id FROM {$this->conversations()} WHERE id = %s AND wp_user_id = %d", $id, $user_id )
		);
		if ( ! $owned ) {
			return;
		}
		$wpdb->delete( $this->messages(), array( 'conversation_id' => $id ) );
		$wpdb->delete( $this->conversations(), array( 'id' => $id ) );
	}

	/**
	 * Append a message to a conversation.
	 *
	 * @param string $conversation_id Conversation id.
	 * @param array  $data            Message fields.
	 * @return int Inserted message id.
	 */
	public function add_message( $conversation_id, array $data ) {
		global $wpdb;

		$wpdb->insert(
			$this->messages(),
			array(
				'conversation_id'  => $conversation_id,
				'role'             => $data['role'] ?? 'user',
				'content'          => $data['content'] ?? '',
				'steps_json'       => isset( $data['steps'] ) ? wp_json_encode( $data['steps'] ) : null,
				'attachments_json' => isset( $data['attachments'] ) ? wp_json_encode( $data['attachments'] ) : null,
				'usage_json'       => isset( $data['usage'] ) ? wp_json_encode( $data['usage'] ) : null,
				'created_at'       => current_time( 'mysql' ),
			)
		);

		$wpdb->update(
			$this->conversations(),
			array( 'updated_at' => current_time( 'mysql' ) ),
			array( 'id' => $conversation_id )
		);

		return (int) $wpdb->insert_id;
	}
}
