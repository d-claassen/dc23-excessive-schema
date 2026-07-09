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
		
		$this->assertSame( [
			['@id' => $primary_image],
			['@id' => $image_3_url],
			['@id' => $image_2_url],
		], $article['image'] );
		
		$keyed_graph = array_column( $schema['@graph'], null, '@id' );
		
		$this->assertArrayHasKey( $primary_image, $keyed_graph );
		$this->assertArrayNotHasKey( $image_1_url, $keyed_graph );
		$this->assertSame( $image_1_url, $keyed_graph[$primary_image]['url'], '1st image in graph as primary' );
		$this->assertSame( 'Pretty canola', $keyed_graph[$primary_image]['caption'], 'primary image has caption' );
		
		$this->assertArrayHasKey( $image_2_url, $keyed_graph );
		$this->assertSame( $image_2_url, $keyed_graph[$image_2_url]['@id'], '@id is url' );
		$this->assertSame( 'ImageObject', $keyed_graph[$image_2_url]['@type'], '@type is image' );
		$this->assertSame( $image_2_url, $keyed_graph[$image_2_url]['contentUrl'], 'contentUrl is url' );
		$this->assertSame( $image_2_url, $keyed_graph[$image_2_url]['url'], 'url is url (compatibility support)' );
		$this->assertSame(
		 'Pretty waffles',
		 $keyed_graph[$image_2_url]['caption'],
			 sprintf(
				'2nd image has caption: post %s, image %s',
					$post_id, $image_2,
				)
			);

		$this->assertArrayHasKey( $image_3_url, $keyed_graph );
		$this->assertSame( $image_3_url, $keyed_graph[$image_3_url]['@id'], '@id is url' );
		$this->assertSame( 'ImageObject', $keyed_graph[$image_3_url]['@type'], '@type is image' );
		$this->assertSame( $image_3_url, $keyed_graph[$image_3_url]['contentUrl'], 'contentUrl is url' );
		$this->assertSame( $image_3_url, $keyed_graph[$image_3_url]['url'], 'url is url (compatibility support)' );
	}

	public function test_schema_for_image_with_caption(): void {
		$image_id = self::factory()->attachment->create_upload_object(
			DIR_TESTDATA . '/images/canola.jpg',
		);								
		wp_update_post( [
			'ID' => $image_id,
			'post_excerpt' => 'Pretty canola',
		] );

		$image_url = wp_get_attachment_url( $image_id );
		$image_alt = 'Image description.';
		$image_caption = 'Image caption.';

		$post_id = self::factory()->post->create( [
			'post_content' => sprintf(
				<<<'HTML'
				<!-- wp:image {"id":%1$d,"sizeSlug":"large","linkDestination":"none"} -->
				<figure class="wp-block-image size-large">
				 <img src="%2$s" alt="%3$s" class="wp-image-936"/>
					<figcaption class="wp-element-caption">%4$s</figcaption>
				</figure>
				<!-- /wp:image -->
				HTML,
				$image_id,
				$image_url,
				$image_alt,
				$image_caption,
			),
		] );
			
		set_post_thumbnail( $post_id, $image_id );

		// Update object to persist meta value to indexable.
		self::factory()->post->update_object( $post_id, [] );
					
		$schema  = $this->get_schema( $post_id, true );
		$article = $this->get_article_schema( $schema );
		
		$primary_image = \get_permalink( $post_id ) . '#primaryimage';
		
		$this->assertSame( [
			['@id' => $primary_image],
			//['@id' => $image_url],
		], $article['image'] );

		$keyed_graph = array_column( $schema['@graph'], null, '@id' );
		
		$this->assertArrayHasKey( $primary_image, $keyed_graph );
		$this->assertArrayNotHasKey( $image_url, $keyed_graph );
		$this->assertSame( $image_url, $keyed_graph[$primary_image]['url'], '1st image in graph as primary' );
		$this->assertArrayHasKey( 'caption', $keyed_graph[$primary_image] );
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