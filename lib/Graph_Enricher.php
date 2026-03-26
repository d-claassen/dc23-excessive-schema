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

		// TODO: Get page type from Yoast.
		// TODO: Determine connection property (hasPart vs mentions).
		// TODO: Build ItemList nodes.
		// TODO: Mutate WebPage node to add references.
		// TODO: Merge new nodes into graph.

		return $graph;
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
