<?php

namespace TEC\Events\Innovation_Day\Union_Queries;

use DateInterval;
use DatePeriod;
use DateTime;
use DateTimeImmutable;
use DateTimeInterface;
use stdClass;
use Tribe\Events\Views\V2\Utils\Stack;
use Tribe\Events\Views\V2\Views\By_Day_View;
use Tribe\Events\Views\V2\Views\Day_View;
use WP_Post;
use Tribe__Date_Utils as Dates;
use Tribe__Repository__Interface as Repository_Interface;
use Tribe__Repository__Decorator as Repository_Decorator;

class Month_View_Filters {
	/**
	 * The interval of days that will be used to fetch the events.
	 *
	 * @since TBD
	 *
	 * @var DatePeriod<DateTimeImmutable>|null
	 */
	private ?DatePeriod $days_interval;

	/**
	 * A request cache for the grid days by cache key.
	 *
	 * @var array<string, array<string,int[]>>
	 */
	private array $cached_grid_days = [];

	/**
	 * A request cache for the days data by cache key.
	 *
	 * @since TBD
	 *
	 * @var array<string,array<string,mixed>>
	 */
	private array $cache_days_data = [];

	/**
	 * A map from dates in the `Y-m-d` format to the number of events found for that date.
	 *
	 * @var array<string,int>
	 */
	private array $found = [];

	/**
	 * A map of each day sub-query for the union query.
	 *
	 * @since TBD
	 *
	 * @var array<string,string>
	 */
	private ?array $union_queries = [];

	/**
	 * The number of Events to fetch, at most, for each page.
	 *
	 * @since TBD
	 *
	 * @var int
	 */
	private ?int $per_page;

	private function generate_union_queries( string $query_template, string $start_date, string $end_date, string $day_cutoff = '00:00:00', int $limit = 10 ): void {
		$start               = new DateTime( $start_date );
		$end                 = new DateTime( $end_date );
		$days                = $this->build_days_interval( $start, $end );
		$this->days_interval = $days;
		$one_day             = new DateInterval( 'P1D' );
		$one_second          = new DateInterval( 'PT1S' );

		$union_queries = [];
		foreach ( $days as $day ) {
			$start_beginning_of_day = new DateTime( $day->format( 'Y-m-d ' . $day_cutoff ) );
			$end_end_of_day         = ( clone $day )->add( $one_day )->sub( $one_second );

			$search                                   = [
				'{{start_beginning_of_day}}',
				'{{end_end_of_day}}',
				'{{start_date}}',
				'{{end_date}}',
				'{{day_date}}',
			];
			$replace                                  = [
				$start_beginning_of_day->format( 'Y-m-d H:i:s' ),
				$end_end_of_day->format( 'Y-m-d H:i:s' ),
				$start_beginning_of_day->format( 'Y-m-d' ),
				$end_end_of_day->format( 'Y-m-d' ),
				$day->format( 'Y-m-d' ),
			];
			$union_queries[ $day->format( 'Y-m-d' ) ] = '(' . str_replace( $search, $replace, $query_template ) . ')';
		}

		$this->union_queries = $union_queries;
	}

	private function reset_state(): void {
		$this->union_queries = [];
		$this->days_interval = null;
		$this->per_page      = null;
		$this->found         = [];
	}

