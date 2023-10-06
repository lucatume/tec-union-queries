<?php
/**
 * Plugin Name: The Events Calendar - Better Queries
 * Description: A feature plugin to test the use of better queries in The Events Calendar. The goal is to make the
 * queries more performant and easier to maintain. Version: 0.0.4
 */

use TEC\Events\Innovation_Day\Union_Queries\Migrate_Command;
use TEC\Events\Innovation_Day\Union_Queries\Migrations\Move_Cost_Meta_To_Events_Table;
use TEC\Events\Innovation_Day\Union_Queries\Month_View_Filters;
use TEC\Events\Innovation_Day\Union_Queries\Filterbar_Filters;

require_once __DIR__ . '/src/Month_View_Filters.php';
require_once __DIR__ . '/src/Filterbar_Filters.php';

$month_view_filters = new  Month_View_Filters();
add_filter( 'tribe_events_views_v2_by_day_view_grid_days', [ $month_view_filters, 'get_grid_days' ], 1000, 4 );
// Hook int pretty late to replace the repository with a dummy one.
add_filter( 'tribe_events_views_v2_by_day_view_day_repository', [
	$month_view_filters,
	'filter_by_view_day_repository'
], 1000, 4 );
add_filter( 'tribe_events_views_v2_by_day_view_grid_days_from_repository', [
	$month_view_filters,
	'get_grid_days_from_repository'
], 1000, 4 );
add_filter( 'tribe_events_views_v2_by_day_view_days_data', [ $month_view_filters, 'get_days_data' ], 1000, 3 );

$filterbar_filters = new Filterbar_Filters();
add_filter( 'tribe_events_filter_bar_context_to_filter_map', [ $filterbar_filters, 'filter_context_to_filter_map' ] );
add_filter( 'tribe_events_filter_bar_option_key_map', [ $filterbar_filters, 'filter_option_key_map' ] );

if ( defined( 'WP_CLI' ) && WP_CLI ) {
	require_once __DIR__ . '/src/Migrate_Command.php';
	require_once __DIR__ . '/src/Migrations/Migration_Interface.php';
	require_once __DIR__ . '/src/Migrations/Move_Cost_Meta_To_Events_Table.php';

	// Engineering said they love short closures, so here we are.
	add_filter( 'tec_migrations', fn() => [
		Move_Cost_Meta_To_Events_Table::class
	] );

	// Called like the Laravel commands, we're cool kids now.
	WP_CLI::add_command( 'migrate', fn() => ( new Migrate_Command() )->migrate(), [
		'shortdesc' => 'Run migrations'
	] );
	WP_CLI::add_command( 'migrate:rollback', fn() => ( new Migrate_Command() )->rollback(), [
		'shortdesc' => 'Rollback migrations'
	] );
}

/*
 * Next:
 * - Connect cost filter to the Events table `cost` column.
 * - Go through all FilterBar filters and make sure they are using either the Events or the Occurrences table.
 */
