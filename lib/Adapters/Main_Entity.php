<?php
/**
 * Main entity interface.
 *
 * @package DC23\ExcessiveSchema
 */

declare( strict_types=1 );

namespace DC23\ExcessiveSchema\Adapters;

use Yoast\WP\SEO\Models\Indexable;

/**
 * Contract describing a main entity for a post type.
 *
 * Implementations declare identity (root type, entity ID, allowed subtypes)
 * and set up enrichment by bridging the source plugin's schema filter into
 * the `dc23_schema_main_entity` filter where enrichment logic attaches.
 */
interface Main_Entity {

	/**
	 * The schema.org root type this main entity represents (e.g. "Article", "Event").
	 *
	 * @return string
	 */
	public function get_root_type(): string;

	/**
	 * The `@id` of the main entity node for the given indexable.
	 *
	 * Used by mentions and other reference logic to link to the entity rather
	 * than the WebPage that contains it.
	 *
	 * @param Indexable $indexable The indexable to resolve.
	 *
	 * @return string The `@id` URI.
	 */
	public function get_entity_id( Indexable $indexable ): string;

	/**
	 * The effective schema.org type for a specific indexable.
	 *
	 * Resolves to the actual subtype the source plugin would render for this
	 * instance — for example, "BlogPosting" for an Article whose user-selected
	 * subtype is BlogPosting.
	 *
	 * Returns null when the source plugin would not render a main entity node
	 * for this indexable (e.g. Yoast's "None" article type selection). Callers
	 * should fall back to a WebPage reference in that case.
	 *
	 * @param Indexable $indexable The indexable to resolve.
	 */
	public function get_entity_type( Indexable $indexable ): ?string;

	/**
	 * Subtypes of the root type that are valid for this post type.
	 *
	 * Returns null when no constraint applies (any subtype of the root type is allowed).
	 *
	 * @return string[]|null
	 */
	public function get_allowed_subtypes(): ?array;

	/**
	 * Install the bridge from the source plugin's schema filter into
	 * `dc23_schema_main_entity`.
	 *
	 * Called automatically by `dc23_schema_register_main_entity()` after the
	 * main entity has been stored in the registry. Implementations with no
	 * bridging needs can leave this as a no-op.
	 *
	 * @return void
	 */
	public function setup_main_entity_enrichment(): void;
}
