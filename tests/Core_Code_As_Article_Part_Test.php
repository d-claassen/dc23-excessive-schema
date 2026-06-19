<?php

namespace DC23\ExcessiveSchema\Tests;

final class Core_Code_As_Article_Part_Test extends \WP_UnitTestCase {

	public function set_up(): void {
		parent::set_up();

		// Create test user for publisher. Needed for Article output from wordpress-seo below 26.7.
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
		// noop.
	}
	
	public function test_part_added_for_code_block(): void {
		$post_id = self::factory()->post->create( [
			'post_status'  => 'publish',
			'post_content' => <<<GB_HTML
				<!-- wp:code -->
					<pre class="wp-block-code"><code>&lt;?php

					function greet( string $name ): string {
					return "Hello {$name}";
				}
				
				echo greet( 'Dennis' );</code></pre>
				<!-- /wp:code -->
			GB_HTML,
		] );
		
		// Update object to persist meta value to indexable.
		self::factory()->post->update_object( $post_id, [] );

		$this->go_to( \get_permalink( $post_id ) );

		$article = $this->get_article_schema( $post_id );

		$this->assertArrayHasKey( 'hasPart', $article );
		$this->assertSame( 'SoftwareSourceCode', $article['hasPart'][0]['@type'] );
		$this->assertNotEmpty( $article['hasPart'][0]['text'] );
	}

}