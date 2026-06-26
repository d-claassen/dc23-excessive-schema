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
        
        if ( ! empty( $article['image'] ) && ! array_is_list( $article['image'] ) ) {
            // Wrap one associative array within a new array list.
            $article['image'] = [ $article['image'] ];
        }
        
        return $article;
    }
}