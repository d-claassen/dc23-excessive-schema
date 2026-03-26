<?php
/**
 * Query Loop Parser
 *
 * Hooks into render_block to capture Query Loop blocks and extract their content.
 *
 * @package DC23\ExcessiveSchema
 */

declare( strict_types=1 );

namespace DC23\ExcessiveSchema;

/**
 * Parses Query Loop blocks during page rendering.
 */
final class Query_Loop_Parser {

	/**
	 * Collected sections from Query Loop blocks.
	 *
	 * @var array<int, array{name: string, post_type: string, post_ids: array<int>}>
	 */
	public array $sections = [];

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function register(): void {
		add_filter( 'render_block', [ $this, 'capture' ], 10, 2 );
	}

	/**
	 * Capture Query Loop blocks and extract their data.
	 *
	 * @param string $html  Block HTML output.
	 * @param array  $block Block data.
	 *
	 * @return string Unmodified HTML.
	 */
	public function capture( string $html, array $block ): string {
		if ( $block['blockName'] !== 'core/query' ) {
			return $html;
		}

		$post_type = $block['attrs']['query']['postType'] ?? 'post';
		$name      = $this->resolve_name( $block );
		$post_ids  = $this->resolve_post_ids( $block['attrs']['query'] ?? [] );

		if ( ! empty( $post_ids ) ) {
			$this->sections[] = [
				'name'      => $name,
				'post_type' => $post_type,
				'post_ids'  => $post_ids,
			];
		}

		return $html;
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
		// TODO: Implement heading search in inner blocks.
		// TODO: Check metadata.name.
		// TODO: Fallback to post type label.

		return 'Query Loop Section';
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
		// TODO: Build WP_Query args from block attributes.
		// TODO: Handle taxonomy queries.
		// TODO: Return post IDs.

		return [];
	}
}
