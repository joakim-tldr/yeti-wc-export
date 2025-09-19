<?php

namespace YWCE\Data\Store;

use WP_User_Query;

class UserStore {

	/**
	 * Resolve user IDs according to filters.
	 *
	 * @param array $filters
	 *
	 * @return array
	 */
	public function resolveItemIds( array $filters ): array {
		$user_roles = $filters['user_roles'] ?? [ 'all' ];
		$date_range = $filters['date_range'] ?? 'all';
		$date_from  = $filters['date_from'] ?? '';
		$date_to    = $filters['date_to'] ?? '';

		$args = [
			'fields' => 'ID',
			'number' => - 1,
		];

		if ( ! empty( $user_roles ) && ! in_array( 'all', $user_roles, true ) ) {
			$args['role__in'] = $user_roles;
		}

		if ( $date_range !== 'all' ) {
			$date_query = [];
			switch ( $date_range ) {
				case 'last30':
					$date_query = [ 'after' => '30 days ago', 'inclusive' => true ];
					break;
				case 'last90':
					$date_query = [ 'after' => '90 days ago', 'inclusive' => true ];
					break;
				case 'last180':
					$date_query = [ 'after' => '180 days ago', 'inclusive' => true ];
					break;
				case 'custom':
					if ( $date_from ) {
						$date_query['after'] = $date_from;
					}
					if ( $date_to ) {
						$date_query['before'] = $date_to;
					}
					$date_query['inclusive'] = true;
					break;
			}
			if ( ! empty( $date_query ) ) {
				$args['date_query'] = [ $date_query ];
			}
		}

		$query   = new WP_User_Query( $args );
		$itemIds = $query->get_results();
		if ( ! is_array( $itemIds ) ) {
			$itemIds = [];
		}

		return [ 'ids' => $itemIds, 'total' => count( $itemIds ) ];
	}
}
