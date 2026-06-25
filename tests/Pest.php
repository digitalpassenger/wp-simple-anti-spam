<?php
/**
 * Pest test bootstrap and WordPress stubs.
 *
 * @package WPSimpleAntiSpam
 */

declare( strict_types=1 );

require_once __DIR__ . '/../vendor/autoload.php';

/*
|--------------------------------------------------------------------------
| WordPress stubs
|--------------------------------------------------------------------------
*/

$GLOBALS['wp_filters'] = array();

if ( ! function_exists( 'add_filter' ) ) {
	function add_filter( string $tag, callable $callback, int $priority = 10, int $accepted_args = 1 ): void {
		$GLOBALS['wp_filters'][ $tag ][] = $callback;
	}
}

if ( ! function_exists( 'remove_all_filters' ) ) {
	function remove_all_filters( string $tag ): void {
		unset( $GLOBALS['wp_filters'][ $tag ] );
	}
}

if ( ! function_exists( 'apply_filters' ) ) {
	function apply_filters( $tag, $value, ...$args ) {
		foreach ( $GLOBALS['wp_filters'][ $tag ] ?? array() as $callback ) {
			$value = $callback( $value, ...$args );
		}

		return $value;
	}
}

if ( ! function_exists( 'get_site_url' ) ) {
	function get_site_url() {
		return 'https://example.test';
	}
}

if ( ! function_exists( 'wp_unslash' ) ) {
	function wp_unslash( $value ) {
		return $value;
	}
}

if ( ! function_exists( 'wp_parse_url' ) ) {
	function wp_parse_url( $url, $component = -1 ) {
		return parse_url( (string) $url, $component );
	}
}

if ( ! function_exists( 'wp_extract_urls' ) ) {
	function wp_extract_urls( $content ) {
		preg_match_all( '#https?://[^\s<>"\']+#i', (string) $content, $matches );
		return array_values( array_unique( $matches[0] ?? array() ) );
	}
}

if ( ! function_exists( 'wp_strip_all_tags' ) ) {
	function wp_strip_all_tags( $text ) {
		return strip_tags( (string) $text );
	}
}

if ( ! function_exists( 'ray' ) ) {
	function ray( ...$args ) {
		return new class() {
			public function __call( string $name, array $arguments ): self {
				return $this;
			}
		};
	}
}

function mock_wpdb( mixed $get_var_result = null ): void {
	global $wpdb;

	$wpdb = new class( $get_var_result ) {
		public string $comments = 'wp_comments';

		public function __construct( private mixed $get_var_result ) {}

		public function get_var( $query ) {
			return $this->get_var_result;
		}

		public function prepare( $query, ...$args ) {
			return $query;
		}
	};
}

uses()
	->beforeEach(
		function () {
			$GLOBALS['wp_filters'] = array();
			mock_wpdb();
		}
	)
	->in( 'CheckTest.php' );
