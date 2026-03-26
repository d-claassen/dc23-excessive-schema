<?php
/**
 * Tests for Query Loop Schema enrichment.
 *
 * @package DC23\ExcessiveSchema
 */

declare( strict_types=1 );

namespace DC23\Tests\ExcessiveSchema;

use DC23\ExcessiveSchema\Query_Loop_Parser;
use DC23\ExcessiveSchema\Graph_Enricher;
use DC23\ExcessiveSchema\Schema_Helpers;

/**
 * Integration tests for Query Loop schema enrichment.
 */
final class Query_Loop_Schema_Test extends \WP_UnitTestCase {

	/**
	 * Query Loop Parser instance.
	 *
	 * @var Query_Loop_Parser
	 */
	private Query_Loop_Parser $parser;

	/**
	 * Graph Enricher instance.
	 *
	 * @var Graph_Enricher
	 */
	private Graph_Enricher $enricher;

	/**
	 * Test post IDs.
	 *
	 * @var array<int>
	 */
	private array $post_ids = [];

	/**
	 * Set up test environment.
	 *
	 * @return void
	 */
	public function set_up(): void {
		parent::set_up();

		// Create test posts.
		$this->post_ids = [
			self::factory()->post->create( [ 'post_title' => 'Test Post 1' ] ),
			self::factory()->post->create( [ 'post_title' => 'Test Post 2' ] ),
			self::factory()->post->create( [ 'post_title' => 'Test Post 3' ] ),
		];

		// Initialize plugin components.
		$this->parser   = new Query_Loop_Parser();
		$helpers        = new Schema_Helpers();
		$this->enricher = new Graph_Enricher( $this->parser, $helpers );

		$this->parser->register();
		$this->enricher->register();

		// Reset sections before each test.
		$this->parser->sections = [];
	}

	/**
	 * Override WordPress function that's incompatible with PHPUnit 10+.
	 *
	 * @return void
	 */
	public function expectDeprecated(): void {
	}

	/**
	 * Test Query Loop detection with heading in inner blocks.
	 *
	 * @return void
	 */
	public function test_query_loop_detection_with_heading(): void {
		$block_content = $this->create_query_loop_with_heading( 'Latest Posts' );
		$page_id       = $this->create_page_with_blocks( $block_content );

		// Render the page to trigger render_block filter.
		$this->go_to( get_permalink( $page_id ) );
		get_post( $page_id );
		do_blocks( get_post_field( 'post_content', $page_id ) );

		// Assert parser collected the section.
		$this->assertCount( 1, $this->parser->sections, 'Parser should collect 1 section' );

		$section = $this->parser->sections[0];
		$this->assertSame( 'Latest Posts', $section['name'], 'Section name should match heading' );
		$this->assertSame( 'post', $section['post_type'], 'Post type should be post' );
		$this->assertCount( 3, $section['post_ids'], 'Section should have 3 post IDs' );

		// Sort both arrays since Query Loop order doesn't matter for functionality.
		$expected_ids = $this->post_ids;
		$actual_ids   = $section['post_ids'];
		sort( $expected_ids );
		sort( $actual_ids );
		$this->assertSame( $expected_ids, $actual_ids, 'Post IDs should match created posts' );
	}

	/**
	 * Test Query Loop detection with block metadata name (no heading).
	 *
	 * @return void
	 */
	public function test_query_loop_detection_with_metadata_name(): void {
		$block_content = $this->create_query_loop_with_metadata_name( 'Featured Articles' );
		$page_id       = $this->create_page_with_blocks( $block_content );

		$this->go_to( get_permalink( $page_id ) );
		do_blocks( get_post_field( 'post_content', $page_id ) );

		$this->assertCount( 1, $this->parser->sections );
		$section = $this->parser->sections[0];
		$this->assertSame( 'Featured Articles', $section['name'], 'Should use metadata name' );
	}

	/**
	 * Test Query Loop detection with post type label fallback.
	 *
	 * @return void
	 */
	public function test_query_loop_detection_with_fallback_name(): void {
		$block_content = $this->create_basic_query_loop();
		$page_id       = $this->create_page_with_blocks( $block_content );

		$this->go_to( get_permalink( $page_id ) );
		do_blocks( get_post_field( 'post_content', $page_id ) );

		$this->assertCount( 1, $this->parser->sections );
		$section = $this->parser->sections[0];
		$this->assertSame( 'Posts', $section['name'], 'Should fallback to post type label' );
	}

