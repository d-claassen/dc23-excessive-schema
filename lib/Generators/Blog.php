<?php

namespace DC23\ExcessiveSchema\Generators;

use \WP_Post;
use \WP_Term;
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
								
								if ( \is_single() && \get_post_type() === 'post' ) {
										$schema_article_type = (array) $this->context->schema_article_type;

			if ( \in_array( 'BlogPosting', $schema_article_type, true ) ) {
				return true;
			}
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

		if ( \is_category() ) {
			$category = \get_term( \get_query_var( 'cat' ), 'category' );
		} else{
			$post = \get_post( \get_the_ID() );
			\assert( $post instanceof WP_Post );

			$categories = \wp_get_post_categories( $post->ID, [ 'fields' => 'all' ] );
			if ( \count( $categories ) !== 1 ) {
				// Only add Blog piece when there's one category:
				// - Without category, there's no blog to connect with,
				// - With multiple categories, it'll be a PITA to make sense.
				return [];
			}

			$category = \reset( $categories );
		}
		\assert( $category instanceof WP_Term );

		$data = [
				'@id'         => $id,
				'@type'       => 'Blog',
				'name'        => $category->name,
				'description' => \wp_trim_excerpt( $category->description ),
				'publisher'   => [
					// @todo. support company.
					'@id' => \YoastSEO()->helpers->schema->id->get_user_schema_id( $this->context->site_user_id, $this->context ),
				],
				'inLanguage'  => \get_bloginfo( 'language' ),
			];
			
			if ( \isset( $post ) ) {
				$id      = \get_permalink( $post->ID ) . '#article';
				$post_id = [ '@id' => $id ];

				$data[	'blogPost' ] = [ $post_id ];
			}
		
		return $data;
	}
}
