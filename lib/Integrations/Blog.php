<?php
declare(strict_types=1);

namespace DC23\ExcessiveSchema\Integrations;

use DC23\ExcessiveSchema\Generators\Blog as Blog_Generator;
use WP_Post;
use WP_Query;
use WP_Term;
use Yoast\WP\SEO\Context\Meta_Tags_Context;
use Yoast\WP\SEO\Generators\Schema\Abstract_Schema_Piece;

final class Blog {

	public function register(): void {

		\add_filter( 'wpseo_schema_webpage', [ $this, 'make_blog_main_entity' ], 11, 2 );

		\add_filter( 'wpseo_schema_graph_pieces', [ $this, 'add_blog_to_schema' ], 11, 1 );
	}

	private function should_add_blog_data(): bool {
		return \is_category();
	}

	/**
	 * Enhance the WebPage data with a mainEntity reference to the blog.
	 *
	 * @template T of array{"@type": string}
	 *
	 * @param T $webpage_data The webpage data.
	 * @param Meta_Tags_Context $context The current page context.
	 *
	 * @return T|(T&array{mainEntity: array{"@id": string}}) The enhanced webpage.
	 */
	public function make_blog_main_entity( $webpage_data, $context ) {

        // @todo. decide to rely on blog generator.
		if ( ! $this->should_add_blog_data() ) {
			return $webpage_data;
		}

		$category = \get_term( \get_query_var( 'cat' ), 'category' );
		\assert( $category instanceof WP_Term );

		$webpage_data['mainEntity'] = [
			'@id' => $context->site_url . '#/schema/blog/' . $category->term_id,
		];

		return $webpage_data;
	}

	/**
	 * Add new Blog piece to Schema.org graph.
	 *
	 * @param Abstract_Schema_Piece[] $pieces Existing schema pieces.
	 *
	 * @return array<Abstract_Schema_Piece> Schema pieces.
	 */
	public function add_blog_to_schema( $pieces ) {

		$pieces[] = new Blog_Generator();

		return $pieces;
	}
}
