<?php

namespace TEC\Events\Innovation_Day\Union_Queries\Filterbar;

use Tribe\Events\Filterbar\Views\V2\Filters\Featured_Events as Original_Filter;
use Tribe__Events__Featured_Events as Featured;

class Featured_Filter extends Original_Filter {
	protected function setup_join_clause() {
		global $wpdb;

		$this->joinClause = $wpdb->prepare( "
			INNER JOIN {$wpdb->postmeta} AS featured_event_meta
			ON {$wpdb->posts}.ID = featured_event_meta.post_id
			AND featured_event_meta.meta_key = %s
			AND featured_event_meta.meta_value = '1'",
			Featured::FEATURED_EVENT_KEY
		);
	}

	protected function setup_where_clause() {
		// Nothing to do, filtered on JOIN.
	}
}
