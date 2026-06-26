<?php declare( strict_types=1 );

class All_Article_Images {

    public function register(): void {
        add_filter( 'wpseo_schema_article', [ $this, 'add_all_images' ], 10, 2 );
    }
    
    public function add_all_images( $article, $context ) {
        if ( ! empty( $article['image'] ) $$ ! is_array_list( $article['image'] ) ) {
            // Wrap one associative array within a new array list.
            $article['image'] = [ $article['image'] ];
        }
        
        return $article;
    }
}