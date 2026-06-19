<?php declare( strict_types=1 );

namespace DC23\ExcessiveSchema\Integrations;

final class Core_Code_As_Article_Part {

    public function register(): void {
        add_filter( 'wpseo_schema_block_core/code', [ $this, 'render_sourcecode_schema' ], 10, 3 );
        
        add_action( 'wpseo_pre_schema_block_type_core/code', [ $this, 'prepare_sourcecode_references' ], 10, 1 );
    }
    
    public function render_sourcecode_schema( $graph, $code_block, $context ) {
        array_push(
            $graph,
            [
                // @TODO. create identifier.
                '@id' => $context->canonical . '#/schema/sourcecode/' . '',
                '@type' => 'SoftwareSourceCode',
                'text' => $code_block,
            ],
        );
        
        return $graph;
    }
    
    /**
     * If this fires, we know there's a Code block on the page, so reference it from the webpage piece.
     *
     * @param array $blocks The blocks of this type on the current page.
     */
    public function prepare_itemlist_references( $blocks ) {
        add_filter( 'wpseo_schema_webpage', function( $webpage_data, $context ) use ( $blocks ) {
            $references = [];
            foreach ( $blocks as $code_block ) {
                $references[] = [
                    '@id' => $context->canonical . '#/schema/sourcecode/',
                ];
            }
            

                $webpage_data['hasPart'] = $webpage_data['hasPart'] ?? [];
                array_push(
                    $webpage_data['hasPart'],
                    ...$references,
                );
            
            return $webpage_data;
        }, 10, 2 );
    }
}