	public function filter_by_view_day_repository(
		Repository_Interface $events_repository,
		\DateTimeInterface $grid_start,
		\DateTimeInterface $grid_end,
		By_Day_View $view
	): Repository_Interface {
		$this->reset_state();

		// Run the repository query, set the LIMIT to 1; the LIMIT cannot bet set to 0.
		// We run the query to get the actual SQL that did run.
		// Filters on the `posts_pre_query` filter (e.g. CT1) will run sep. queries and report
		// the used SQL; here we trade efficiency (not running the query) for accuracy (getting the actual SQL).
		$results = $events_repository
			->offset( 0 )->per_page( 1 )
			->set_found_rows( false )
			->all();

		if ( count( $results ) === 0 ) {
			// Skip the circus if there are no results.
			$this->days_interval = $this->build_days_interval( $grid_start, $grid_end );

			return $this->get_repository_decorator( $events_repository );
		}

		// Get the SQL that just ran.
		$last_sql = $events_repository->get_last_sql();

		$day_cutoff = tribe_get_option( 'multiDayCutoff', '00:00' );
		if ( ! preg_match( '/\d{2}:\d{2}/', $day_cutoff ) ) {
			$day_cutoff = '00:00';
		}
		$day_cutoff = $day_cutoff . ':00';

		// Build some dates required to create the template.
		$one_day                = new DateInterval( 'P1D' );
		$one_second             = new DateInterval( 'PT1S' );
		$start_beginning_of_day = new DateTime( $grid_start->format( 'Y-m-d ' . $day_cutoff ) );
		$start_end_of_day       = ( clone $start_beginning_of_day )->add( $one_day )->sub( $one_second );
		$end_beginning_of_day   = new DateTime( $grid_end->format( 'Y-m-d ' . $day_cutoff ) );
		$end_end_of_day         = ( clone $end_beginning_of_day )->add( $one_day )->sub( $one_second );

		// Compile a list of permutations of the dates that might be contained in the query.
		// Sort them by length, DESC.
		$search         = [
			$start_beginning_of_day->format( 'Y-m-d H:i:s' ),
			$end_end_of_day->format( 'Y-m-d H:i:s' ),
			$start_beginning_of_day->format( 'Y-m-d' ),
			$end_beginning_of_day->format( 'Y-m-d' ),
		];
		$replace        = [
			'{{start_beginning_of_day}}',
			'{{end_end_of_day}}',
			'{{start_date}}',
			'{{end_date}}',
		];
		$query_template = str_replace( $search, $replace, $last_sql );

		// Prepend '{{day_date}} AS day_date' to the `SELECT` fields.
		$select = 'SELECT "{{day_date}}" AS day_date, ';
		if ( str_contains( $query_template, 'SQL_CALC_FOUND_ROWS' ) ) {
			$query_template = preg_replace( '/SELECT SQL_CALC_FOUND_ROWS /', $select, $query_template );
		} else {
			$query_template = preg_replace( '/SELECT /', $select, $query_template );
		}

		// Replace the `LIMIT` clause with `LIMIT {{limit}}`: due to filtering, this is the only safe way to do it.
		$query_template = preg_replace( '/LIMIT (\d+\s*,)*\s*\d+?/', '{{limit}}', $query_template );

		// Set the `per_page` from the view.
		$per_page = $view->get_context()->get( 'events_per_day' );

		// If the view `per_page` value is inconsistent, read from the options.
		if ( ! ( is_numeric( $per_page ) && $per_page >= 1 ) ) {
			$per_page = tribe_get_option( 'monthEventAmount', get_option( 'posts_per_page', 10 ) );
		}

		// Sanity check.
		$this->per_page = is_numeric( $per_page ) && $per_page >= 1 ? (int) $per_page : 10;

		// Generate the union query, but do not run it yet.
		$this->generate_union_queries(
			$query_template,
			$grid_start->format( 'Y-m-d' ),
			$grid_end->format( 'Y-m-d' ),
			$day_cutoff,
			$this->per_page
		);

		// The filter requires to return a repository, return one that will do essentially nothing.
		return $this->get_repository_decorator( $events_repository );
	}

	public function get_grid_days( array $grid_days = null, DateTimeInterface $grid_start, DateTimeInterface $grid_end, By_Day_View $view ): ?array {
		$cache_key = sprintf( '%s_%s_%s', get_class( $view ), $grid_start->format( 'Y-m-d' ), $grid_end->format( 'Y-m-d' ) );

		if ( isset( $this->cached_grid_days[ $cache_key ] ) && is_array( $this->cached_grid_days[ $cache_key ] ) ) {
			return $this->cached_grid_days[ $cache_key ];
		}

		// Let the default logic run: we're  filtering later.
		return null;
	}

