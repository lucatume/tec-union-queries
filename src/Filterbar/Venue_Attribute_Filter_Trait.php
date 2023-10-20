<?php

namespace TEC\Events\Innovation_Day\Union_Queries\Filterbar;

use Tribe__Events__Venue as Venue;
use Tribe__Utils__Array as Arr;

/**
 * Trait Venue_Attribute_Filter_Trait.
 *
 * @since   TBD
 *
 * @package TEC\Events\Innovation_Day\Union_Queries\Filterbar;
 */
trait Venue_Attribute_Filter_Trait {
	protected function fetch_venue_attribute_values( $attribute_meta_key ): array {
		global $wpdb;

		$meta_keys          = (array) $attribute_meta_key;
		$meta_keys_interval = $wpdb->prepare(
			implode( ',', array_fill( 0, count( $meta_keys ), '%s' ) ),
			$meta_keys
		);

		// Return a Venue post ID representing each attribute.
		$results = $wpdb->get_results( $wpdb->prepare( "
			SELECT p.ID as ID, TRIM(attribute.meta_value) as attribute from {$wpdb->posts} p
			INNER JOIN {$wpdb->postmeta} attribute
				ON p.ID=attribute.post_id
				AND attribute.meta_key IN ({$meta_keys_interval})
				AND attribute.meta_value IS NOT NULL
				AND TRIM(attribute.meta_value) != ''
			WHERE p.post_type=%s
			GROUP BY TRIM(attribute.meta_value)
		", Venue::POSTTYPE ), OBJECT );

		if ( ! is_array( $results ) || empty( $results ) ) {
			return [];
		}

		return array_map( static function ( \stdClass $result ): array {
			return [
				'name'  => $result->attribute,
				'value' => $result->ID,
			];
		}, $results );
	}

	protected function setup_venue_attribute_join_clause( $attribute_meta_key ): void {
		$values = array_values(
			array_filter(
				Arr::list_to_array( $this->currentValue )
			)
		);

		if ( empty( $values ) ) {
			return;
		}

		global $wpdb;

		$meta_keys          = (array) $attribute_meta_key;
		$meta_keys_interval = $wpdb->prepare(
			implode( ',', array_fill( 0, count( $meta_keys ), '%s' ) ),
			$meta_keys
		);

		// Compile a list of attributes names we're looking for.
		// This is a fast query on a likely small number of IDs selected by the user.
		$values_interval  = implode( ',', $values );
		$attribute_values = $wpdb->get_col( "
			SELECT meta_value FROM {$wpdb->postmeta}
			WHERE post_ID IN ({$values_interval})
			AND meta_key IN ({$meta_keys_interval})
			AND meta_value IS NOT NULL
			AND TRIM(meta_value) != ''
			GROUP BY meta_value
		" );

		// Set up a sub-query to find all Events related to Venue that has one of the searched attributes.
		$attribute_values = $wpdb->prepare(
			implode( ',', array_fill( 0, count( $attribute_values ), '%s' ) ),
			$attribute_values
		);
		$this->joinClause = $wpdb->prepare(
			"INNER JOIN {$wpdb->postmeta}
			AS attribute_filter
			ON ({$wpdb->posts}.ID = attribute_filter.post_id
			AND attribute_filter.meta_key = '_EventVenueID'
			AND attribute_filter.meta_value IN (
				SELECT venue.ID FROM {$wpdb->posts} venue
				INNER JOIN {$wpdb->postmeta} venue_attribute_meta
					ON venue.ID = venue_attribute_meta.post_id
					AND venue_attribute_meta.meta_key IN ({$meta_keys_interval})
					AND venue_attribute_meta.meta_value IN ({$attribute_values})
				WHERE venue.post_type = %s 
			))",
			Venue::POSTTYPE
		);
	}
}
