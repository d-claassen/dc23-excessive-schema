<?php
/**
 * Storage container for registered main entities.
 *
 * @package DC23\ExcessiveSchema
 */

declare( strict_types=1 );

namespace DC23\ExcessiveSchema\Registries;

use DC23\ExcessiveSchema\Adapters\Main_Entity;
use InvalidArgumentException;

/**
 * Static container for main entities keyed by post type.
 *
 * This is passive storage — it does not hook WordPress, fire actions, or
 * call setup methods. The procedural API (functions.php) and the
 * Main_Entity_Registration integration coordinate those concerns.
 */
final class Main_Entity_Registry {

	/**
	 * @var array<string, Main_Entity>
	 */
	private static array $entities = array();

	/**
	 * Register a main entity for a post type.
	 *
	 * @throws InvalidArgumentException When the post type is empty.
	 * @throws Main_Entity_Already_Registered When the post type already has a main entity.
	 */
	public static function register( string $post_type, Main_Entity $main_entity ): void {
		if ( $post_type === '' ) {
			throw new InvalidArgumentException( 'Cannot register a main entity for an empty post type.' );
		}
		if ( isset( self::$entities[ $post_type ] ) ) {
			throw Main_Entity_Already_Registered::for_post_type( $post_type );
		}

		self::$entities[ $post_type ] = $main_entity;
	}

	/**
	 * Get the main entity registered for a post type, or null if none.
	 */
	public static function get( string $post_type ): ?Main_Entity {
		return self::$entities[ $post_type ] ?? null;
	}

	/**
	 * Get all registered main entities keyed by post type.
	 *
	 * @return array<string, Main_Entity>
	 */
	public static function get_all(): array {
		return self::$entities;
	}

	/**
	 * Whether a post type has a registered main entity.
	 */
	public static function exists( string $post_type ): bool {
		return isset( self::$entities[ $post_type ] );
	}

	/**
	 * Reset the registry. Intended for tests.
	 */
	public static function reset(): void {
		self::$entities = array();
	}
}
