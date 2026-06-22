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
		$code_snippet = <<<'CODE'
		&lt;?php
				function greet( string $name ): string {
					return "Hello {$name}";
				}
				
				echo greet( 'Reader' );
		CODE;
		$post_id = self::factory()->post->create( [
			'post_status'  => 'publish',
			'post_content' => sprintf(
				<<<'GB_HTML'
				<!-- wp:code -->
					<pre class="wp-block-code"><code>%s</code></pre>
				<!-- /wp:code -->
				GB_HTML,
				$code_snippet,
			),
		] );
		
		// Update object to persist meta value to indexable.
		self::factory()->post->update_object( $post_id, [] );

		$this->go_to( \get_permalink( $post_id ) );

		$schema = $this->get_schema( $post_id );

		$article = null;
		foreach ( $schema['@graph'] ?? [] as $piece ) {
			if ( isset( $piece['@type'] ) && $piece['@type'] === 'Article' ) {
				$article = $piece;
				break;
			}
		}
		
		$this->assertArrayHasKey( 'hasPart', $article );
		$this->assertNotEmpty( $article['hasPart'][0]['@id'] );
		
		$code = null;
		foreach ( $schema['@graph'] ?? [] as $piece ) {
			if ( isset( $piece['@type'] ) && $piece['@id'] === $article['hasPart'][0]['@id'] ) {
				$code = $piece;
				break;
			}
		}
		
		$this->assertSame( 'SoftwareSourceCode', $code['@type'] );
		$this->assertSame( $code_snippet, $code['text'] );
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