<?php
/**
 * Exception thrown when registering a main entity for an already-registered post type.
 *
 * @package DC23\ExcessiveSchema
 */

declare( strict_types=1 );

namespace DC23\ExcessiveSchema\Registries\Exceptions;

use RuntimeException;

/**
 * Thrown when two registrations attempt to claim the same post type.
 *
 * Catchable by consumers that want graceful handling, but the default
 * behaviour is to surface the conflict rather than silently overwrite.
 */
final class Main_Entity_Already_Registered extends RuntimeException {

	public static function for_post_type( string $post_type ): self {
		return new self(
			sprintf(
				'A main entity is already registered for post type "%s".',
				$post_type
			)
		);
	}
}
