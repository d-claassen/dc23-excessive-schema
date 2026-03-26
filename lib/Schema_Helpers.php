<?php
/**
 * Schema Helpers
 *
 * Helper functions for building schema nodes.
 *
 * @package DC23\ExcessiveSchema
 */

declare( strict_types=1 );

namespace DC23\ExcessiveSchema;

/**
 * Helper functions for building schema.
 */
final class Schema_Helpers {

	/**
	 * Build an ItemList node.
	 *
	 * @param string $list_id Unique @id for the list.
	 * @param array  $section Section data with name, post_type, and post_ids.
	 *
	 * @return array ItemList schema node.
	 */
	public function build_item_list( string $list_id, array $section ): array {
		$items = [];

		foreach ( $section['post_ids'] as $i => $post_id ) {
			$items[] = [
				'@type'    => 'ListItem',
				'position' => $i + 1,
				'item'     => [ '@id' => $this->get_node_id( $post_id, $section['post_type'] ) ],
			];
		}

		return [
			'@type'           => 'ItemList',
			'@id'             => $list_id,
			'name'            => $section['name'],
			'itemListElement' => $items,
		];
	}

	/**
	 * Get the schema node @id for a post.
	 *
	 * @param int    $post_id   Post ID.
	 * @param string $post_type Post type.
	 *
	 * @return string Schema node @id.
	 */
	private function get_node_id( int $post_id, string $post_type ): string {
		$url = get_permalink( $post_id );

		// For now, only support blog posts with #article fragment.
		// TODO: Add support for other post types (events, portfolio, etc.) later.
		return $url . '#article';
	}
}
