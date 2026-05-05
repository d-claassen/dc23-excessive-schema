<?php

declare( strict_types=1 );

namespace DC23\ExcessiveSchema\Integrations;

use Yoast\WP\SEO\Context\Meta_Tags_Context;
use Yoast\WP\SEO\Models\Indexable;
use Yoast\WP\SEO\Models\SEO_Links;
use Yoast\WP\SEO\Repositories\Indexable_Repository;
use Yoast\WP\SEO\Repositories\SEO_Links_Repository;

use function DC23\ExcessiveSchema\dc23_schema_get_main_entity;

class SEO_Links_As_Mentions {

	private Indexable_Repository $indexable_repo;
	private SEO_Links_Repository $links_repo;

	public function register(): void {
		add_filter( 'dc23_schema_main_entity', [ $this, 'add_main_entity_mentions' ], 10, 2 );
		add_filter( 'wpseo_schema_webpage', [ $this, 'add_webpage_mentions' ], 10, 2 );
	}

	/**
	 * Adds mentions to a main entity node via the uniform mutation point.
	 *
	 * @param array     $data      Main entity schema data.
	 * @param Indexable $indexable The indexable being rendered.
	 *
	 * @return array
	 */
	public function add_main_entity_mentions( $data, $indexable ) {
		if ( ! is_array( $data ) || ! ( $indexable instanceof Indexable ) ) {
			return $data;
		}

		return $this->add_mentions_for_indexable( $data, $indexable );
	}

	/**
	 * Adds mentions to the WebPage node when no main entity will render.
	 *
	 * Skipped when a main entity is registered for the current post type AND
	 * that main entity will actually render for this indexable. When a main
	 * entity is registered but resolves to no effective type for this
	 * instance (e.g. Yoast's "None" article type), the WebPage receives the
	 * mentions instead so they aren't lost.
	 *
	 * @param array             $data    WebPage schema data.
	 * @param Meta_Tags_Context $context Yoast context.
	 *
	 * @return array
	 */
	public function add_webpage_mentions( $data, $context ) {
		if ( ! ( $context instanceof Meta_Tags_Context ) ) {
			return $data;
		}

		if ( ! $context->indexable ) {
			return $data;
		}

		$post_type = $context->indexable->object_sub_type;
		if ( is_string( $post_type ) && $post_type !== '' ) {
			$main_entity = dc23_schema_get_main_entity( $post_type );
			if ( $main_entity !== null && $main_entity->get_entity_type( $context->indexable ) !== null ) {
				// A registered main entity will render and receive the mentions.
				return $data;
			}
		}

		return $this->add_mentions_for_indexable( $data, $context->indexable );
	}

	private function add_mentions_for_indexable( array $data, Indexable $indexable ): array {
		$links = array_filter(
			$this->get_links_repo()->find_all_by_indexable_id( $indexable->id ),
			fn( $link ) => $link->type === SEO_Links::TYPE_INTERNAL
		);

		if ( empty( $links ) ) {
			return $data;
		}

		$target_ids = array_column( $links, 'target_post_id' );
		$targets    = $this->get_indexable_repo()->find_by_multiple_ids_and_type( $target_ids, 'post' );
		$targets    = array_column( $targets, null, 'object_id' );

		$data['mentions'] ??= [];
		foreach ( $links as $link ) {
			$target    = $targets[ $link->target_post_id ] ?? null;
			$permalink = $link->url;
			if ( \YoastSEO()->helpers->url->is_relative( $permalink ) ) {
				$permalink = home_url( $permalink );
			}

			$data['mentions'][] = $this->build_mention_reference( $target, $permalink );
		}

		return $data;
	}

	/**
	 * Builds a single mention reference, pointing at the target's main entity
	 * node when one is registered and renders, falling back to the WebPage @id
	 * otherwise.
	 *
	 * @param Indexable|null $target    The target indexable, or null when not resolved.
	 * @param string         $permalink The absolute permalink to the target.
	 *
	 * @return array{'@id': string, '@type': string, url: string}
	 */
	private function build_mention_reference( ?Indexable $target, string $permalink ): array {
		if ( $target !== null && is_string( $target->object_sub_type ) && $target->object_sub_type !== '' ) {
			$main_entity = dc23_schema_get_main_entity( $target->object_sub_type );
			if ( $main_entity !== null ) {
				$entity_type = $main_entity->get_entity_type( $target );
				if ( $entity_type !== null ) {
					return [
						'@id'   => $main_entity->get_entity_id( $target ),
						'@type' => $entity_type,
						'url'   => $permalink,
					];
				}
			}
		}

		return [
			'@id'   => $permalink,
			'@type' => $target?->schema_page_type ?? 'WebPage',
			'url'   => $permalink,
		];
	}

	private function get_links_repo(): SEO_Links_Repository {
		if ( ! isset( $this->links_repo ) ) {
			$this->links_repo = YoastSEO()->classes->get( SEO_Links_Repository::class );
		}
		return $this->links_repo;
	}

	private function get_indexable_repo(): Indexable_Repository {
		if ( ! isset( $this->indexable_repo ) ) {
			$this->indexable_repo = YoastSEO()->classes->get( Indexable_Repository::class );
		}
		return $this->indexable_repo;
	}
}