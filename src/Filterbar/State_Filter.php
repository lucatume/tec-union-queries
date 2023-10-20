<?php

namespace TEC\Events\Innovation_Day\Union_Queries\Filterbar;

use Tribe\Events\Filterbar\Views\V2\Filters\State as Original_Filter;
use Tribe__Cache_Listener as Cache;

require_once __DIR__ . '/Venue_Attribute_Filter_Trait.php';

class State_Filter extends Original_Filter {
	use Venue_Attribute_Filter_Trait;

	protected function get_values() {
		$cache     = tribe_cache();
		$cache_key = __METHOD__;

		$cached = $cache->get( $cache_key, Cache::TRIGGER_SAVE_POST, false, DAY_IN_SECONDS );

		if ( is_array( $cached ) ) {
			return $cached;
		}

		$values = $this->fetch_venue_attribute_values( [ '_VenueState', '_VenueProvince' ] );

		$cache->set( $cache_key, $values, DAY_IN_SECONDS, Cache::TRIGGER_SAVE_POST );

		return $values;
	}

	protected function setup_join_clause() {
		$this->setup_venue_attribute_join_clause( [ '_VenueState', '_VenueProvince' ] );

	}

	protected function setup_where_clause() {
		// Nothing to do, filtered on JOIN.
	}
}
