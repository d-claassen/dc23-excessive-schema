<?php

namespace DC23\ExcessiveSchema\Tests;

use Yoast\WP\SEO\Builders\Indexable_Link_Builder;
use Yoast\WP\SEO\Repositories\Indexable_Repository;

class Article_Mentions_Schema_Test extends \WP_UnitTestCase {

	public function set_up(): void {
		parent::set_up();
	}

	public function test_mentions_added_for_internal_links(): void {
		$target_id  = self::factory()->post->create( [ 'post_status' => 'publish' ] );
		$target_url = get_permalink( $target_id );

		$source_id = self::factory()->post->create( [
			'post_status'  => 'publish',
			'post_content' => sprintf( '<p>See <a href="%s">this post</a>.</p>', $target_url ),
		] );

		$this->index_links( $source_id );

		$article = $this->get_article_schema( $source_id );

		$this->assertArrayHasKey( 'mentions', $article );
		$this->assertSame( $target_url, $article['mentions'][0]['url'] );
	}

	public function test_no_mentions_when_no_internal_links(): void {
		$source_id = self::factory()->post->create( [
			'post_status'  => 'publish',
			'post_content' => '<p>No links here.</p>',
		] );

		$this->index_links( $source_id );

		$article = $this->get_article_schema( $source_id );

		$this->assertArrayNotHasKey( 'mentions', $article );
	}

	public function test_external_links_are_not_mentioned(): void {
		$source_id = self::factory()->post->create( [
			'post_status'  => 'publish',
			'post_content' => '<p>See <a href="https://external.com/post">this</a>.</p>',
		] );

		$this->index_links( $source_id );

		$article = $this->get_article_schema( $source_id );

		$this->assertArrayNotHasKey( 'mentions', $article );
	}

	public function test_article_type_is_derived_from_target_indexable(): void {
		$target_id = self::factory()->post->create( [ 'post_status' => 'publish' ] );

		// Set the schema type on the target's indexable.
		$indexable_repo = YoastSEO()->classes->get( Indexable_Repository::class );
		$indexable      = $indexable_repo->find_by_id_and_type( $target_id, 'post' );
		$indexable->schema_article_type = 'BlogPosting';
		$indexable->save();

		$source_id = self::factory()->post->create( [
			'post_status'  => 'publish',
			'post_content' => sprintf( '<p><a href="%s">link</a></p>', get_permalink( $target_id ) ),
		] );

		$this->index_links( $source_id );

		$article = $this->get_article_schema( $source_id );

		$this->assertSame( 'BlogPosting', $article['mentions'][0]['@type'] );
	}

	// -------------------------------------------------------------------------
	// Helpers
	// -------------------------------------------------------------------------

	private function index_links( int $post_id ): void {
		$indexable_repo = YoastSEO()->classes->get( Indexable_Repository::class );
		$link_builder   = YoastSEO()->classes->get( Indexable_Link_Builder::class );

		$indexable = $indexable_repo->find_by_id_and_type( $post_id, 'post' );
		$post      = get_post( $post_id );

		$link_builder->build( $indexable, $post->post_content );
	}

	private function get_schema( int $post_id ): array {
		$this->go_to( get_permalink( $post_id ) );

		ob_start();
		do_action( 'wpseo_head' );
		$output = ob_get_clean();

		preg_match( '/<script type="application\/ld\+json"[^>]*>(.*?)<\/script>/s', $output, $matches );

		return json_decode( $matches[1] ?? '{}', true );
	}

	private function get_article_schema( int $post_id ): ?array {
		$schema = $this->get_schema( $post_id );

		foreach ( $schema['@graph'] ?? [] as $piece ) {
			if ( isset( $piece['@type'] ) && $piece['@type'] === 'Article' ) {
				return $piece;
			}
		}

		return null;
	}
}