<?php

namespace DC23\ExcessiveSchema\Generators;

use \Yoast\WP\SEO\Generators\Schema\Abstract_Schema_Piece;

/**
 * Returns blog schema markup.
 */
class Blog extends Abstract_Schema_Piece {

	/**
	 * Determines whether a piece should be added to the graph.
	 *
	 * @return bool
	 */
	public function is_needed() {
        if ( is_category() ) {
            return true;
        }

        return false;
	}

	/**
	 * Generate pieces for thr graph.
	 *
	 * @return array<list<string, mixed>>
	 */
	public function generate() {
		$graph = [];

		$graph[] = $this->generate_blog();

		return $graph;
	}

	/**
	 * Generate a Blog piece.
	 *
	 *
	 * @return array<sting, mixed>
	 */
        protected function generate_blog(): array {
                $blog_id = $this->context->indexable->object_id;
                $id      = $this->context->site_url . '#/schema/blog/' . \esc_attr( $blog_id );

		$category = \get_term( $id, 'category' );
		\assert( $category instanceof WP_Term );

		$data = [
				'@id'         => $id,
				'@type'       => 'Blog',
				'name'        => $category->name,
				'description' => \wp_trim_excerpt( $category->description ),
				'publisher'   => [
					// @todo. support company.
					'@id' => \YoastSEO()->helpers->schema->id->get_user_schema_id( $context->site_user_id, $context ),
				],
				'inLanguage'  => \get_bloginfo( 'language' ),
			],
		);
		
		return $data;
	}
}
