<?php

namespace TEC\Events\Innovation_Day\Union_Queries\Filterbar;

use TEC\Events\Custom_Tables\V1\Tables\Occurrences;
use Tribe\Events\Filterbar\Views\V2\Filters\Time_Of_Day as Original_Filter;

class Time_Of_Day_Filter extends Original_Filter {
	protected function setup_join_clause() {
		// Nothing to join on: the query is already JOINed on the `wp_tec_occurrences` table.
	}

	protected function setup_where_clause() {
		global $wpdb;
		$clauses = [];
		$values  = $this->currentValue;
		$occurrences = Occurrences::table_name();

		// They've checked _everything_, let's not pile on the JOINs.
		if ( empty( $values ) || count( $values ) === count( $this->get_values() ) ) {
			return;
		}

		foreach ( $values as $time_range_string ) {
			if ( $time_range_string === 'allday' ) {
				continue; // handled later.
			}

			$time_range_frags = explode( '-', $time_range_string );
			$range_start_hour = $time_range_frags[0];
			$range_end_hour   = $time_range_frags[1];
			$range_start_time = $time_range_frags[0] . ':00:00';
			$range_end_time   = $time_range_frags[1] . ':00:00';

			$is_overnight_range = $range_start_hour > $range_end_hour;
			if ( $is_overnight_range ) {
				$clauses[] = $wpdb->prepare(
					"(
						( TIME(CAST($occurrences.start_date as DATETIME)) < %s )
						OR ( TIME(CAST($occurrences.start_date as DATETIME)) >= %s )
						OR ( MOD(TIME_TO_SEC(TIMEDIFF(%s, TIME(CAST($occurrences.start_date as DATETIME)))) + 86400, 86400) < $occurrences.duration )
					)",
					$range_end_time,
					$range_start_time,
					$range_end_time
				);
			} else {
				$clauses[] = $wpdb->prepare(
					"(
						( TIME(CAST($occurrences.start_date as DATETIME)) >= %s AND TIME(CAST($occurrences.start_date as DATETIME)) < %s )
						OR ( MOD(TIME_TO_SEC(TIMEDIFF(%s, TIME(CAST($occurrences.start_date as DATETIME)))) + 86400, 86400) < $occurrences.duration )
					)",
					$range_start_time,
					$range_end_time,
					$range_start_time
				);
			}
		}

		// To avoid a JOIN on the `wp_postmeta` table, we'll discern all-day events as those that have a duration that
		//is an exact multiple of 86400 seconds (24 hours).
		if ( in_array( 'allday', $values ) ) {
			$clauses[] = "( $occurrences.duration % 86400 = 0 )";
		} else {
			$this->whereClause .= " AND ( $occurrences.duration % 86400 != 0 ) ";
		}

		$this->whereClause .= ' AND (' . implode( ' OR ', $clauses ) . ')';
	}
}
