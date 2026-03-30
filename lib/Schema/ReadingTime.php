<?php

namespace DC23\ExcessiveSchema\Schema;

use Yoast\WP\SEO\Context\Meta_Tags_Context;

final class ReadingTime {

	public function register(): void {
		\add_filter( 'wpseo_schema_webpage', [ $this, 'add_reading_time_to_webpage' ], 11, 2 );
	}

	/**
	 * Enhance the WebPage with a timeRequired property.
	 *
	 * @template T of array{"@type": string}
	 *
	 * @param T                 $webpage_data The WebPage schema piece.
	 * @param Meta_Tags_Context $context      The page context.
	 *
	 * @return T|(T&array{timeRequired: string}) The original or enhanced WebPage piece.
	 */
	public function add_reading_time_to_webpage( $webpage_data, $context ) {
		\assert( $context instanceof Meta_Tags_Context );
		if ( ! $context->has_article ) {
			return $webpage_data;
		}

		$reading_time = $context->presentation->estimated_reading_time_minutes;
		if ( $reading_time <= 0 ) {
			return $webpage_data;
		}

		$webpage_data['timeRequired'] = \sprintf( 'PT%dM', $reading_time );

		return $webpage_data;
	}
}
