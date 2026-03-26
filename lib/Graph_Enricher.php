<?php
/**
 * Graph Enricher
 *
 * Enriches the Yoast schema graph with ItemList nodes from Query Loop sections.
 *
 * @package DC23\ExcessiveSchema
 */

declare( strict_types=1 );

namespace DC23\ExcessiveSchema;

/**
 * Enriches the Yoast SEO schema graph.
 */
final class Graph_Enricher {

	/**
	 * Query Loop Parser instance.
	 *
	 * @var Query_Loop_Parser
	 */
	private Query_Loop_Parser $parser;

	/**
	 * Schema Helpers instance.
	 *
	 * @var Schema_Helpers
	 */
	private Schema_Helpers $helpers;

	/**
	 * Constructor.
	 *
	 * @param Query_Loop_Parser $parser  Parser instance.
	 * @param Schema_Helpers    $helpers Helpers instance.
	 */
	public function __construct( Query_Loop_Parser $parser, Schema_Helpers $helpers ) {
		$this->parser  = $parser;
		$this->helpers = $helpers;
	}

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function register(): void {
		add_filter( 'wpseo_schema_graph', [ $this, 'enrich' ], 10, 2 );
	}

	/**
	 * Enrich the schema graph with ItemList nodes.
	 *
	 * @param array  $graph   Schema graph.
	 * @param object $context Yoast context object.
	 *
	 * @return array Modified graph.
	 */
	public function enrich( array $graph, object $context ): array {
		if ( empty( $this->parser->sections ) ) {
			return $graph;
		}

		// Get page type from Yoast.
		$page_type = $this->get_page_type();

		// Determine connection property based on page type.
		$property = $this->get_connection_property( $page_type );

		// Build canonical URL and WebPage @id.
		$canonical  = $context->canonical;
		$webpage_id = $canonical . '#webpage';

		// Build ItemList nodes and collect references.
		$additions  = [];
		$references = [];

		foreach ( $this->parser->sections as $section ) {
			$list_id      = $canonical . '#' . sanitize_title( $section['name'] ) . '-list';
			$references[] = [ '@id' => $list_id ];
			$additions[]  = $this->helpers->build_item_list( $list_id, $section );
		}

		// Find and mutate the WebPage node to add references.
		foreach ( $graph as &$node ) {
			if ( ( $node['@id'] ?? '' ) === $webpage_id ) {
				// Merge with existing property if present.
				$existing         = $node[ $property ] ?? [];
				$node[ $property ] = array_merge( $existing, $references );
				break;
			}
		}
		unset( $node );

		// Merge ItemList nodes into graph.
		return array_merge( $graph, $additions );
	}

	/**
	 * Get the current page's schema type from Yoast.
	 *
	 * @return string Page type (e.g., 'WebPage', 'CollectionPage').
	 */
	private function get_page_type(): string {
		if ( ! function_exists( 'YoastSEO' ) ) {
			return 'WebPage';
		}

		try {
			$page_type = YoastSEO()->meta->for_current_page()->schema_page_type;
			return $page_type ?? 'WebPage';
		} catch ( \Exception $e ) {
			return 'WebPage';
		}
	}

	/**
	 * Get the connection property based on page type.
	 *
	 * @param string $page_type Yoast page type (e.g., 'CollectionPage', 'ProfilePage').
	 *
	 * @return string Connection property ('hasPart' or 'mentions').
	 */
	private function get_connection_property( string $page_type ): string {
		return match ( $page_type ) {
			'ProfilePage', 'AboutPage', 'ItemPage' => 'mentions',
			default => 'hasPart',
		};
	}
}
