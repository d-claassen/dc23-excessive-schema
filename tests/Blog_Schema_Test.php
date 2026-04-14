<?php

namespace DC23\ExcessiveSchema\Tests;

/**
 * Class Blog_Schema_Test.
 *
 * @testdox Blog schema markup for a category
 */
class Blog_Schema_Test extends \WP_UnitTestCase {

	private $user_id;

	public function setUp(): void {
		parent::setUp();

		// Yoast user settings
		$this->user_id = self::factory()->user->create();

		\YoastSEO()->helpers->options->set( 'company_or_person', 'person' );
		\YoastSEO()->helpers->options->set( 'company_or_person_user_id', $this->user_id );
	}

	// override wordpress function thats incompatible
	// with phpunit 10.
	public function expectDeprecated(){}

	public function test_should_contain_blog_piece_as_main_entity(): void {
		$post_id = self::factory()->post->create( [
			'title'        => 'New post',
			'post_content' => 'Hello world!',
		] );

		$category_id = self::factory()->category->create( [
				'name' => 'News',
		] );

		\wp_set_post_categories( $post_id, [ $category_id ] );

		$this->go_to( \get_category_link( $category_id ) );

		$schema_output = $this->get_schema_output( true );
		$schema_data = \json_decode( $schema_output, JSON_OBJECT_AS_ARRAY );

		$blog_piece    = $this->get_piece_by_type( $schema_data['@graph'], 'Blog' );
		$webpage_piece = $this->get_piece_by_type( $schema_data['@graph'], 'CollectionPage' );
		$org_piece     = $this->get_piece_by_type( $schema_data['@graph'], ['Person', 'Organization'] );

		$this->assertSame(
			$blog_piece['@id'],
			$webpage_piece['mainEntity']['@id'],
			'MainEntity should be Blog'
		);
		$this->assertSame(
			'http://localhost:8889/#/schema/blog/2',
			$blog_piece['@id'],
			'Format {{website}}/#/schema/{{type}}/{{ID}}'
		);
		$this->assertSame('News', $blog_piece['name']);
		$this->assertSame(
			$org_piece['@id'],
			$blog_piece['publisher']['@id'],
			'Blog publisher should be org'
		);
	}

	/**
	 * @testdox Should not contain Blog piece when the article type is not BlogPosting
	 */
	public function test_should_not_add_blog_piece_when_article_type_is_not_blog_posting(): void {
		$post_id = self::factory()->post->create(
			array(
				'title'        => 'unchanged',
				'post_content' => 'Hello world!',
			)
		);

		\YoastSEO()->helpers->options->set( 'schema-article-type-post', '' );
		\YoastSEO()->helpers->meta->delete( 'schema_article_type', $post_id );

		$this->go_to( \get_permalink( $post_id ) );

		$schema_output = $this->get_schema_output();

		$this->assertJson( $schema_output );

		$schema_data = json_decode( $schema_output, JSON_OBJECT_AS_ARRAY );

		$article_piece = $this->get_piece_by_type( $schema_data['@graph'], 'Article' );

		$this->assertSame('Article', $article_piece['@type'],'Article piece should just be Article');
	}

	/**
	 * @testdox Should contain Blog piece when the article type for the post type is BlogPosting
	 */
	public function test_should_add_blog_piece_when_default_article_type_is_blog_posting(): void {
		$post_id = self::factory()->post->create(
			array(
				'title'        => 'default setting',
				'post_content' => 'Hello world!',
				'post_type'    => 'post',
			)
		);

		// Set the default Schema.org article type for the post type "post" to BlogPosting.
		\YoastSEO()->helpers->options->set( 'schema-article-type-post', 'BlogPosting' );

		$this->go_to( \get_permalink( $post_id ) );

		$schema_output = $this->get_schema_output();

		$this->assertJson( $schema_output );

		$schema_data = json_decode( $schema_output, JSON_OBJECT_AS_ARRAY );

		$article_piece = $this->get_piece_by_type( $schema_data['@graph'], [ 'Article', 'BlogPosting' ] );
		$blog_piece = $this->get_piece_by_type( $schema_data['@graph'], 'Blog' );

		$this->assertSame(['Article','BlogPosting'], $article_piece['@type'],'Article graph piece should be BlogPosting');
		$this->assertSame('Blog', $blog_piece['@type'],'Blog graph piece should exist');
		$this->assertSame($article_piece['@id'], $blog_piece['blogPost'][0]['@id'],'Blog should refer to BlogPosting');
	}

