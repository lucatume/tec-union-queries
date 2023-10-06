<?php

namespace TEC\Events\Innovation_Day\Union_Queries\Migrations;

use TEC\Common\StellarWP\DB\DB;
use TEC\Events\Custom_Tables\V1\Tables\Events;

class Move_Cost_Meta_To_Events_Table implements Migration_Interface {

	public function up(): void {
		$events = Events::table_name();
		global $wpdb;

		// Add the `cost` column to the Events table if not already there.
		DB::query( "ALTER TABLE $events ADD cost DECIMAL(10,2) NOT NULL DEFAULT 0" );

		// From the `wp_postmeta` table, move the `_EventCost` meta value to the `cost` column in the `wp_tribe_events`
		DB::query( "UPDATE $events AS e
			LEFT JOIN $wpdb->postmeta AS pm ON e.post_id = pm.post_id
			AND pm.meta_key = '_EventCost'
			SET e.cost = COALESCE(pm.meta_value, 0)" );
	}

	public function down(): void {
		$events = Events::table_name();

		// Remove the `cost` column from the Events table.
		DB::query( "ALTER TABLE $events DROP COLUMN cost" );
	}
}
