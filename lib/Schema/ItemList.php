<?php declare( strict_types=1 );

namespace DC23\ExcessiveSchema\Schema;

final class ItemList {

    public function register(): void {
        add_filter( 'wpseo_schema_block_core/query', [ $this, 'render_itemlist_schema' ], 10, 3 );
        
        add_action( 'wpseo_pre_schema_block_type_core/query', [ $this, 'prepare_itemlist_references' ], 10, 1 );
    }
    
    public function render_itemlist_schema( $graph, $query_loop_block, $context ) {
        printf('Adding 1 ItemList based on a core/query blocks');

        array_push(
            $graph,
            [
                '@id' => $context->site_url . '#/schema/itemlist/1',
                '@type' => 'ItemList',
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
            
            $webpage_data['hasPart'] = [
                [ '@id' => $context->site_url . '#/schema/itemlist/1' ],
            ];

            $webpage_data['mentions'] = [
                [ '@id' => $context->site_url . '#/schema/itemlist/1' ],
            ];
            
            return $webpage_data;
        }, 10, 2 );
    }
}