<?php

namespace DC23\ExcessiveSchema\Tests;

use Yoast\WP\SEO\Builders\Indexable_Link_Builder;
use Yoast\WP\SEO\Repositories\Indexable_Repository;

class Article_Mentions_Schema_Test extends \WP_UnitTestCase {

	public function set_up(): void {
		parent::set_up();
		
		// Enable pretty urls. Yoasts internal linking system removes query args for "canonicalizing" indexables.
		// That doesnt work well when the post id is specifically passed via them.
		$this->set_permalink_structure( '/%postname%/' );
		
		// Enable indexables to allow internal links between then being set.
		add_filter( 'wpseo_should_save_indexable', '__return_true' );
		
		// Create test user for publisher. Needed for Article ouput from wordpress-seo below 26.7.
		$this->user_id = self::factory()->user->create( [
			'display_name' => 'Test User',
			'user_email'   => 'test@example.com',
			'user_url'     => 'https://example.com',
		] );

		// Set Yoast user settings to use person schema
		\YoastSEO()->helpers->options->set( 'company_or_person', 'person' );
		\YoastSEO()->helpers->options->set( 'company_or_person_user_id', $this->user_id );
	}

	/**
	 * Override WordPress function that's incompatible with PHPUnit 10+.
	 */
	public function expectDeprecated(): void {
	}

	public function test_mentions_added_for_internal_links(): void {
		$target_id  = self::factory()->post->create( [ 'post_status' => 'publish' ] );
		$target_url = get_permalink( $target_id );

		$source_id = self::factory()->post->create( [
			'post_status'  => 'publish',
			'post_content' => sprintf( '<p>See <a href="%s">this post</a>.</p>', $target_url ),
		] );
		
		// Update object to persist meta value to indexable.
		self::factory()->post->update_object( $target_id, [] );
		self::factory()->post->update_object( $source_id, [] );

		$this->go_to( \get_permalink( $source_id ) );

		$article = $this->get_article_schema( $source_id );

		$this->assertArrayHasKey( 'mentions', $article );
		$this->assertSame( $target_url, $article['mentions'][0]['url'] );
		$this->assertSame( $target_url . '#article', $article['mentions'][0]['@id'] );
	}

	public function test_mentions_added_for_internal_taxonomy_links(): void {
		$target_id  = self::factory()->category->create( [ 'name' => 'News' ] );
		$target_url = get_category_link( $target_id );

		$source_id = self::factory()->post->create( [
			'post_status'  => 'publish',
			'post_content' => sprintf( '<p>See <a href="%s">this taxonomy</a>.</p>', $target_url ),
		] );
		
		// Update object to persist meta value to indexable.
		self::factory()->post->update_object( $target_id, [] );
		self::factory()->post->update_object( $source_id, [] );

		$this->go_to( \get_permalink( $source_id ) );

		$article = $this->get_article_schema( $source_id );

		$this->assertArrayHasKey( 'mentions', $article );
		$this->assertSame( $target_url, $article['mentions'][0]['url'] );
		$this->assertSame( $target_url, $article['mentions'][0]['@id'] );
	}
	
	public function test_absolute_mentions_added_for_relative_links(): void {
		$target_id  = self::factory()->post->create( [
			'post_status' => 'publish',
			'post_name'   => 'awesome-post',
		] );
		$target_url = get_permalink( $target_id );

		$source_id = self::factory()->post->create( [
			'post_status'  => 'publish',
			'post_content' => sprintf( '<p>See <a href="%s">this awesome post</a>.</p>', '/awesome-post/' ),
		] );
		
		// Update object to persist meta value to indexable.
		self::factory()->post->update_object( $target_id, [] );
		self::factory()->post->update_object( $source_id, [] );

		$this->go_to( \get_permalink( $source_id ) );

		$article = $this->get_article_schema( $source_id, true );

		$this->assertArrayHasKey( 'mentions', $article );
		$this->assertSame( 'Article', $article['mentions'][0]['@type'] );
		$this->assertSame( $target_url, $article['mentions'][0]['url'] );
		$this->assertSame( $target_url . '#article', $article['mentions'][0]['@id'] );
	}

