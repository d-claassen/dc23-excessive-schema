<?php
/**
 * PHPUnit bootstrap file for DC23 Excessive Schema plugin tests.
 *
 * @package DC23\ExcessiveSchema
 */

namespace PHPUnit\Framework {
	class Warning {}
	class TestListener {}
}
namespace PHPUnit\Framework\Error {
	class Deprecated {}
	class Notice {}
	class Warning {}
}

namespace DC23\ExcessiveSchema {

	/**
	 * Retrieves the path to the WordPress `tests/phpunit/` directory.
	 *
	 * @return string|false Path to the WP `tests/phpunit/` directory.
	 */
	function get_path_to_wp_test_dir() {
		$normalize_path = static function ( $path ) {
			return \str_replace( '\\', '/', $path );
		};

		if ( \getenv( 'WP_TESTS_DIR' ) !== false ) {
			$tests_dir = \getenv( 'WP_TESTS_DIR' );
			$tests_dir = \realpath( $tests_dir );
			if ( $tests_dir !== false ) {
				$tests_dir = $normalize_path( $tests_dir ) . '/';
				if ( \is_dir( $tests_dir ) === true
				     && @\file_exists( $tests_dir . 'includes/bootstrap.php' )
				) {
					return $tests_dir;
				}
			}

			unset( $tests_dir );
		}

		if ( \getenv( 'WP_DEVELOP_DIR' ) !== false ) {
			$dev_dir = \getenv( 'WP_DEVELOP_DIR' );
			$dev_dir = \realpath( $dev_dir );
			if ( $dev_dir !== false ) {
				$dev_dir = $normalize_path( $dev_dir ) . '/';
				if ( \is_dir( $dev_dir ) === true
				     && @\file_exists( $dev_dir . 'tests/phpunit/includes/bootstrap.php' )
				) {
					return $dev_dir . 'tests/phpunit/';
				}
			}

			unset( $dev_dir );
		}

		if ( @\file_exists( __DIR__ . '/../../../../../../../../../tests/phpunit/includes/bootstrap.php' ) ) {
			$tests_dir = __DIR__ . '/../../../../../../../../../tests/phpunit/';
			$tests_dir = \realpath( $tests_dir );
			if ( $tests_dir !== false ) {
				return $normalize_path( $tests_dir ) . '/';
			}

			unset( $tests_dir );
		}

		$tests_dir = \sys_get_temp_dir() . '/wordpress-tests-lib';
		$tests_dir = \realpath( $tests_dir );
		if ( $tests_dir !== false ) {
			$tests_dir = $normalize_path( $tests_dir ) . '/';
			if ( \is_dir( $tests_dir ) === true
			     && @\file_exists( $tests_dir . 'includes/bootstrap.php' )
			) {
				return $tests_dir;
			}
		}

		return false;
	}

	$_tests_dir = get_path_to_wp_test_dir();

	// Give access to tests_add_filter() function.
	require_once $_tests_dir . 'includes/functions.php';

	/**
	 * Manually load the plugin being tested.
	 */
	function _manually_load_plugin() {
		require dirname( __DIR__ ) . '/../wordpress-seo/wp-seo.php';
		require dirname( __DIR__ ) . '/dc23-excessive-schema.php';
	}

	// Add plugin to active mu-plugins - to make sure it gets loaded.
	tests_add_filter( 'muplugins_loaded', '\DC23\ExcessiveSchema\_manually_load_plugin' );

	// Make sure the tests never register as being in development mode.
	tests_add_filter( 'yoast_seo_development_mode', '__return_false' );

	/* *****[ Yoast SEO specific configuration ]***** */

	if ( ! defined( 'YOAST_ENVIRONMENT' ) ) {
		define( 'YOAST_ENVIRONMENT', 'test' );
	}

	if ( ! defined( 'YOAST_SEO_INDEXABLES' ) ) {
		define( 'YOAST_SEO_INDEXABLES', true );
	}

	if ( defined( 'WPSEO_TESTS_PATH' ) && WPSEO_TESTS_PATH !== __DIR__ . '/' ) {
		echo 'WPSEO_TESTS_PATH is already defined and does not match expected path.';
		exit( 1 );
	}
	define( 'WPSEO_TESTS_PATH', __DIR__ . '/' );

	$wp_test_path = get_path_to_wp_test_dir();

	if ( $wp_test_path !== false ) {
		require_once $wp_test_path . 'includes/bootstrap.php';

		return;
	}

	echo \PHP_EOL, 'ERROR: The WordPress native unit test bootstrap file could not be found. Please set either the WP_TESTS_DIR or the WP_DEVELOP_DIR environment variable, either in your OS or in a custom phpunit.xml file.', \PHP_EOL;
	exit( 1 );
}
