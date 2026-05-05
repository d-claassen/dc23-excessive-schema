<?php
/**
 * Integration that provides the registration moment for main entities.
 *
 * @package DC23\ExcessiveSchema
 */

declare( strict_types=1 );

namespace DC23\ExcessiveSchema\Integrations;

/**
 * Fires `dc23_schema_register_main_entities` on init priority 20.
 *
 * Priority 20 is late enough that source plugins have registered their post
 * types (which happens at init priority 10 by convention) and Yoast's
 * indexable repository is available, but early enough that schema rendering
 * (on wp_head) hasn't started.
 */
final class Main_Entity_Registration {

	public function register(): void {
		add_action( 'init', array( $this, 'fire_registration_action' ), 20 );
	}

	public function fire_registration_action(): void {
		/**
		 * Fires when main entities should register themselves.
		 *
		 * Consumers hook this action and call dc23_schema_register_main_entity()
		 * for each post type they handle.
		 */
		do_action( 'dc23_schema_register_main_entities' );
	}
}
