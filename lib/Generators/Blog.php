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


                $data = [
                        '@type' => 'Blog,
                        '@id'   => $id,
                ];

                return $data;
        }
}
