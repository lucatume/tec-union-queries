<?php

namespace TEC\Events\Innovation_Day\Union_Queries\Filterbar;

use Tribe\Events\Filterbar\Views\V2\Filters\Organizer as Original_Filter;
use Tribe__Utils__Array as Arr;

class Organizer_Filter extends Original_Filter {
	protected function setup_join_clause() {
		$organizer_ids = array_values(
			array_filter(
				Arr::list_to_array( $this->currentValue ),
				static function ( $value ) {
					return is_numeric( $value ) && ! empty( $value );
				}
			)
		);

		if ( ! count( $organizer_ids ) ) {
			return;
		}

		$organizers_ids_interval = implode( ',', $organizer_ids );

		global $wpdb;
		$this->joinClause =
			"INNER JOIN {$wpdb->postmeta}
			AS organizer_filter
			ON ({$wpdb->posts}.ID = organizer_filter.post_id
			AND organizer_filter.meta_key = '_EventOrganizerID'
			AND organizer_filter.meta_value IN ({$organizers_ids_interval})
			)";
	}

	protected function setup_where_clause() {
		// Nothing to do, filtered on JOIN.
	}
}
