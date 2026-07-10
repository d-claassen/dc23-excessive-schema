<?php

namespace DC23\ExcessiveSchema\Generators;

use \Yoast\WP\SEO\Generators\Schema\Abstract_Schema_Piece;
use Yoast\WP\SEO\Models\SEO_Links;
use Yoast\WP\SEO\Repositories\SEO_Links_Repository;

class Linked_Image extends Abstract_Schema_Piece {

    private SEO_Links_Repository $links_repo;

    public function is_needed() {
        return $this->context->has_article;
    }
    
    public function generate() {
        $image_pieces = [];
        
        $images = array_filter(
            $this->get_links_repo()->find_all_by_indexable_id( $this->context->indexable->id ),
			fn( $link ) => in_array( $link->type, [ SEO_Links::TYPE_INTERNAL_IMAGE, SEO_Links::TYPE_EXTERNAL_IMAGE ], true ),
		);

		if ( empty( $images ) ) {
            return $image_pieces;
		}

        foreach ( $images as $image ) {
            // @TODO. Consider cases where $image->post_target_id needs comparing with $context->main_image_id
            if ( $image->url === $this->context->main_image_url ) {
                continue;
            }

            $image = $this->helpers->schema->image->generate_from_url(
                $image->url,
                $image->url,
            );
            
            $image['caption'] = $this->context->blocks;
            
            $image_pieces[] = $image;
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
