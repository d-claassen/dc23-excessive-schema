<?php
/**
 * Integration tests for the Article main entity.
 *
 * @package DC23\ExcessiveSchema\Tests
 */

declare( strict_types=1 );

namespace DC23\ExcessiveSchema\Tests;

use DC23\ExcessiveSchema\Adapters\Article_Main_Entity;
use DC23\ExcessiveSchema\Registries\Main_Entity_Registry;
use WP_UnitTestCase;

use function DC23\ExcessiveSchema\dc23_schema_get_main_entity;
use function DC23\ExcessiveSchema\dc23_schema_main_entity_exists;

final class Article_Main_Entity_Test extends WP_UnitTestCase {

	public function set_up(): void {
		parent::set_up();
	}

	/**
	 * Override WordPress function that's incompatible with PHPUnit 10+.
	 */
	public function expectDeprecated(): void {
	}

	public function test_article_main_entity_registered_for_post_post_type(): void {
		// Verify Article is registered.
		$this->assertTrue(
			dc23_schema_main_entity_exists( 'post' ),
			'Article main entity should be registered for the "post" post type.'
		);

		// Verify basics.
		$main_entity = dc23_schema_get_main_entity( 'post' );
		$this->assertInstanceOf( Article_Main_Entity::class, $main_entity );
		$this->assertSame( 'Article', $main_entity->get_root_type() );
        
		// Create an indexable.
		$post_id   = self::factory()->post->create( [ 'post_status' => 'publish' ] );
		$indexable = \YoastSEO()->meta->for_post( $post_id )->context->indexable;
        
		// Verify id creation.
		$entity_id = $main_entity->get_entity_id( $indexable );
		$this->assertStringEndsWith( '#article', $entity_id );
		$this->assertStringStartsWith( $indexable->permalink, $entity_id );
	}

	public function test_dc23_schema_main_entity_filter_fires_on_article_schema(): void {
		$captured_indexable = null;
		add_filter(
			'dc23_schema_main_entity',
			static function ( array $data, $indexable ) use ( &$captured_indexable ): array {
				$captured_indexable     = $indexable;
				$data['_test_marker'] = true;
				return $data;
			},
			10,
			2
		);

		$post_id = self::factory()->post->create( [
			'post_title'  => 'Test article',
			'post_status' => 'publish',
		] );

		// Fetch the Yoast schema, which runs relevant filters.
		$article = $this->get_article_schema( $post_id );

		// Verify Article schema has the marker.
		$this->assertTrue(
			$article['_test_marker'] ?? false,
			'dc23_schema_main_entity should fire when wpseo_schema_article is applied.'
		);
		$this->assertNotNull( $captured_indexable, 'Indexable should be passed to the filter.' );
		$this->assertSame( $post_id, $captured_indexable->object_id );
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
}