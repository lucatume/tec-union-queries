<?php

namespace TEC\Events\Innovation_Day\Union_Queries;

use TEC\Events\Innovation_Day\Union_Queries\Filterbar\Day_Of_Week_Filter;
use TEC\Events\Innovation_Day\Union_Queries\Filterbar\Null_Filter;
use TEC\Events\Innovation_Day\Union_Queries\Filterbar\Time_Of_Day_Filter;
use Tribe\Events\Filterbar\Views\V2\Filters\Category as Category_Filter;
use Tribe\Events\Filterbar\Views\V2\Filters\Cost as Cost_Filter;
use Tribe\Events\Filterbar\Views\V2\Filters\Tag as Tag_Filter;
use TEC\Events\Innovation_Day\Union_Queries\Filterbar\Venue_Filter;
use TEC\Events\Innovation_Day\Union_Queries\Filterbar\Organizer_Filter;
use TEC\Events\Innovation_Day\Union_Queries\Filterbar\Country_Filter;
use TEC\Events\Innovation_Day\Union_Queries\Filterbar\City_Filter;
use TEC\Events\Innovation_Day\Union_Queries\Filterbar\State_Filter;
use TEC\Events\Innovation_Day\Union_Queries\Filterbar\Featured_Filter;


class Filterbar_Filters {
	public function filter_context_to_filter_map( array $map ): array {
		// Old style!
		require_once __DIR__ . '/Filterbar/Null_Filter.php';
		require_once __DIR__ . '/Filterbar/Day_Of_Week_Filter.php';
		require_once __DIR__ . '/Filterbar/Time_Of_Day_Filter.php';
		require_once __DIR__ . '/Filterbar/Venue_Filter.php';
		require_once __DIR__ . '/Filterbar/Organizer_Filter.php';
		require_once __DIR__ . '/Filterbar/Country_Filter.php';
		require_once __DIR__ . '/Filterbar/City_Filter.php';
		require_once __DIR__ . '/Filterbar/State_Filter.php';
		require_once __DIR__ . '/Filterbar/Featured_Filter.php';

		$map['filterbar_day_of_week']    = Day_Of_Week_Filter::class;
		$map['filterbar_time_of_day']    = Time_Of_Day_Filter::class;
		$map['filterbar_category']       = Category_Filter::class; // The original one, set for clarity.
		$map['filterbar_cost']           = Cost_Filter::class; // The original one, set for clarity.
		$map['filterbar_tag']            = Tag_Filter::class; // The original one, set for clarity.
		$map['filterbar_venue']          = Venue_Filter::class;
		$map['filterbar_organizer']      = Organizer_Filter::class;
		$map['filterbar_country']        = Country_Filter::class;
		$map['filterbar_city']           = City_Filter::class;
		$map['filterbar_state_province'] = State_Filter::class;
		$map['filterbar_featured']       = Featured_Filter::class;

		return $map;
	}

	public function filter_option_key_map( array $map ): array {
		$map[ Day_Of_Week_Filter::class ] = 'dayofweek';
		$map[ Time_Of_Day_Filter::class ] = 'timeofday';
		$map[ Category_Filter::class ]    = 'eventcategory'; // The original one, set for clarity.
		$map[ Cost_Filter::class ]        = 'cost'; // The original one, set for clarity.
		$map[ Tag_Filter::class ]         = 'tags'; // The original one, set for clarity.
		$map[ Venue_Filter::class ]       = 'venues';
		$map[ Organizer_Filter::class ]   = 'organizers';
		$map[ Country_Filter::class ]     = 'country';
		$map[ City_Filter::class ]        = 'city';
		$map[ State_Filter::class ]       = 'state';
		$map[ Featured_Filter::class ]    = 'featuredevent';

		return $map;
	}
}
