<?php

namespace TEC\Events\Innovation_Day\Union_Queries\Filterbar;

use TEC\Events\Custom_Tables\V1\Tables\Occurrences;
use Tribe\Events\Filterbar\Views\V2\Filters\Day_Of_Week as Original_Filter;
use Tribe__Date_Utils as Dates;

class Day_Of_Week_Filter extends Original_Filter {
	protected function setup_join_clause() {
		// Nothing to join on: the query is already JOINed on the `wp_tec_occurrences` table.
	}

	protected function setup_where_clause() {
		$clauses     = [];
		$values      = array_map( 'intval', $this->currentValue );
		$values      = implode( ',', $values );
		$occurrences = Occurrences::table_name();

		$eod_cutoff = tribe_get_option( 'multiDayCutoff', '00:00' );
		if ( $eod_cutoff !== '00:00' ) {
			$eod_time_difference = Dates::time_between( '1/1/2014 00:00:00', "1/1/2014 {$eod_cutoff}:00" );
			$start_date          = "DATE_SUB($occurrences.start_date, INTERVAL {$eod_time_difference} SECOND)";
			$end_date            = "DATE_SUB($occurrences.end_date, INTERVAL {$eod_time_difference} SECOND)";
		} else {
			$start_date = "$occurrences.start_date";
			$end_date   = "$occurrences.end_date";
		}

		$clauses[] = "(DAYOFWEEK($start_date) IN ($values))";

		// is it on at least 7 days (first day is 0)
		$clauses[] = "(DATEDIFF($end_date, $start_date) >=6)";

		// determine if the start of the nearest matching day is between the start and end dates
		$distance_to_day = [];
		foreach ( $this->currentValue as $day_of_week_index ) {
			$day_of_week_index = (int) $day_of_week_index;
			$distance_to_day[] = "MOD( 7 + $day_of_week_index - DAYOFWEEK($start_date), 7 )";
		}
		if ( count( $distance_to_day ) > 1 ) {
			$distance_to_next_matching_day = 'LEAST(' . implode( ',', $distance_to_day ) . ')';
		} else {
			$distance_to_next_matching_day = reset( $distance_to_day );
		}
		$clauses[] = "(DATE(DATE_ADD($start_date, INTERVAL $distance_to_next_matching_day DAY)) < $end_date)";

		$this->whereClause = ' AND (' . implode( ' OR ', $clauses ) . ')';
	}
}
