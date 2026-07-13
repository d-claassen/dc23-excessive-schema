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
        $image_counts = [];

        if ( isset( $this->context->blocks['core/image'] ) ) {
        foreach ( $this->context->blocks['core/image'] as $block ) {
            $processor = new \WP_HTML_Tag_Processor( $block['innerHTML'] );

            $block_src = null;
            while ( $processor->next_tag() ) {
                switch ( $processor->get_tag() ) {
                    case 'IMG':
                        $block_src = $processor->get_attribute( 'src' );
                        break 2;
                }
            }
            
            if ( $block_src ) {
                $image_counts[$block_src] ??= 0;
                ++$image_counts[$block_src];
                
                if ( $block_src === $this->context->main_image_url && $image_counts[$block_src] === 1 ) {
                    continue;
                }
                
                $image = $this->helpers->schema->image->generate_from_url(
                    $this->context->canonical . '#/schema/ImageObject/' . md5( $block_src ) . '-' . $image_counts[$image->url],
                    $image->url,
                );
                $image_pieces[] = $image;
            }
        }
        }

        $images = array_filter(
            $this->get_links_repo()->find_all_by_indexable_id( $this->context->indexable->id ),
			fn( $link ) => in_array( $link->type, [ SEO_Links::TYPE_INTERNAL_IMAGE, SEO_Links::TYPE_EXTERNAL_IMAGE ], true ),
		);

        foreach ( $images as $image ) {
            // @TODO. Consider cases where $image->post_target_id needs comparing with $context->main_image_id?
            if ( $image->url === $this->context->main_image_url ) {
                continue;
            }

            if ( array_key_exists( $image->url, $image_counts ) ) {
                continue;
            }

            $image_counts[$image->url] = 1;
            
            $image = $this->helpers->schema->image->generate_from_url(
                $context->canonical . '#/schema/ImageObject/' . md5( $image->url ) . '-' . $image_counts[$image->url],
                $image->url,
            );
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