	/**
	 * Test schema graph enrichment on CollectionPage.
	 *
	 * @return void
	 */
	public function test_schema_enrichment_on_collection_page(): void {
		// Set Yoast page type to CollectionPage.
		add_filter( 'wpseo_schema_webpage_type', fn() => 'CollectionPage' );

		$block_content = $this->create_query_loop_with_heading( 'Recent Articles' );
		$page_id       = $this->create_page_with_blocks( $block_content );

		$this->go_to( get_permalink( $page_id ) );

		// Reset sections to ensure clean slate for schema generation.
		// wpseo_head action will render blocks and collect sections.
		$this->parser->sections = [];

		// Get schema output.
		$schema = $this->get_yoast_schema_output();
		$graph  = $schema['@graph'];

		// Find WebPage node.
		$webpage = $this->find_node_by_type( $graph, 'CollectionPage' );
		$this->assertNotNull( $webpage, 'Should have CollectionPage node' );

		// Assert WebPage has hasPart property.
		$this->assertArrayHasKey( 'hasPart', $webpage, 'WebPage should have hasPart property' );
		$this->assertIsArray( $webpage['hasPart'], 'hasPart should be an array' );
		$this->assertCount( 1, $webpage['hasPart'], 'hasPart should reference 1 ItemList' );

		// Find ItemList node.
		$item_list = $this->find_node_by_type( $graph, 'ItemList' );
		$this->assertNotNull( $item_list, 'Should have ItemList node' );

		// Assert ItemList structure.
		$this->assertSame( 'Recent Articles', $item_list['name'], 'ItemList name should match heading' );
		$this->assertArrayHasKey( 'itemListElement', $item_list, 'ItemList should have items' );
		$this->assertCount( 3, $item_list['itemListElement'], 'ItemList should have 3 items' );

		// Verify ListItem structure.
		foreach ( $item_list['itemListElement'] as $index => $list_item ) {
			$this->assertSame( 'ListItem', $list_item['@type'], 'Should be ListItem type' );
			$this->assertSame( $index + 1, $list_item['position'], 'Position should be sequential' );
			$this->assertArrayHasKey( 'item', $list_item, 'ListItem should have item reference' );
			$this->assertArrayHasKey( '@id', $list_item['item'], 'Item should have @id' );

			// Verify @id points to article fragment.
			$expected_url = get_permalink( $this->post_ids[ $index ] ) . '#article';
			$this->assertSame( $expected_url, $list_item['item']['@id'], 'Should reference article @id' );
		}

		// Verify WebPage references ItemList.
		$list_id = $item_list['@id'];
		$this->assertSame( $list_id, $webpage['hasPart'][0]['@id'], 'WebPage should reference ItemList @id' );
	}

	/**
	 * Test schema enrichment on ProfilePage uses mentions instead of hasPart.
	 *
	 * @return void
	 */
	public function test_schema_enrichment_on_profile_page(): void {
		add_filter( 'wpseo_schema_webpage_type', fn() => 'ProfilePage' );

		$block_content = $this->create_query_loop_with_heading( 'My Articles' );
		$page_id       = $this->create_page_with_blocks( $block_content );

		$this->go_to( get_permalink( $page_id ) );

		// Reset sections for clean schema generation.
		$this->parser->sections = [];

		$schema  = $this->get_yoast_schema_output();
		$graph   = $schema['@graph'];
		$webpage = $this->find_node_by_type( $graph, 'ProfilePage' );

		$this->assertNotNull( $webpage, 'Should have ProfilePage node' );
		$this->assertArrayHasKey( 'mentions', $webpage, 'ProfilePage should use mentions property' );
		$this->assertArrayNotHasKey( 'hasPart', $webpage, 'ProfilePage should not have hasPart' );
	}

	/**
	 * Test multiple Query Loops on same page.
	 *
	 * @return void
	 */
	public function test_multiple_query_loops_on_same_page(): void {
		$block_content = $this->create_query_loop_with_heading( 'Featured Posts' ) . "\n\n"
					   . $this->create_query_loop_with_heading( 'Recent Updates' );

		$page_id = $this->create_page_with_blocks( $block_content );

		$this->go_to( get_permalink( $page_id ) );
		do_blocks( get_post_field( 'post_content', $page_id ) );

		// Assert parser collected 2 sections.
		$this->assertCount( 2, $this->parser->sections, 'Should collect 2 sections' );

		// Reset sections before schema generation to avoid duplicates.
		$this->parser->sections = [];

		$schema = $this->get_yoast_schema_output();
		$graph  = $schema['@graph'];

		// Find all ItemList nodes.
		$item_lists = array_filter( $graph, fn( $node ) => ( $node['@type'] ?? '' ) === 'ItemList' );
		$this->assertCount( 2, $item_lists, 'Should have 2 ItemList nodes' );

		// Verify WebPage references both.
		$webpage = $this->find_node_by_type( $graph, 'WebPage' );
		$this->assertCount( 2, $webpage['hasPart'] ?? [], 'WebPage should reference 2 ItemLists' );
	}

