<?php

namespace TEC\Events\Innovation_Day\Union_Queries;

use TEC\Events\Innovation_Day\Union_Queries\Filterbar\Day_Of_Week_Filter;
use TEC\Events\Innovation_Day\Union_Queries\Filterbar\Time_Of_Day_Filter;


class Filterbar_Filters {
	public function filter_context_to_filter_map( array $map ): array {
		// Old style!
		require_once __DIR__ . '/Filterbar/Day_Of_Week_Filter.php';
		require_once __DIR__ . '/Filterbar/Time_Of_Day_Filter.php';

		$map['filterbar_day_of_week'] = Day_Of_Week_Filter::class;
		$map['filterbar_time_of_day'] = Time_Of_Day_Filter::class;

		return $map;
	}

	public function filter_option_key_map( array $map ): array {
		$map[ Day_Of_Week_Filter::class ] = 'dayofweek';
		$map[ Time_Of_Day_Filter::class ] = 'timeofday';

		return $map;
	}
}
