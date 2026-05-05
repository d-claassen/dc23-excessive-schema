<?php
/**
 * Article main entity.
 *
 * @package DC23\ExcessiveSchema
 */

declare( strict_types=1 );

namespace DC23\ExcessiveSchema\Adapters;

use Yoast\WP\SEO\Config\Schema_IDs;
use Yoast\WP\SEO\Context\Meta_Tags_Context;
use Yoast\WP\SEO\Models\Indexable;

/**
 * Main entity for Yoast's Article schema piece.
 *
 * Bridges the `wpseo_schema_article` filter into `dc23_schema_main_entity`,
 * the uniform mutation point all enrichment logic attaches to. Serves as the
 * reference implementation for main entities in glue plugins (dc23-tea,
 * dc23-software-downloads, etc.).
 */
final class Article_Main_Entity implements Main_Entity {

	public function get_root_type(): string {
		return 'Article';
	}

	public function get_entity_id( Indexable $indexable ): string {
		$permalink = $indexable->permalink;
		if ( \YoastSEO()->helpers->url->is_relative( $permalink ) ) {
			$permalink = home_url( $permalink );
		}
		return $permalink . Schema_IDs::ARTICLE_HASH;
	}

	/**
	 * Resolves the Article subtype following Yoast's resolution chain:
	 * per-post override → per-post-type default → root type.
	 *
	 * Yoast's "None" selection (meaning "do not render Article schema for
	 * this post") is translated to null at this boundary so callers don't
	 * need to know about Yoast's string convention.
	 */
	public function get_entity_type( Indexable $indexable ): ?string {
		$override = get_post_meta( $indexable->object_id, '_yoast_wpseo_schema_article_type', true );
		if ( is_string( $override ) && $override !== '' ) {
			return $override === 'None' ? null : $override;
		}

		if ( is_string( $indexable->object_sub_type ) && $indexable->object_sub_type !== '' ) {
			$default = YoastSEO()->helpers->options->get(
				'schema-article-type-' . $indexable->object_sub_type
			);
			if ( is_string( $default ) && $default !== '' ) {
				return $default === 'None' ? null : $default;
			}
		}

		return $this->get_root_type();
	}

	public function get_allowed_subtypes(): ?array {
		return null;
	}

	public function setup_main_entity_enrichment(): void {
		add_filter( 'wpseo_schema_article', array( $this, 'enrich' ), 10, 2 );
	}

	/**
	 * Bridge Yoast's Article filter into dc23_schema_main_entity.
	 *
	 * @param array             $data    Yoast's Article schema data.
	 * @param Meta_Tags_Context $context Yoast's context object.
	 *
	 * @return array
	 */
	public function enrich( array $data, Meta_Tags_Context $context ): array {
		/**
		 * Filters the main entity data before it is output as schema.
		 *
		 * Hooked by dc23-excessive-schema for mentions injection and (later)
		 * override application, subtype mutation, and sidebar-driven changes.
		 *
		 * @param array     $data      The main entity schema data.
		 * @param Indexable $indexable The indexable being rendered.
		 */
		return apply_filters( 'dc23_schema_main_entity', $data, $context->indexable );
	}
}
