<?php declare( strict_types=1 );

namespace DC23\ExcessiveSchema\Integrations;

use DC23\ExcessiveSchema\Generators\Linked_Image;
use Yoast\WP\SEO\Context\Meta_Tags_Context;
use Yoast\WP\SEO\Models\Indexable;
use Yoast\WP\SEO\Models\SEO_Links;
use Yoast\WP\SEO\Repositories\Indexable_Repository;
use Yoast\WP\SEO\Repositories\SEO_Links_Repository;

use function YoastSEO;

class All_Article_Images {

    private SEO_Links_Repository $links_repo;

    public function register(): void {
        add_filter( 'wpseo_schema_graph_pieces', [ $this, 'add_image_piece_generator' ] );
        add_filter( 'wpseo_schema_article', [ $this, 'add_all_images' ], 10, 2 );
    }
    
    public function add_image_piece_generator( $pieces ) {
        $pieces[] = new Linked_Image();
        
        return $pieces;
    }

    public function add_all_images( $article, $context ) {
        if ( ! ( $context instanceof Meta_Tags_Context ) ) {
			return $article;
		}

		if ( ! $context->indexable ) {
			return $article;
		}

        $images = array_filter(
            $this->get_links_repo()->find_all_by_indexable_id( $context->indexable->id ),
			fn( $link ) => in_array( $link->type, [ SEO_Links::TYPE_INTERNAL_IMAGE, SEO_Links::TYPE_EXTERNAL_IMAGE ], true ),
		);

		if ( empty( $images ) ) {
			return $article;
		}
        
        if ( ! empty( $article['image'] ) && ! array_is_list( $article['image'] ) ) {
            // Wrap one associative array within a new array list.
            $article['image'] = [ $article['image'] ];
        }

        foreach ( $images as $image ) {
            // @TODO. Consider cases where $image->post_target_id needs comparing with $context->main_image_id
            if ( $image->url === $context->main_image_url ) {
                continue;
            }

            $article['image'][] = [
                '@id' => $image->url,
            ];
        }
        
        foreach ( $context->blocks['core/image'] as $block ) {
            $processor = new WP_HTML_Tag_Processor( $block['innerHTML'] );

            $block_src = null;
            while ( $processor->next_tag() ) {
                switch ( $processor->get_tag() ) {
                    case 'IMG':
                        $block_src = $processor->get_attribute( 'src' );
                        break 2;
                }
            }
            
            if ( $block_src ) {
                $article['image'][] = [
                    '@id' => $context->canonical . '#/schema/ImageObject/' . md5( $block_src ),
                ];
            }
        }
        $article['image'][] = $context->blocks['core/image'];

        return $article;
    }

    private function get_links_repo(): SEO_Links_Repository {
		if ( ! isset( $this->links_repo ) ) {
			$this->links_repo = YoastSEO()->classes->get( SEO_Links_Repository::class );
		}
		return $this->links_repo;
	}
}