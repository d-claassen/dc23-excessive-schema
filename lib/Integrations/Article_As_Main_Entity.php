<?php
/**
 * Integration that registers the built-in Article main entity.
 *
 * @package DC23\ExcessiveSchema
 */

declare( strict_types=1 );

namespace DC23\ExcessiveSchema\Integrations;

use DC23\ExcessiveSchema\Adapters\Article_Main_Entity;

use function DC23\ExcessiveSchema\dc23_schema_register_main_entity;

/**
 * Registers the Article main entity against the post types Yoast considers articles.
 *
 * @TODO Yoast applies Article schema to a configurable set of post types; this
 * integration registers the same Article instance against each of them so
 * mentions resolution and other enrichment can find a registered main entity
 * regardless of which "article" post type is being rendered.
 */
final class Article_As_Main_Entity {

	public function register(): void {
		add_action( 'dc23_schema_register_main_entities', array( $this, 'register_main_entity' ) );
	}

	public function register_main_entity(): void {
		$post_types = [ 'post' ];

		$main_entity = new Article_Main_Entity();

		foreach ( $post_types as $post_type ) {
			dc23_schema_register_main_entity( $post_type, $main_entity );
		}
	}
}
