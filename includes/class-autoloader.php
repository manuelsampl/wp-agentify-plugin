<?php
/**
 * PSR-4-ish autoloader for the WPAgentify namespace.
 *
 * @package WPAgentify
 */

namespace WPAgentify;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Maps WPAgentify\Foo\Bar to includes/foo/class-bar.php (WordPress file naming).
 */
class Autoloader {

	/**
	 * Register the autoloader.
	 *
	 * @return void
	 */
	public static function register() {
		spl_autoload_register( array( __CLASS__, 'autoload' ) );
	}

	/**
	 * Resolve and require a class file.
	 *
	 * @param string $class Fully qualified class name.
	 * @return void
	 */
	public static function autoload( $class ) {
		$prefix = __NAMESPACE__ . '\\';
		if ( 0 !== strpos( $class, $prefix ) ) {
			return;
		}

		$relative = substr( $class, strlen( $prefix ) );
		$parts    = explode( '\\', $relative );
		$class_nm = array_pop( $parts );

		$path = WPAGENTIFY_DIR . 'includes/';
		foreach ( $parts as $segment ) {
			$path .= strtolower( str_replace( '_', '-', $segment ) ) . '/';
		}

		$file = $path . 'class-' . strtolower( str_replace( '_', '-', $class_nm ) ) . '.php';

		if ( is_readable( $file ) ) {
			require_once $file;
		}
	}
}
