<?php

namespace TEC\Events\Innovation_Day\Union_Queries\Migrated\Filterbar;

use Tribe\Events\Filterbar\Views\V2\Filters\Cost as Original_Filter;

class Cost_Filter extends Original_Filter {
	protected function setup_join_clause() {
		// Join on the `wp_tec_events` table, which has the `cost` column.
		$this->joinClause = 'INNER JOIN wp_tec_events ON wp_tec_events.id = wp_tec_occurrences.event_id';
	}

	protected function setup_where_clause() {
		global $wpdb;
		$clauses = [];
		$values  = $this->currentValue;
		$events  = 'wp_tec_events';

		// They've checked _everything_, let's not pile on the JOINs.
		if ( empty( $values ) ) {
			return;
		}

		foreach ( $values as $cost_range_string ) {
			if ( $cost_range_string === 'all' ) {
				continue; // handled later.
			}

			$cost_range_frags = explode( '-', $cost_range_string );
			$range_start_cost = $cost_range_frags[0];
			$range_end_cost   = $cost_range_frags[1];

			$clauses[] = $wpdb->prepare(
				"(
					( $events.cost >= %s AND $events.cost <= %s )
				)",
				$range_start_cost,
				$range_end_cost
			);
		}

		// To avoid a JOIN on the `wp_postmeta` table, we'll discern all-day events as those that have a duration that
		// spans the entire day.
		if ( in_array( 'all', $values, true ) ) {
			$clauses[] = "$events.cost = 0";
		}

		$this->whereClause = implode( ' OR ', $clauses );
	}
}