	public function get_grid_days_from_repository( array $grid_days = null, DateTimeInterface $grid_start, DateTimeInterface $grid_end, By_Day_View $view ): array {
		$view_class = get_class( $view );
		$cache_key  = sprintf( '%s_%s_%s', $view_class, $grid_start->format( 'Y-m-d' ), $grid_end->format( 'Y-m-d' ) );

		if ( isset( $this->cached_grid_days[ $cache_key ] ) && is_array( $this->cached_grid_days[ $cache_key ] ) ) {
			return $this->cached_grid_days[ $cache_key ];
		}

		return $this->fetch_grid_days( $cache_key, $view_class );
	}

	private function fetch_results( int $chunk_size = 7 ): array {
		if ( ! count( $this->union_queries ) ) {
			return [];
		}

		$query_sets             = $this->union_queries;
		$result_sets            = [];
		$required_found_queries = [];
		global $wpdb;

		do {
			$query_set      = array_splice( $query_sets, 0, $chunk_size );
			$query_set_days = array_keys( $query_set );
			$query_template = implode( ' UNION ALL ', $query_set );
			$query          = str_replace( '{{limit}}', 'LIMIT ' . $this->per_page, $query_template );

			$result_set = $wpdb->get_results( $query );

			foreach ( $query_set_days as $day_date ) {
				$day_count = count( array_filter( $result_set, fn( stdClass $result ) => $result->day_date === $day_date ) );

				/*
				 * If the size of the result set is greater than, or equal, to the per_page limit we need to run another
				 * query to know how many events would have beed found for this day if no LIMIT was applied.
				 */
				if ( $day_count === $this->per_page ) {
					// Remove the LIMIT.
					$found_query = str_replace( '{{limit}}', '', $this->union_queries[ $day_date ] );

					// Replace all the SELECT fields with `SELECT COUNT(*)` in the query; only the first: the query might contain sub-queries.
					$found_query = preg_replace( '/SELECT.*FROM/ius', "SELECT '$day_date' as day_date, COUNT(*) AS found FROM", $found_query, 1 );

					// The query will contain a `GROUP BY ... wp_tec_occurrences.occurrence_id`: let's use that as canary.
					// But it might contain more than one in the sub-queries, so we need to replace only the last one with `GROUP BY day_date`.
					// First let's find out where the last `GROUP BY` is.
					$last_group_by_pos = strripos( $found_query, 'GROUP BY' );

					if ( $last_group_by_pos === false ) {
						// This is a weird query: calculate the found value immediately.
						// Re-run the query with a LIMIT of 1.
						$found_query = str_replace( '{{limit}}', 'LIMIT 1', $this->union_queries[ $day_date ] );
						$wpdb->query( $found_query );
						$this->found[ $day_date ] = $wpdb->num_rows;

						continue;
					}

					// Now we'll operate only on this string slice.
					$last_group_by = substr( $found_query, $last_group_by_pos );
					// Second let's find out if there is an `ORDER BY` after the last `GROUP BY`.
					$last_order_by_pos = strripos( $last_group_by, 'ORDER BY' );
					// Replace the last `ORDER BY` with `ORDER BY day_date`, if it exists.
					if ( $last_order_by_pos !== false ) {
						$last_group_by = substr_replace( $last_group_by, 'ORDER BY day_date', $last_order_by_pos );
					}
					// Replace the last `GROUP BY` with `GROUP BY day_date`.
					$found_query = substr_replace( $found_query, 'GROUP BY day_date', $last_group_by_pos );
					// Re-add the final ')': it was removed by the `substr_replace` call.
					$found_query .= ')';

					$required_found_queries [] = $found_query;
				} else {
					$this->found[ $day_date ] = $day_count;
				}
			}

			$result_sets[] = $result_set;
		} while ( count( $query_sets ) );

		if ( count( $result_sets ) > 1 ) {
			$results = array_merge( ...$result_sets );
		} else {
			$first   = (array) $result_sets[0];
			$results = ( array ) reset( $first );
		}

		if ( count( $results ) === 0 ) {
			return [];
		}

		if ( count( $required_found_queries ) ) {
			// Run the queries to get the number of events found for each day in chunks.
			$found_results = [];

			do {
				$found_query_set = array_splice( $required_found_queries, 0, $chunk_size );
				$found_query     = implode( ' UNION ALL ', $found_query_set );
				$found_results[] = $wpdb->get_results( $found_query );
			} while ( count( $required_found_queries ) );

			if ( count( $found_results ) === 1 ) {
				$found_results = $found_results[0];
			} else if ( count( $found_results ) > 1 ) {
				$found_results = array_merge( ...$found_results );
			}

			foreach ( $found_results as $found_result ) {
				$this->found[ $found_result->day_date ] = $found_result->found;
			}
		}

		return $results;
	}

