<?php declare( strict_types=1 );

namespace DC23\ExcessiveSchema\Schema;

final class ItemList {

    public function register(): void {
        add_filter( 'wpseo_schema_block_core/query', [ $this, 'render_itemlist_schema' ], 10, 3 );
        
        add_action( 'wpseo_pre_schema_block_type_core/query', [ $this, 'prepare_itemlist_references' ], 10, 1 );
    }
    
    public function render_itemlist_schema( $graph, $query_loop_block, $context ) {
        printf('Adding 1 ItemList based on a core/query blocks');

        $post_ids  = $this->resolve_post_ids( $query_loop_block['attrs']['query'] ?? [] );

        // No content in this block.
        if ( empty( $post_ids ) ) {
            return $graph;
        }

        $items = [];
        $list_name = $this->resolve_name( $query_loop_block );
        foreach ( $post_ids as $i => $post_id ) {
            $post_context = \YoastSEO()->meta->for_post( $post_id );
            $page_type    = $post_context->schema_page_type;
            $main_entity   = $post_context->main_entity_of_page;
            
            
			$items[] = [
				'@type'    => 'ListItem',
				'position' => $i + 1,
                '_item' => $main_entity,
				'item'     => [
                    '@id'   => \get_permalink( $post_id ),
                    '@type' => $page_type,
                ],
			];
		}

        array_push(
            $graph,
            [
                '@id' => $context->canonical . '#/schema/itemlist/' . sanitize_title( $list_name ),
                '@type' => 'ItemList',
                'name' => $list_name,
                'itemListElement' => $items,
            ],
        );
        
        return $graph;
    }
    
    /**
     * If this fires, we know there's a Query Loop block on the page, so reference it from the webpage piece.
     *
     * @param array $blocks The blocks of this type on the current page.
     */
    public function prepare_itemlist_references( $blocks ) {
        printf('Found %d core/query blocks', count($blocks));
        add_filter( 'wpseo_schema_webpage', function( $webpage_data, $context ) use ( $blocks ) {
            printf('Attaching %d ItemList pieces to the WebPage', count($blocks));
            
            $references = [];
            foreach ( $blocks as $query_loop_block ) {
                $list_name = $this->resolve_name( $query_loop_block );
                $references[] = [
                    '@id' => $context->canonical . '#/schema/itemlist/' . sanitize_title( $list_name ),
                ];
            }
            
            $list_as_side_content = ['ProfilePage', 'AboutPage', 'ItemPage'];
            $webpage_type = (array) $webpage_data['@type'];
            if ( ! empty( array_intersect( $webpage_type, $list_as_side_content ) ) ) {
                $webpage_data['mentions'] = $webpage_data['mentions'] ?? [];
                array_push(
                    $webpage_data['mentions'],
                    ...$references,
                );
            } else {
                $webpage_data['hasPart'] = $webpage_data['hasPart'] ?? [];
                array_push(
                    $webpage_data['hasPart'],
                    ...$references,
                );
            }
            
            return $webpage_data;
        }, 10, 2 );
    }
    

	/**
	 * Resolve the name for a Query Loop section.
	 *
	 * Priority order:
	 * 1. First core/heading or core/query-title in inner blocks
	 * 2. Block's editor-assigned rename (metadata.name)
	 * 3. Post type label fallback
	 *
	 * @param array $block Block data.
	 *
	 * @return string Section name.
	 */
	private function resolve_name( array $block ): string {
		// Priority 1: Search for heading in inner blocks.
		$heading = $this->find_heading_in_blocks( $block['innerBlocks'] ?? [] );
		if ( ! empty( $heading ) ) {
			return $heading;
		}

		// Priority 2: Check metadata name.
		$metadata_name = $block['attrs']['metadata']['name'] ?? '';
		if ( ! empty( $metadata_name ) ) {
			return $metadata_name;
		}

		// Priority 3: Fallback to post type label.
		$post_type = $block['attrs']['query']['postType'] ?? 'post';
		$post_type_obj = get_post_type_object( $post_type );

		return $post_type_obj->labels->name ?? 'Posts';
	}

	/**
	 * Recursively search for heading block in inner blocks.
	 *
	 * @param array $blocks Array of blocks to search.
	 *
	 * @return string Heading content or empty string.
	 */
	private function find_heading_in_blocks( array $blocks ): string {
		foreach ( $blocks as $block ) {
			// Check if this is a heading or query-title block.
			if ( in_array( $block['blockName'] ?? '', [ 'core/heading', 'core/query-title' ], true ) ) {
				// Extract text content from block.
				$content = $block['innerHTML'] ?? '';
				$content = wp_strip_all_tags( $content );
				$content = trim( $content );

				if ( ! empty( $content ) ) {
					return $content;
				}
			}

			// Recursively search inner blocks.
			if ( ! empty( $block['innerBlocks'] ) ) {
				$heading = $this->find_heading_in_blocks( $block['innerBlocks'] );
				if ( ! empty( $heading ) ) {
					return $heading;
				}
			}
		}

		return '';
	}

    /**
	 * Resolve post IDs from Query Loop attributes.
	 *
	 * Mirrors the block's query attributes into a WP_Query to get the IDs.
	 *
	 * @param array $query_attrs Query attributes from the block.
	 *
	 * @return array<int> Post IDs.
	 */
	private function resolve_post_ids( array $query_attrs ): array {
		$args = [
			'post_type'      => $query_attrs['postType'] ?? 'post',
			'posts_per_page' => $query_attrs['perPage'] ?? get_option( 'posts_per_page' ),
			'orderby'        => $query_attrs['orderBy'] ?? 'date',
			'order'          => strtoupper( $query_attrs['order'] ?? 'DESC' ),
			'fields'         => 'ids',
			'no_found_rows'  => true,
		];

		$query = new \WP_Query( $args );

		return $query->posts;
	}
}