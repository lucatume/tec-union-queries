<?php

namespace TEC\Events\Innovation_Day\Union_Queries\Filterbar;

use Tribe\Events\Filterbar\Views\V2\Filters\Venue as Original_Filter;
use Tribe__Utils__Array as Arr;

class Venue_Filter extends Original_Filter {
	protected function setup_join_clause() {
		$venue_ids = array_values(
			array_filter(
				Arr::list_to_array( $this->currentValue ),
				static function ( $value ) {
					return is_numeric( $value ) && ! empty( $value );
				}
			)
		);

		if ( ! count( $venue_ids ) ) {
			return;
		}

		$venue_ids_interval = implode( ',', $venue_ids );

		global $wpdb;
		$this->joinClause =
			"INNER JOIN {$wpdb->postmeta}
			AS venue_filter
			ON ({$wpdb->posts}.ID = venue_filter.post_id
			AND venue_filter.meta_key = '_EventVenueID'
			AND venue_filter.meta_value IN ({$venue_ids_interval})
			)";
	}

	protected function setup_where_clause() {
		// Nothing to do, filtered on JOIN.
	}
}