	public function fetch_grid_days( string $cache_key = null, string $view_class ): array {
		$results = $this->fetch_results();

		$grid_days = [];
		foreach ( $this->days_interval as $day ) {
			// From the results grab all the entries that have `day_date` equal to the current day.
			$day_results = array_filter( $results, function ( $result ) use ( $day ) {
				return $result->day_date === $day->format( 'Y-m-d' );
			} );

			// Populate the map in the required format: [Y-m-d] => [...occurrence_ids].
			$grid_days[ $day->format( 'Y-m-d' ) ] = array_map( fn( $result ) => $result->occurrence_id, $day_results );
		}

		if ( $cache_key ) {
			$this->cached_grid_days[ $cache_key ] = $grid_days;
		}

		return $grid_days;
	}

	private function get_repository_decorator( Repository_Interface $events_repository ): Repository_Decorator {
		return new  class( $events_repository ) extends Repository_Decorator {
			public function __construct( Repository_Interface $decorated ) {
				$this->decorated = $decorated;
			}

			// Do not run a query, we'll get the correct results in the `get_day_results` method.
			public function all() {
				return [];
			}
		};
	}

	public function get_days_data( ?array $days_data, array $grid_days = [], By_Day_View $view ): array {
		$dates      = array_keys( $grid_days );
		$first_date = $dates[0] ?? null;
		$last_date  = $dates[ count( $grid_days ) - 1 ] ?? null;

		if ( ! ( $first_date && $last_date ) ) {
			return [];
		}

		$cache_key = sprintf( '%s_%s_%s', get_class( $view ), $first_date, $last_date );

		if ( isset( $this->cache_days_data[ $cache_key ] ) ) {
			return $this->cache_days_data[ $cache_key ];
		}

		$days_data      = [];
		$prev_day_stack = [];
		$prev_day_date  = '';

		// Sunday is 0, Monday is 1 and Saturday is 6.
		// Follows the `w` format used in date format functions.
		$start_of_week = (int) get_option( 'start_of_week', 0 );

		// Used to build the link to the Day view in each cell.
		$default_day_url_args = array_merge( $view->get_url_args(), [ 'eventDisplay' => Day_View::get_view_slug() ] );

		foreach ( $grid_days as $day_date => $occurrence_ids ) {
			$count        = count( $grid_days[ $day_date ] ?? [] );
			$day          = new DateTime( $day_date );
			$day_url_args = array_merge( $default_day_url_args, [ 'eventDate' => $day_date ] );
			$found        = $this->found[ $day_date ] ?? $count;
			[ $day_events, $multiday_events, $featured_events ] = array_reduce(
				array_map( static function ( $id ) use ( $day_date ) {
					return tribe_get_event( $id, OBJECT, $day_date );
				}, $occurrence_ids ),
				static function ( array $carry, $event ): array {
					if ( ! $event instanceof WP_Post ) {
						return $carry;
					}

					if ( $event->multiday || $event->all_day ) {
						$carry[1][] = $event;
					} else {
						$carry[0][] = $event;
					}

					if ( $event->featured ) {
						$carry[2][] = $event;
					}

					return $carry;
				}, [ [], [], [] ] );

			// Build the stack for the day.
			$spacer = tribe( Stack::class )->get_spacer();
			// Start clean on the first day of the week to have a minimum height stack.
			$stack            = $this->build_stack( $prev_day_date, $day_date, $prev_day_stack, $multiday_events, $spacer );

			$day_data = [
				'date'             => $day_date,
				'is_start_of_week' => (int) get_option( 'start_of_week', 0 ) === (int) $day->format( 'w' ),
				'year_number'      => $day->format( 'Y' ),
				'month_number'     => $day->format( 'm' ),
				'day_number'       => $day->format( 'j' ),
				'events'           => $day_events,
				'featured_events'  => $featured_events,
				'multiday_events'  => $stack,
				'found_events'     => $found,
				'more_events'      => $found - $count,
				'day_url'          => tribe_events_get_url( $day_url_args ),
			];

			$days_data[ $day_date ] = $day_data;
			$prev_day_stack         = $stack;
			$prev_day_date          = $day_date;
		}
		unset( $prev_day_stack );

		// Trim the end spacers of each multi-day stack to "compact" events.
		foreach ( $days_data as $day_date => &$day_data ) {
			$trimmed_stack = $day_data['multiday_events'];
			while ( count( $trimmed_stack ) && end( $trimmed_stack ) === $spacer ) {
				array_pop( $trimmed_stack );
			}
			$day_data['multiday_events'] = $trimmed_stack;
		}
		unset( $day_data );

		$this->cache_days_data[ $cache_key ] = $days_data;

		return $days_data;
	}

