<?php
/**
 * Integration that registers the built-in Article main entity.
 *
 * @package DC23\ExcessiveSchema
 */

declare( strict_types=1 );

namespace DC23\ExcessiveSchema\Integrations;

use DC23\ExcessiveSchema\Adapters\Article_Main_Entity as Article_Main_Entity_Implementation;

use function DC23\ExcessiveSchema\dc23_schema_register_main_entity;

/**
 * Registers the Article main entity against the post types Yoast considers articles.
 *
 * Yoast applies Article schema to a configurable set of post types; this
 * integration registers the same Article instance against each of them so
 * mentions resolution and other enrichment can find a registered main entity
 * regardless of which "article" post type is being rendered.
 *
 * The full set is filterable via `dc23_schema_article_post_types` for
 * consumers that want to opt specific post types in or out.
 */
final class Article_Main_Entity {

	public function register(): void {
		add_action( 'dc23_schema_register_main_entities', array( $this, 'register_main_entity' ) );
	}

	public function register_main_entity(): void {
		/**
		 * Filters the post types the built-in Article main entity is registered for.
		 *
		 * Defaults to 'post'. Consumers can add custom post types that should
		 * be treated as Articles, or remove the default to fully opt out.
		 *
		 * @param string[] $post_types Post types to register the Article main entity for.
		 */
		$post_types = apply_filters( 'dc23_schema_article_post_types', array( 'post' ) );

		if ( ! is_array( $post_types ) ) {
			return;
		}

		$main_entity = new Article_Main_Entity_Implementation();

		foreach ( $post_types as $post_type ) {
			if ( ! is_string( $post_type ) || $post_type === '' ) {
				continue;
			}
			dc23_schema_register_main_entity( $post_type, $main_entity );
		}
	}
}
