<?php

namespace DC23\ExcessiveSchema\Generators;

use \Yoast\WP\SEO\Generators\Schema\Abstract_Schema_Piece;

class Linked_Image extends Abstract_Schema_Piece {

    public function is_needed() {
        return $this->context->has_article;
    }
    
    public function generate() {
        $image_pieces = [];
        
        $images = array_filter(
            $this->get_links_repo()->find_all_by_indexable_id( $context->indexable->id ),
			fn( $link ) => in_array( $link->type, [ SEO_Links::TYPE_INTERNAL_IMAGE, SEO_Links::TYPE_EXTERNAL_IMAGE ], true ),
		);

		if ( empty( $images ) ) {
			return $article;
		}

        foreach ( $images as $image ) {
            // @TODO. Consider cases where $image->post_target_id needs comparing with $context->main_image_id
            if ( $image->url === $context->main_image_url ) {
                continue;
            }

            $image_pieces[] = [
                '@id' => $image->url,
            ];
        }

        return $image_pieces;
    }

    private function get_links_repo(): SEO_Links_Repository {
		if ( ! isset( $this->links_repo ) ) {
			$this->links_repo = YoastSEO()->classes->get( SEO_Links_Repository::class );
		}
		return $this->links_repo;
	}
}
    
    
}