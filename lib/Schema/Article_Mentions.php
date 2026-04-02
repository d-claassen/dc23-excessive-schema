<?php

namespace DC23\ExcessiveSchema\Schema;

use Yoast\WP\SEO\Context\Meta_Tags_Context;
use Yoast\WP\SEO\Models\SEO_Links;
use Yoast\WP\SEO\Repositories\Indexable_Repository;
use Yoast\WP\SEO\Repositories\SEO_Links_Repository;

class Article_Mentions {

	private Indexable_Repository $indexable_repo;
	private SEO_Links_Repository $links_repo;

	public function register(): void {
		add_filter( 'wpseo_schema_article', [ $this, 'add_mentions' ], 10, 2 );
	}

	public function add_mentions( array $data, Meta_Tags_Context $context ): array {
		if ( ! $context->indexable ) {
			return $data;
		}

		$links = array_filter(
			$this->get_links_repo()->find_all_by_indexable_id( $context->indexable->id ),
			fn( $l ) => $l->type === SEO_Links::TYPE_INTERNAL && ! empty( $l->target_post_id )
		);

		if ( empty( $links ) ) {
			return $data;
		}

		$target_ids = array_column( $links, 'target_post_id' );
		$targets    = $this->get_indexable_repo()->find_by_multiple_ids_and_type( $target_ids, 'post' );
		$targets    = array_column( $targets, null, 'object_id' );

		$mentions = [];
		foreach ( $links as $link ) {
			$target     = $targets[ $link->target_post_id ] ?? null;
			$mentions[] = [
				'@type' => $target?->schema_article_type
					?? $target?->schema_page_type
					?? 'WebPage',
				'url'   => $link->permalink ?: $link->url,
			];
		}

		$data['mentions'] = $mentions;

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