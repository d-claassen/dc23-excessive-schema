<?php
/**
 * Plugin Name:       DC23 Excessive Schema
 * Description:       Template-level schema enrichment: connects WebPage to Query Loop sections via ItemList nodes.
 * Requires at least: 6.6
 * Requires PHP:      8.2
 * Requires Plugins:  wordpress-seo
 * Version:           0.3.2
 * Author:            Dennis Claassen
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       dc23-excessive-schema
 * GitHub Plugin URI: https://github.com/d-claassen/dc23-excessive-schema
 * Primary Branch:    main
 * Release Asset:     true
 *
 * @package DC23
 */

declare( strict_types=1 );

namespace DC23\ExcessiveSchema;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Require Composer autoloader.
if ( file_exists( __DIR__ . '/vendor/autoload.php' ) ) {
	require_once __DIR__ . '/vendor/autoload.php';
}

/**
 * Initialize the plugin.
 *
 * @return void
 */
function init(): void {
	// Check if Yoast SEO is active.
	if ( ! function_exists( 'YoastSEO' ) ) {
		add_action( 'admin_notices', __NAMESPACE__ . '\\display_yoast_dependency_notice' );
		return;
	}
	
	( new \DC23\ExcessiveSchema\Integrations\Blog() )->register();
	( new \DC23\ExcessiveSchema\Integrations\Article_Mentions() )->register();
	( new \DC23\ExcessiveSchema\Integrations\ItemList() )->register();
	( new \DC23\ExcessiveSchema\Integrations\ReadingTime() )->register();
}

add_action( 'plugins_loaded', __NAMESPACE__ . '\\init' );

/**
 * Display admin notice when Yoast SEO is not active.
 *
 * @return void
 */
function display_yoast_dependency_notice(): void {
	?>
	<div class="notice notice-error">
		<p>
			<?php
			printf(
				/* translators: %s: Plugin name */
				esc_html__( '%s requires Yoast SEO to be installed and activated.', 'dc23-excessive-schema' ),
				'<strong>DC23 Excessive Schema</strong>'
			);
			?>
		</p>
	</div>
	<?php
}