	/**
	 * Test empty Query Loop produces no schema.
	 *
	 * @return void
	 */
	public function test_empty_query_loop_no_schema(): void {
		// Delete all posts so Query Loop is empty.
		foreach ( $this->post_ids as $post_id ) {
			wp_delete_post( $post_id, true );
		}

		$block_content = $this->create_query_loop_with_heading( 'No Posts Here' );
		$page_id       = $this->create_page_with_blocks( $block_content );

		$this->go_to( get_permalink( $page_id ) );
		do_blocks( get_post_field( 'post_content', $page_id ) );

		// Parser should collect nothing (empty post_ids).
		$this->assertCount( 0, $this->parser->sections, 'Should not collect empty sections' );

		// Reset sections before schema generation.
		$this->parser->sections = [];

		$schema     = $this->get_yoast_schema_output();
		$graph      = $schema['@graph'];
		$item_lists = array_filter( $graph, fn( $node ) => ( $node['@type'] ?? '' ) === 'ItemList' );

		$this->assertCount( 0, $item_lists, 'Should have no ItemList nodes' );
	}

	/**
	 * Create a Query Loop block with a heading.
	 *
	 * @param string $heading_text Heading text.
	 *
	 * @return string Serialized block content.
	 */
	private function create_query_loop_with_heading( string $heading_text ): string {
		return sprintf(
			'<!-- wp:query {"queryId":1,"query":{"perPage":3,"pages":0,"offset":0,"postType":"post","order":"desc","orderBy":"date","author":"","search":"","exclude":[],"sticky":"","inherit":false}} -->
<div class="wp-block-query">
<!-- wp:heading -->
<h2 class="wp-block-heading">%s</h2>
<!-- /wp:heading -->
<!-- wp:post-template -->
<!-- wp:post-title /-->
<!-- wp:post-excerpt /-->
<!-- /wp:post-template -->
</div>
<!-- /wp:query -->',
			esc_html( $heading_text )
		);
	}

	/**
	 * Create a Query Loop block with metadata name.
	 *
	 * @param string $name Metadata name.
	 *
	 * @return string Serialized block content.
	 */
	private function create_query_loop_with_metadata_name( string $name ): string {
		return sprintf(
			'<!-- wp:query {"queryId":1,"query":{"perPage":3,"postType":"post"},"metadata":{"name":"%s"}} -->
<div class="wp-block-query">
<!-- wp:post-template -->
<!-- wp:post-title /-->
<!-- /wp:post-template -->
</div>
<!-- /wp:query -->',
			esc_attr( $name )
		);
	}

	/**
	 * Create a basic Query Loop block with no heading or metadata.
	 *
	 * @return string Serialized block content.
	 */
	private function create_basic_query_loop(): string {
		return '<!-- wp:query {"queryId":1,"query":{"perPage":3,"postType":"post"}} -->
<div class="wp-block-query">
<!-- wp:post-template -->
<!-- wp:post-title /-->
<!-- /wp:post-template -->
</div>
<!-- /wp:query -->';
	}

	/**
	 * Create a page with block content.
	 *
	 * @param string $blocks Serialized block content.
	 *
	 * @return int Page ID.
	 */
	private function create_page_with_blocks( string $blocks ): int {
		return self::factory()->post->create(
			[
				'post_type'    => 'page',
				'post_title'   => 'Test Page',
				'post_content' => $blocks,
				'post_status'  => 'publish',
			]
		);
	}

	/**
	 * Get Yoast schema output for current page.
	 *
	 * @return array Schema data.
	 */
	private function get_yoast_schema_output(): array {
		$json = $this->get_schema_json();

		return json_decode( $json, true );
	}

	/**
	 * Get schema JSON from Yoast output.
	 *
	 * @return string JSON-LD schema string.
	 */
	private function get_schema_json(): string {
		ob_start();
		do_action( 'wpseo_head' );
		$wpseo_head = ob_get_contents();
		ob_end_clean();

		$dom = new \DOMDocument();
		@$dom->loadHTML( $wpseo_head );
		$scripts = $dom->getElementsByTagName( 'script' );

		foreach ( $scripts as $script ) {
			if ( $script instanceof \DOMElement && $script->getAttribute( 'type' ) === 'application/ld+json' ) {
				return $script->textContent;
			}
		}

		throw new \LengthException( 'No schema script found in wpseo_head output.' );
	}

	/**
	 * Find a node in the graph by @type.
	 *
	 * @param array  $graph Schema graph.
	 * @param string $type  Schema type to find.
	 *
	 * @return array|null Node data or null.
	 */
	private function find_node_by_type( array $graph, string $type ): ?array {
		foreach ( $graph as $node ) {
			if ( ( $node['@type'] ?? '' ) === $type ) {
				return $node;
			}
		}

		return null;
	}
}
