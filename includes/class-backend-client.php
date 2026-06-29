<?php
/**
 * HTTP client for the WP-AGENTIFY backend (Laravel on Hostinger).
 *
 * @package WPAgentify
 */

namespace WPAgentify;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Thin wrapper around wp_remote_* for backend calls. Authenticates with the
 * stored site token. Never exposes provider/Stripe keys to the browser.
 */
class Backend_Client {

	/**
	 * Backend base URL.
	 *
	 * @var string
	 */
	private $base;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->base = untrailingslashit( WPAGENTIFY_BACKEND_URL );
	}

	/**
	 * Stored site application token.
	 *
	 * @return string
	 */
	public function token() {
		return (string) get_option( 'wpagentify_site_token', '' );
	}

	/**
	 * Perform a request to the backend.
	 *
	 * @param string $method HTTP method.
	 * @param string $path   Path beginning with /api.
	 * @param array  $body   Optional JSON body.
	 * @return array|\WP_Error Decoded response or error.
	 */
	public function request( $method, $path, $body = array() ) {
		$args = array(
			'method'  => strtoupper( $method ),
			'timeout' => 30,
			'headers' => array(
				'Accept'        => 'application/json',
				'Content-Type'  => 'application/json',
				'Authorization' => 'Bearer ' . $this->token(),
				'X-Agentify-Site' => home_url(),
			),
		);

		if ( ! empty( $body ) ) {
			$args['body'] = wp_json_encode( $body );
		}

		$response = wp_remote_request( $this->base . $path, $args );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code = wp_remote_retrieve_response_code( $response );
		$data = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( $code >= 400 ) {
			return new \WP_Error(
				'wpagentify_backend_error',
				is_array( $data ) && isset( $data['message'] ) ? $data['message'] : 'Backend error',
				array( 'status' => $code, 'data' => $data )
			);
		}

		return is_array( $data ) ? $data : array();
	}
}