	public function test_mentions_added_to_webpage(): void {
		$target_id  = self::factory()->post->create( [ 'post_status' => 'publish' ] );
		$target_url = get_permalink( $target_id );

		$source_id = self::factory()->post->create( [
			'post_status'  => 'publish',
			'post_type'    => 'page',
			'post_content' => sprintf( '<p>See <a href="%s">this post</a>.</p>', $target_url ),
		] );
		
		// Update object to persist meta value to indexable.
		self::factory()->post->update_object( $target_id, [] );
		self::factory()->post->update_object( $source_id, [] );

		$this->go_to( \get_permalink( $source_id ) );

		$webpage = $this->get_webpage_schema( $source_id );

		$this->assertArrayHasKey( 'mentions', $webpage );
		$this->assertSame( $target_url, $webpage['mentions'][0]['url'] );
		$this->assertSame( $target_url . '#article', $webpage['mentions'][0]['@id'] );
	}
	
	public function test_no_mentions_when_no_internal_links(): void {
		$source_id = self::factory()->post->create( [
			'post_status'  => 'publish',
			'post_content' => '<p>No links here.</p>',
		] );

		// Update object to persist meta value to indexable.
		self::factory()->post->update_object( $source_id, [] );
		
		$this->go_to( \get_permalink( $source_id ) );
		
		$article = $this->get_article_schema( $source_id );

		$this->assertArrayNotHasKey( 'mentions', $article );
	}

	public function test_external_links_are_not_mentioned(): void {
		$source_id = self::factory()->post->create( [
			'post_status'  => 'publish',
			'post_content' => '<p>See <a href="https://external.com/post">this</a>.</p>',
		] );

		// Update object to persist meta value to indexable.
		self::factory()->post->update_object( $source_id, [] );
		
		$this->go_to( \get_permalink( $source_id ) );
		
		$article = $this->get_article_schema( $source_id );

		$this->assertArrayNotHasKey( 'mentions', $article );
	}

	public function test_page_type_is_derived_from_target_indexable(): void {
		$target_id = self::factory()->post->create( [
			'post_status' => 'publish',
			'post_type'   => 'page',
	 ] );
		
		\YoastSEO()->helpers->meta->set_value( 'schema_page_type', 'ItemPage', $target_id );

		$source_id = self::factory()->post->create( [
			'post_status'  => 'publish',
			'post_content' => sprintf( '<p><a href="%s">link</a></p>', get_permalink( $target_id ) ),
		] );

		// Update object to persist meta value to indexable.
		self::factory()->post->update_object( $target_id, [] );
		self::factory()->post->update_object( $source_id, [] );
		
		$this->go_to( \get_permalink( $source_id ) );
		
		$article = $this->get_article_schema( $source_id );

		$this->assertSame( 'ItemPage', $article['mentions'][0]['@type'] );
	}

	// -------------------------------------------------------------------------
	// Helpers
	// -------------------------------------------------------------------------

	private function get_schema( int $post_id, bool $debug = false ): array {
		$this->go_to( get_permalink( $post_id ) );

		ob_start();
		do_action( 'wpseo_head' );
		$output = ob_get_clean();

		preg_match( '/<script type="application\/ld\+json"[^>]*>(.*?)<\/script>/s', $output, $matches );

		if ( $debug ) {
			var_dump( $matches[0] ?? 'no matches' );
		}

		return json_decode( $matches[1] ?? '{}', true );
	}

	private function get_article_schema( int $post_id, bool $debug = false ): ?array {
		$schema = $this->get_schema( $post_id, $debug );

		foreach ( $schema['@graph'] ?? [] as $piece ) {
			if ( isset( $piece['@type'] ) && $piece['@type'] === 'Article' ) {
				return $piece;
			}
		}

		return null;
	}
	
	private function get_webpage_schema( int $post_id ): ?array {
		$schema = $this->get_schema( $post_id );

		foreach ( $schema['@graph'] ?? [] as $piece ) {
			if ( isset( $piece['@type'] ) && $piece['@type'] === 'WebPage' ) {
				return $piece;
			}
		}

		return null;
	}
}
