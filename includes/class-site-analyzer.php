<?php
/**
 * Analyzes the WordPress site and stores structured facts for the agent.
 *
 * @package WPAgentify
 */

namespace WPAgentify;

use WPAgentify\DB\Facts_Repository;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Gathers counts, content structure, multilingual setup, site type and plugin
 * stack, then persists everything into wpagf_site_facts.
 */
class Site_Analyzer {

	/**
	 * Known plugin signatures grouped by capability. Key = path fragment or
	 * function/class probe; value = human label.
	 *
	 * @var array
	 */
	private $signatures = array(
		'seo' => array(
			'all-in-one-seo-pack/all_in_one_seo_pack.php' => 'All in One SEO',
			'wordpress-seo/wp-seo.php'                     => 'Yoast SEO',
			'seo-by-rank-math/rank-math.php'               => 'Rank Math',
			'wp-seopress/seopress.php'                     => 'SEOPress',
			'autodescription/autodescription.php'          => 'The SEO Framework',
			'squirrly-seo/squirrly.php'                     => 'Squirrly SEO',
			'slim-seo/slim-seo.php'                         => 'Slim SEO',
		),
		'i18n' => array(
			'sitepress-multilingual-cms/sitepress.php' => 'WPML',
			'polylang/polylang.php'                    => 'Polylang',
			'polylang-pro/polylang.php'                => 'Polylang Pro',
			'translatepress-multilingual/index.php'    => 'TranslatePress',
			'weglot/weglot.php'                        => 'Weglot',
			'gtranslate/gtranslate.php'                => 'GTranslate',
			'loco-translate/loco.php'                  => 'Loco Translate',
		),
		'forms' => array(
			'contact-form-7/wp-contact-form-7.php' => 'Contact Form 7',
			'wpforms-lite/wpforms.php'             => 'WPForms',
			'wpforms/wpforms.php'                  => 'WPForms',
			'gravityforms/gravityforms.php'        => 'Gravity Forms',
			'fluentform/fluentform.php'            => 'Fluent Forms',
			'forminator/forminator.php'            => 'Forminator',
		),
		'shop' => array(
			'woocommerce/woocommerce.php'                      => 'WooCommerce',
			'easy-digital-downloads/easy-digital-downloads.php'=> 'Easy Digital Downloads',
		),
		'builder' => array(
			'advanced-custom-fields/acf.php'     => 'ACF',
			'advanced-custom-fields-pro/acf.php' => 'ACF Pro',
			'elementor/elementor.php'            => 'Elementor',
			'beaver-builder-lite-version/fl-builder.php' => 'Beaver Builder',
			'wpbakery/js_composer.php'           => 'WPBakery',
		),
	);

	/**
	 * Run the analysis and persist the facts.
	 *
	 * @return array The collected facts.
	 */
	public function analyze_and_store() {
		$facts = $this->collect();

		$repo    = new Facts_Repository();
		$payload = array();
		foreach ( $facts as $group => $values ) {
			foreach ( $values as $key => $value ) {
				$payload[] = array(
					'key'   => $group . '.' . $key,
					'value' => $value,
					'group' => $group,
				);
			}
		}
		$repo->set_many( $payload );

		update_option( 'wpagentify_last_analysis', time() );

		return $facts;
	}

	/**
	 * Collect all facts.
	 *
	 * @return array
	 */
	public function collect() {
		return array(
			'overview' => $this->overview(),
			'content'  => $this->content(),
			'i18n'     => $this->i18n(),
			'plugins'  => $this->plugins(),
			'theme'    => $this->theme(),
		);
	}

	/**
	 * Site-level overview facts.
	 *
	 * @return array
	 */
	private function overview() {
		return array(
			'site_name' => get_bloginfo( 'name' ),
			'tagline'   => get_bloginfo( 'description' ),
			'url'       => home_url(),
			'admin_email' => get_bloginfo( 'admin_email' ),
			'language'  => get_locale(),
			'wp_version'=> get_bloginfo( 'version' ),
			'site_type' => $this->detect_site_type(),
		);
	}

	/**
	 * Content counts and structure.
	 *
	 * @return array
	 */
	private function content() {
		$cpts = array();
		foreach ( get_post_types( array( 'public' => true, '_builtin' => false ), 'objects' ) as $cpt ) {
			$cpts[] = array(
				'name'  => $cpt->name,
				'label' => $cpt->label,
				'count' => (int) wp_count_posts( $cpt->name )->publish,
			);
		}

		return array(
			'pages_count'        => (int) wp_count_posts( 'page' )->publish,
			'posts_count'        => (int) wp_count_posts( 'post' )->publish,
			'media_count'        => (int) wp_count_posts( 'attachment' )->inherit,
			'categories_count'   => (int) wp_count_terms( array( 'taxonomy' => 'category', 'hide_empty' => false ) ),
			'tags_count'         => (int) wp_count_terms( array( 'taxonomy' => 'post_tag', 'hide_empty' => false ) ),
			'custom_post_types'  => $cpts,
			'has_sitemap'        => $this->has_sitemap(),
		);
	}

	/**
	 * Multilingual facts.
	 *
	 * @return array
	 */
	private function i18n() {
		$plugins = $this->detect( 'i18n' );
		return array(
			'plugins'      => $plugins,
			'is_multilingual' => ! empty( $plugins ),
			'default_locale'  => get_locale(),
		);
	}

	/**
	 * Detected plugin stack by capability.
	 *
	 * @return array
	 */
	private function plugins() {
		return array(
			'seo'     => $this->detect( 'seo' ),
			'i18n'    => $this->detect( 'i18n' ),
			'forms'   => $this->detect( 'forms' ),
			'shop'    => $this->detect( 'shop' ),
			'builder' => $this->detect( 'builder' ),
			'active_count' => count( (array) get_option( 'active_plugins', array() ) ),
		);
	}

	/**
	 * Theme facts.
	 *
	 * @return array
	 */
	private function theme() {
		$theme = wp_get_theme();
		return array(
			'name'      => $theme->get( 'Name' ),
			'version'   => $theme->get( 'Version' ),
			'template'  => get_template(),
			'is_block_theme' => function_exists( 'wp_is_block_theme' ) ? wp_is_block_theme() : false,
		);
	}

	/**
	 * Detect installed plugins for a capability group.
	 *
	 * @param string $group Group key.
	 * @return array List of human labels.
	 */
	private function detect( $group ) {
		if ( ! function_exists( 'is_plugin_active' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}
		$found = array();
		foreach ( $this->signatures[ $group ] as $path => $label ) {
			if ( is_plugin_active( $path ) ) {
				$found[] = $label;
			}
		}
		return array_values( array_unique( $found ) );
	}

	/**
	 * Rough site-type heuristic.
	 *
	 * @return string shop|blog|website
	 */
	private function detect_site_type() {
		if ( ! empty( $this->detect( 'shop' ) ) ) {
			return 'shop';
		}
		$posts = (int) wp_count_posts( 'post' )->publish;
		$pages = (int) wp_count_posts( 'page' )->publish;
		if ( $posts > $pages && $posts > 5 ) {
			return 'blog';
		}
		return 'website';
	}

	/**
	 * Whether a sitemap is available.
	 *
	 * @return bool
	 */
	private function has_sitemap() {
		$response = wp_remote_head( home_url( '/sitemap.xml' ), array( 'timeout' => 5 ) );
		if ( is_wp_error( $response ) ) {
			return function_exists( 'wp_sitemaps_get_server' );
		}
		return 200 === (int) wp_remote_retrieve_response_code( $response );
	}
}
