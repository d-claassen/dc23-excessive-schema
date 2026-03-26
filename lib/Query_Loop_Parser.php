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