	/**
	 * @testdox Should contain Blog piece when the article type for the single post is BlogPosting
	 */
	public function test_should_contain_blog_piece_when_article_type_for_post_is_blog_posting(): void {
		$post_id = self::factory()->post->create(
			array(
				'title'        => 'indexable setting',
				'post_content' => 'Hello world!',
			)
		);

		\YoastSEO()->helpers->meta->set_value( 'schema_article_type', 'BlogPosting', $post_id );

		// Update object to persist meta value to indexable.
		self::factory()->post->update_object( $post_id, [] );

		$this->go_to( \get_permalink( $post_id ) );

		$schema_output = $this->get_schema_output();

		$this->assertJson( $schema_output );

		$schema_data = \json_decode( $schema_output, JSON_OBJECT_AS_ARRAY );

		$article_piece = $this->get_piece_by_type( $schema_data['@graph'], [ 'Article', 'BlogPosting' ] );
		$blog_piece = $this->get_piece_by_type( $schema_data['@graph'], 'Blog' );

		$this->assertSame(['Article','BlogPosting'], $article_piece['@type'],'Article piece should be BlogPosting');
		$this->assertSame('Blog', $blog_piece['@type'],'Blog graph piece should exit');
		$this->assertSame($article_piece['@id'], $blog_piece['blogPost'][0]['@id'],'Blog should refer to BlogPosting');
	}

	/**
	 * @testdox Should not contain Blog piece when the post has multiple categories
	 */
	public function test_should_not_contain_blog_piece_when_post_has_multiple_categories(): void {
		$post_id = self::factory()->post->create(
			array(
				'title'        => 'indexable setting',
				'post_content' => 'Hello world!',
			)
		);

		\YoastSEO()->helpers->meta->set_value( 'schema_article_type', 'BlogPosting', $post_id );

		\wp_set_post_categories(
			$post_id,
			[
				self::factory()->category->create(),
				self::factory()->category->create(),
				self::factory()->category->create(),
			]
		);

		// Update object to persist meta value to indexable.
		self::factory()->post->update_object( $post_id, [] );

		$this->go_to( \get_permalink( $post_id ) );

		$schema_output = $this->get_schema_output();

		$this->assertJson( $schema_output );

		$schema_data = \json_decode( $schema_output, JSON_OBJECT_AS_ARRAY );

		$article_piece = $this->get_piece_by_type( $schema_data['@graph'], [ 'Article', 'BlogPosting' ] );

		$this->assertSame([ 'Article', 'BlogPosting' ], $article_piece['@type'],'Article graph piece should be BlogPosting');
	}

	private function get_schema_output( bool $debug_wpseo_head = false ): string {

		ob_start();
		do_action( 'wpseo_head' );
		$wpseo_head = ob_get_contents();
		ob_end_clean();

		if ( $debug_wpseo_head ) {
			print $wpseo_head;
		}

		$dom = new \DOMDocument();
		$dom->loadHTML( $wpseo_head );
		$scripts = $dom->getElementsByTagName('script');
		foreach( $scripts as $script ) {
			if( $script instanceof \DOMElement && $script->getAttribute('type') === 'application/ld+json') {
				return $script->textContent;
			}
		}

		throw new \LengthException('No schema script was found in the wpseo_head output.' );
	}

	/**
	 * Find a Schema.org piece in the root of the Graph by its type.
	 *
	 * @param array<int, array{"@type": string}> $graph Schema.org graph.
	 * @param string|array<int, string> $type Schema type to search for.
	 * @return array{"@type": string} The matching schema.org piece.
	 */
	private function get_piece_by_type( $graph, $type ): array {
		$nodes_of_type = array_filter( $graph, fn( $piece ) => $piece['@type'] === $type );

		if ( empty( $nodes_of_type ) ) {
			throw new \InvalidArgumentException( 'No piece found for type' );
		}

		// Return first instance.
		return reset( $nodes_of_type );
	}
}
