<?php declare( strict_types=1 );

namespace DC23\ExcessiveSchema\Integrations;

class All_Article_Images {

    private SEO_Links_Repository $links_repo;

    public function register(): void {
        add_filter( 'wpseo_schema_article', [ $this, 'add_all_images' ], 10, 2 );
    }
    
    public function add_all_images( $article, $context ) {
        if ( ! ( $context instanceof Meta_Tags_Context ) ) {
			return $data;
		}

		if ( ! $context->indexable ) {
			return $data;
		}
        
        $images = array_filter(
			$this->get_links_repo()->find_all_by_indexable_id( $context->indexable->id ),
			fn( $link ) => $link->type === SEO_Links::TYPE_INTERNAL
		);

		if ( empty( $images ) ) {
			return $data;
		}
        
        if ( ! empty( $article['image'] ) && ! array_is_list( $article['image'] ) ) {
            // Wrap one associative array within a new array list.
            $article['image'] = [ $article['image'] ];
        }
        
        return $article;
    }

    private function get_links_repo(): SEO_Links_Repository {
		if ( ! isset( $this->links_repo ) ) {
			$this->links_repo = YoastSEO()->classes->get( SEO_Links_Repository::class );
		}
		return $this->links_repo;
	}
}