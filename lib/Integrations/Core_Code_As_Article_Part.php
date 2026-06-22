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
                'text' => $this->unwrap_core_code_block( $code_block['innerHTML'] ),
            ],
        );

        return $graph;
    }

    private function unwrap_core_code_block( string $html ): string {
    	if ( ! preg_match( '/<code\b[^>]*>(.*?)<\/code>/s', $html, $matches ) ) {
    		return '';
    	}
    
    	return html_entity_decode(
    		$matches[1],
    		ENT_QUOTES | ENT_HTML5,
    		'UTF-8'
    	);
    }

    /**
     * If this fires, we know there's a Code block on the page, so reference it from the webpage piece.
     *
     * @param array $blocks The blocks of this type on the current page.
     */
    public function prepare_sourcecode_references( $blocks ) {
        add_filter( 'wpseo_schema_article', function( $article_data, $context ) use ( $blocks ) {
            $references = [];
            foreach ( $blocks as $code_block ) {
                $references[] = [
                    '@id' => $context->canonical . '#/schema/sourcecode/',
                ];
            }

            $article_data['hasPart'] = $article_data['hasPart'] ?? [];
            array_push(
                $article_data['hasPart'],
                ...$references,
            );

            return $article_data;
        }, 10, 2 );
    }
}