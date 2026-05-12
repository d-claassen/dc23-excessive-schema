<?php declare( strict_types=1 );

namespace DC23\ExcessiveSchema\Integrations;

use Yoast\WP\SEO\Models\Indexable;

use function DC23\ExcessiveSchema\dc23_schema_get_main_entity;

final class Query_As_ItemList {

    public function register(): void {
        add_action( 'wpseo_pre_schema_block_type_core/query', [ $this, 'prepare_itemlist_references' ], 10, 1 );
    }

	public function render_itemlist_schema( $graph, $query_loop_block, $context ) {
		$post_ids = $this->resolve_post_ids( $query_loop_block['attrs']['query'] ?? [] );

		// No content in this block.
		if ( empty( $post_ids ) ) {
			return $graph;
		}

		$query_id  = $query_loop_block['attrs']['queryId'];
		$list_id   = $context->canonical . '#/schema/itemlist/' . $query_id;
		$list_name = $this->resolve_name( $query_loop_block );

		$items = [];
		foreach ( $post_ids as $i => $post_id ) {
			$position = $i + 1;
			$items[] = [
				'@id'      => $list_id . '-' . $position,
				'@type'    => 'ListItem',
				'position' => $position,
				'item'     => $this->build_item_reference( (int) $post_id ),
			];
		}

		array_push(
			$graph,
			[
				'@id'             => $list_id,
				'@type'           => 'ItemList',
				'name'            => $list_name,
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
		// Run late so we see mentions already added by other integrations
		// (notably SEO_Links_As_Mentions at priority 10) and can prune any
		// of those that duplicate @ids inside our ItemLists.
		add_filter( 'wpseo_schema_webpage', function( $webpage_data, $context ) use ( $blocks ) {
			$references = [];
			foreach ( $blocks as $query_loop_block ) {
				$references = $this->render_itemlist_schema( $references, $query_loop_block, $context );
			}

			if ( empty( $references ) ) {
				return $webpage_data;
			}

			$webpage_data['mentions'] ??= [];
			array_push( $webpage_data['mentions'], ...$references );

			// Collect @ids referenced inside our ItemLists' items, then drop
			// any top-level scalar mentions that duplicate them. The ItemList
			// carries richer context (list name, ordering), so it wins; the
			// scalar mention is redundant.
			$item_list_ids = $this->collect_item_list_member_ids( $references );
			if ( ! empty( $item_list_ids ) ) {
				$webpage_data['mentions'] = array_values(
					array_filter(
						$webpage_data['mentions'],
						fn( $mention ) => ! $this->is_redundant_scalar_mention( $mention, $item_list_ids )
					)
				);
			}

			return $webpage_data;
		}, 20, 2 );
	}

	/**
	 * Builds the `item` reference for a ListItem, using the registered main
	 * entity's @id and entity type when available, falling back to the post's
	 * permalink and schema page type otherwise.
	 *
	 * @return array{'@id': string, '@type': string}
	 */
	private function build_item_reference( int $post_id ): array {
		$post_context = \YoastSEO()->meta->for_post( $post_id );
		$indexable    = $post_context->context->indexable ?? null;

		if ( $indexable instanceof Indexable
			&& is_string( $indexable->object_sub_type )
			&& $indexable->object_sub_type !== ''
		) {
			$main_entity = dc23_schema_get_main_entity( $indexable->object_sub_type );
			if ( $main_entity !== null ) {
				$entity_type = $main_entity->get_entity_type( $indexable );
				if ( $entity_type !== null ) {
					return [
						'@id'   => $main_entity->get_entity_id( $indexable ),
						'@type' => $entity_type,
					];
				}
			}
		}

		return [
			'@id'   => \get_permalink( $post_id ),
			'@type' => $post_context->schema_page_type ?? 'WebPage',
		];
	}

	/**
	 * Collects all member @ids referenced inside the provided ItemLists'
	 * itemListElement.item entries.
	 *
	 * @param array $item_lists Array of ItemList nodes.
	 *
	 * @return string[]
	 */
	private function collect_item_list_member_ids( array $item_lists ): array {
		$ids = [];
		foreach ( $item_lists as $list ) {
			if ( ! is_array( $list ) || ! isset( $list['itemListElement'] ) ) {
				continue;
			}
			foreach ( $list['itemListElement'] as $list_item ) {
				if ( isset( $list_item['item']['@id'] ) && is_string( $list_item['item']['@id'] ) ) {
					$ids[] = $list_item['item']['@id'];
				}
			}
		}
		return $ids;
	}

	/**
	 * A mention is a redundant scalar if it's a flat reference (has @id but
	 * no itemListElement of its own) and its @id matches one of the ItemList
	 * member @ids.
	 */
	private function is_redundant_scalar_mention( $mention, array $item_list_ids ): bool {
		if ( ! is_array( $mention ) ) {
			return false;
		}
		// Don't prune ItemList nodes themselves.
		if ( isset( $mention['itemListElement'] ) ) {
			return false;
		}
		if ( ! isset( $mention['@id'] ) || ! is_string( $mention['@id'] ) ) {
			return false;
		}
		return in_array( $mention['@id'], $item_list_ids, true );
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
		$post_type     = $block['attrs']['query']['postType'] ?? 'post';
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