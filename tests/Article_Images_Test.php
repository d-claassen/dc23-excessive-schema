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
								wp_update_post( [
									'ID' => $image_1,
									'post_excerpt' => 'Pretty canola',
								] );
        
        $image_2 = self::factory()->attachment->create_upload_object(
        	DIR_TESTDATA . '/images/waffles.jpg'
        );
								wp_update_post( [
									'ID' => $image_2,
									'post_excerpt' => 'Pretty waffles',
								] );
								
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

                <!-- wp:image {"sizeSlug":"full","linkDestination":"none"} -->
                <figure class="wp-block-image size-full"><img src="%5$s" alt="" /></figure>
                <!-- /wp:image -->
                HTML,
    			$image_1,
    			$image_1_url = wp_get_attachment_url( $image_1 ),
    			$image_2,
    			$image_2_url = wp_get_attachment_url( $image_2 ),
							$image_3_url = 'https://example.com/image.jpg',
    		),
    	] );
		
		// Update object to persist meta value to indexable.
		self::factory()->post->update_object( $post_id, [] );
					
		$schema  = $this->get_schema( $post_id );
		$article = $this->get_article_schema( $schema );
		
		$primary_image = \get_permalink( $post_id ) . '#primaryimage';
		$image_2_id = $this->build_image_id( $post_id, $image_2_url );
		$image_3_id = $this->build_image_id( $post_id, $image_3_url );

		$this->assertSame( [
			['@id' => $primary_image],
			['@id' => $image_2_id],
			['@id' => $image_3_id],
		], $article['image'] );
		
		$keyed_graph = array_column( $schema['@graph'], null, '@id' );
		
		$this->assertArrayHasKey( $primary_image, $keyed_graph );
		$this->assertArrayNotHasKey( $image_1_url, $keyed_graph );
		$this->assertSame( $image_1_url, $keyed_graph[$primary_image]['url'], '1st image in graph as primary' );
		$this->assertSame( 'Pretty canola', $keyed_graph[$primary_image]['caption'], 'primary image has caption' );
		
		$this->assertArrayHasKey( $image_2_id, $keyed_graph );
		$this->assertSame( $image_2_id, $keyed_graph[$image_2_id]['@id'], '@id is page bound' );
		$this->assertSame( 'ImageObject', $keyed_graph[$image_2_id]['@type'], '@type is image' );
		$this->assertSame( $image_2_url, $keyed_graph[$image_2_id]['contentUrl'], 'contentUrl is url' );
		$this->assertSame( $image_2_url, $keyed_graph[$image_2_id]['url'], 'url is url (compatibility support)' );
		$this->assertSame( 'Pretty waffles', $keyed_graph[$image_2_id]['caption'],	'2nd image has caption' );

		$this->assertArrayHasKey( $image_3_id, $keyed_graph );
		$this->assertSame( $image_3_id, $keyed_graph[$image_3_id]['@id'], '@id is page bound' );
		$this->assertSame( 'ImageObject', $keyed_graph[$image_3_id]['@type'], '@type is image' );
		$this->assertSame( $image_3_url, $keyed_graph[$image_3_id]['contentUrl'], 'contentUrl is url' );
		$this->assertSame( $image_3_url, $keyed_graph[$image_3_id]['url'], 'url is url (compatibility support)' );
	}

	public function test_schema_for_image_with_caption(): void {
		$feature_image = self::factory()->attachment->create_upload_object(
			DIR_TESTDATA . '/images/canola.jpg',
		);								
		wp_update_post( [
			'ID' => $feature_image,
			'post_excerpt' => 'Pretty canola',
		] );
		
		$content_image = self::factory()->attachment->create_upload_object(
			DIR_TESTDATA . '/images/waffles.jpg'
		);
		wp_update_post( [
			'ID' => $content_image,
			'post_excerpt' => 'Pretty waffles',
		] );

		$content_image_url = wp_get_attachment_url( $content_image );
		$content_image_alt = 'A plate of 3 waffles';
		$content_image_caption = 'Waffles are still served freshly-baked every day in the greenhouse.';

		$post_id = self::factory()->post->create( [
			'post_content' => sprintf(
				<<<'HTML'
				<!-- wp:image {"id":%1$d,"sizeSlug":"large","linkDestination":"none"} -->
				<figure class="wp-block-image size-large">
				 <img src="%2$s" alt="%3$s" class="wp-image-%1$d"/>
					<figcaption class="wp-element-caption">%4$s</figcaption>
				</figure>
				<!-- /wp:image -->
				HTML,
				$content_image,
				$content_image_url,
				$content_image_alt,
				$content_image_caption,
			),
		] );
			
		set_post_thumbnail( $post_id, $feature_image );

		// Update object to persist meta value to indexable.
		self::factory()->post->update_object( $post_id, [] );
					
		$schema  = $this->get_schema( $post_id, true );
		$article = $this->get_article_schema( $schema );
		
		$primary_image = \get_permalink( $post_id ) . '#primaryimage';
		$content_image_id = $this->build_image_id( $post_id, $content_image_url );

		$this->assertSame( [
			['@id' => $primary_image],
			['@id' => $content_image_id],
		], $article['image'] );

		$keyed_graph = array_column( $schema['@graph'], null, '@id' );
		
		$this->assertArrayHasKey( $content_image_id, $keyed_graph, 'content image in graph' );
		$this->assertSame( $content_image_url, $keyed_graph[$content_image_id]['url'], '1st image in text' );
		$this->assertSame( $content_image_caption, $keyed_graph[$content_image_id]['caption'], 'block caption in schema' );
	}

	public function test_schema_for_same_image_twice(): void {
		$feature_image = self::factory()->attachment->create_upload_object(
			DIR_TESTDATA . '/images/canola.jpg',
		);								

		$content_image = self::factory()->attachment->create_upload_object(
			DIR_TESTDATA . '/images/waffles.jpg'
		);
		$content_image_url = wp_get_attachment_url( $content_image );

		$post_id = self::factory()->post->create( [
			'post_content' => sprintf(
                <<<'HTML'
                <!-- wp:image {"id":%1$d,"sizeSlug":"large","linkDestination":"none"} -->
                <figure class="wp-block-image size-large"><img src="%2$s" alt="" class="wp-image-%1$d"/></figure>
                <!-- /wp:image -->
                
                <!-- wp:paragraph -->
                <p>Some text between the images.</p>
                <!-- /wp:paragraph -->
                
                <!-- wp:image {"id":%1$d,"sizeSlug":"large","linkDestination":"none"} -->
                <figure class="wp-block-image size-large"><img src="%2$s" alt="" class="wp-image-%1$d"/></figure>
                <!-- /wp:image -->
                HTML,
				$content_image,
				$content_image_url,
			),
		] );
			
		set_post_thumbnail( $post_id, $feature_image );

		// Update object to persist meta value to indexable.
		self::factory()->post->update_object( $post_id, [] );
					
		$schema  = $this->get_schema( $post_id, true );
		$article = $this->get_article_schema( $schema );
		
		$primary_image = \get_permalink( $post_id ) . '#primaryimage';
		$content_image_id_1 = $this->build_image_id( $post_id, $content_image_url );
		$content_image_id_2 = $this->build_image_id( $post_id, $content_image_url, 2 );

		$this->assertSame( [
			['@id' => $primary_image],
			['@id' => $content_image_id_1],
			['@id' => $content_image_id_2],
		], $article['image'], 'Duplicate image mentioned twice' );

		$urls = array_column( $schema['@graph'], 'url' );
		
		$this->assertSame( 2, array_count_values($urls)[$content_image_url] );
	}

	// -------------------------------------------------------------------------
	// Helpers
	// -------------------------------------------------------------------------

	private function build_image_id( int|\WP_Post $post_id, string $image_url, int $occurrence = 1 ) : string {
		return sprintf(
			'%s#/schema/ImageObject/%s-%d',
			\get_permalink( $post_id ),
			md5( $image_url ),
			$occurrence,
		);
	}

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