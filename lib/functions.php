<?php
/**
 * Procedural API for the main entity registry.
 *
 * @package DC23\ExcessiveSchema
 */

declare( strict_types=1 );

namespace DC23\ExcessiveSchema;

use DC23\ExcessiveSchema\Adapters\Configured_Main_Entity;
use DC23\ExcessiveSchema\Adapters\Main_Entity;
use DC23\ExcessiveSchema\Registries\Main_Entity_Registry;

/**
 * Register a main entity for a post type.
 *
 * Accepts either a Main_Entity instance or a config array (which is wrapped
 * in Configured_Main_Entity). After successful registration, the main
 * entity's setup_main_entity_enrichment() method is called automatically.
 *
 * @param string                          $post_type   The post type to register for.
 * @param Main_Entity|array<string, mixed> $main_entity Main entity instance or config array.
 *
 * @throws \InvalidArgumentException When config is malformed or post type is empty.
 * @throws Registries\Main_Entity_Already_Registered When the post type is already registered.
 */
function dc23_schema_register_main_entity( string $post_type, $main_entity ): void {
	if ( is_array( $main_entity ) ) {
		$main_entity = new Configured_Main_Entity( $main_entity );
	}

	if ( ! $main_entity instanceof Main_Entity ) {
		throw new \InvalidArgumentException(
			'Main entity must be a Main_Entity instance or a config array.'
		);
	}

	Main_Entity_Registry::register( $post_type, $main_entity );
	$main_entity->setup_main_entity_enrichment();

	/**
	 * Fires after a main entity has been registered and set up.
	 *
	 * @param string      $post_type   The post type the main entity was registered for.
	 * @param Main_Entity $main_entity The registered main entity.
	 */
	do_action( 'dc23_schema_registered_main_entity', $post_type, $main_entity );
}

/**
 * Get the main entity registered for a post type, or null if none.
 */
function dc23_schema_get_main_entity( string $post_type ): ?Main_Entity {
	return Main_Entity_Registry::get( $post_type );
}

/**
 * Get all registered main entities keyed by post type.
 *
 * @return array<string, Main_Entity>
 */
function dc23_schema_get_main_entities(): array {
	return Main_Entity_Registry::get_all();
}

/**
 * Whether a post type has a registered main entity.
 */
function dc23_schema_main_entity_exists( string $post_type ): bool {
	return Main_Entity_Registry::exists( $post_type );
}
