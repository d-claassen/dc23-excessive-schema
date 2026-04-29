<?php

namespace DC23\ExcessiveSchema\Integrations;

use Yoast\WP\SEO\Context\Meta_Tags_Context;
use Yoast\WP\SEO\Models\SEO_Links;
use Yoast\WP\SEO\Repositories\Indexable_Repository;
use Yoast\WP\SEO\Repositories\SEO_Links_Repository;

class SEO_Links_As_Mentions {

	private Indexable_Repository $indexable_repo;
	private SEO_Links_Repository $links_repo;

	public function register(): void {
		add_filter( 'wpseo_schema_article', [ $this, 'add_mentions' ], 10, 2 );
		add_filter( 'wpseo_schema_webpage', [ $this, 'add_webpage_mentions' ], 10, 2 );
	}
	
	public function add_webpage_mentions( $data, $context ) {
		if ( ! ( $context instanceof Meta_Tags_Context ) ) {
			// Unexpected data received. Bail out.
			return $data;
		}
		
		if ( $context->has_article ) {
			// Will enrich the Article instead.
			return $data;
		}
		
		return $this->add_mentions( $data, $context );
	}

	public function add_mentions( $data, $context ) {
		if ( ! ( is_array( $data ) && ( $context instanceof Meta_Tags_Context ) ) ) {
			// Unexpected data received. Bail out.
			return $data;
		}

		if ( ! $context->indexable ) {
			return $data;
		}

		$links = array_filter(
			$this->get_links_repo()->find_all_by_indexable_id( $context->indexable->id ),
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
				// Prepend the relative url with the home url.
				$permalink = home_url( $permalink );
			}

			$data['mentions'][] = [
				'@id'   => $permalink,
				'@type' => $target?->schema_page_type ?? 'WebPage',
				'url'   => $permalink,
			];
		}

		return $data;
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