	/**
	 * @param string               $prev_day_date
	 * @param string               $day_date
	 * @param array<WP_Post|mixed> $prev_day_stack A stack of Events and spacers from the previous day.
	 * @param array<WP_Post>       $multiday_events
	 * @param mixed                $spacer
	 */
	private function build_stack( string $prev_day_date, string $day_date, array $prev_day_stack, array $multiday_events, $spacer ): array {
		if ( count( $prev_day_stack ) === 0 ) {
			return $multiday_events;
		}

		$prev_ids           = array_values( array_filter( array_map( fn( $event ) => $event instanceof WP_Post ? $event->ID : false, $prev_day_stack ) ) );
		$unique_to_this_day = array_filter( $multiday_events, fn( WP_Post $event ) => ! in_array( $event->ID, $prev_ids, true ) );

		// This day stack will have the same size of the previous day stack, or more.
		$stack = array_fill( 0, count( $prev_day_stack ), $spacer );

		foreach ( $prev_day_stack as $key => $el ) {
			$el_is_event      = $el instanceof WP_Post;
			$ends_on_prev_day = $el_is_event && $el->ends_this_week && $el->dates->end->format( 'Y-m-d' ) === $prev_day_date;

			if ( $el_is_event && ! $ends_on_prev_day ) {
				$stack[ $key ] = $el;
				continue;
			}

			// The Event ended on the previous day: add an Event unique to this day in its place, if possible.
			$candidate = $spacer;
			while ( count( $unique_to_this_day ) ) {
				$candidate_event      = array_shift( $unique_to_this_day );
				$candidate_start_date = $candidate_event->dates->start_display->format( 'Y-m-d' );

				if ( $candidate_start_date !== $day_date ) {
					continue;
				}

				$candidate = $candidate_event;
				break;
			}

			$stack[ $key ] = $candidate;
		}

		// If there are still Events unique to this day to add, add them at the end of the stack.
		if ( count( $unique_to_this_day ) ) {
			$stack = array_merge( $stack, $unique_to_this_day );
		}

		return $stack;
	}

	/**
	 * @return DatePeriod<DateTimeImmutable>
	 */
	private function build_days_interval( DateTimeInterface $start, DateTimeInterface $end ): DatePeriod {
		$immutable_start = Dates::immutable( $start );
		$one_day         = new DateInterval( 'P1D' );
		// We need this as the interval would not include the end date, and the `DatePeriod::INCLUDE_END_DATE` flag is only
		// available in PHP 8.2+.
		$immutable_end = Dates::immutable( $end )->add( $one_day );

		return new DatePeriod( $immutable_start, $one_day, $immutable_end );
	}
}
