<?php
/**
 * Integration tests for the Article images.
 *
 * @package DC23\ExcessiveSchema\Tests
 */

declare( strict_types=1 );

namespace DC23\ExcessiveSchema\Tests;

use WP_UnitTestCase;

final class Article_Images_Test extends WP_UnitTestCase {

	public function set_up(): void {
		parent::set_up();
		
		$this->set_up_internal_linking();

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
	
	private function set_up_internal_linking(): void {
		// Enable pretty urls. Yoasts internal linking system removes query args for "canonicalizing" indexables.
		// That doesnt work well when the post id is specifically passed via them.
		$this->set_permalink_structure( '/%postname%/' );
		
		// Enable indexables to allow internal links between then being set.
		add_filter( 'wpseo_should_save_indexable', '__return_true' );
		
		// Workaround for Yoast bug where relative urls arent resolved for home_url values
		// with a port in it due to poor absolute url construction.
		add_filter( 'home_url', static function ( $url ) {
    $parts = wp_parse_url( $url );
    if ( ! isset( $parts['port'] ) ) {
        return $url;
    }
    $rebuilt = $parts['scheme'] . '://' . $parts['host'];
    if ( isset( $parts['path'] ) ) {
        $rebuilt .= $parts['path'];
    }
    return $rebuilt;
		} );
	}

	/**
	 * Override WordPress function that's incompatible with PHPUnit 10+.
	 */
	public function expectDeprecated(): void {
	}

    public function test_schema_with_multiple_images(): void {
        $image_1 = self::factory()->attachment->create_upload_object(
        	DIR_TESTDATA . '/images/canola.jpg'
        );
        
        $image_2 = self::factory()->attachment->create_upload_object(
        	DIR_TESTDATA . '/images/waffles.jpg'
        );
        
        $post_id = self::factory()->post->create( [
    		'post_content' => sprintf(
    			<<<'HTML'
                <!-- wp:image {"id":%1$d,"sizeSlug":"large","linkDestination":"none"} -->
                <figure class="wp-block-image size-large"><img src="%2$s" alt="" class="wp-image-%1$d"/></figure>
                <!-- /wp:image -->
                
                <!-- wp:paragraph -->
                <p>Some text between the images.</p>
                <!-- /wp:paragraph -->
                
                <!-- wp:image {"id":%3$d,"sizeSlug":"large","linkDestination":"none"} -->
                <figure class="wp-block-image size-large"><img src="%4$s" alt="" class="wp-image-%3$d"/></figure>
                <!-- /wp:image -->
                HTML,
    			$image_1,
    			$image_1_url = wp_get_attachment_url( $image_1 ),
    			$image_2,
    			$image_2_url = wp_get_attachment_url( $image_2 )
    		),
    	] );
		
		// Update object to persist meta value to indexable.
		self::factory()->post->update_object( $post_id, [] );
					
		$schema  = $this->get_schema( $post_id )
		$article = $this->get_article_schema( $schema );
		
		$this->assertSame( [
			['@id' => \get_permalink( $post_id ) . '#primaryimage' ],
			['@id' => $image_2_url],
		], $article['image'] );
		
		$keyed_graph = array_column( $schema['graph'], null, '@id' );
		
		$this->assertKeyExists( $image_2_url, $keyed_graph );
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

	private function get_article_schema( $schema ): ?array {
		foreach ( $schema['@graph'] ?? [] as $piece ) {
			if ( isset( $piece['@type'] ) && $piece['@type'] === 'Article' ) {
				return $piece;
			}
		}

		return null;
	}
}