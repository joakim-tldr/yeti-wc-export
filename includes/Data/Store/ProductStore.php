<?php

namespace YWCE\Data\Store;

class ProductStore {

	/**
	 * Resolve product IDs according to filters.
	 *
	 * @param array $filters
	 * @param array $fields
	 *
	 * @return array
	 */
	public function resolveItemIds( array $filters, array $fields ): array {
		$args          = [
			'post_type'      => 'product',
			'posts_per_page' => - 1,
			'fields'         => 'ids',
		];
		$product_types = $filters['product_types'] ?? [ 'all' ];
		if ( ! is_array( $product_types ) ) {
			$product_types = [ $product_types ];
		}

		$product_status     = $filters['product_status'] ?? 'any';
		$product_status_arr = [];
		if ( is_array( $product_status ) ) {
			$product_status_arr = $product_status;
		} elseif ( is_string( $product_status ) ) {
			if ( $product_status !== 'any' ) {
				$product_status_arr = [ $product_status ];
			}
		}
		if ( ! empty( $product_types ) && ! in_array( 'all', $product_types, true ) ) {
			$args['tax_query'][] = [
				'taxonomy' => 'product_type',
				'field'    => 'slug',
				'terms'    => $product_types,
			];
		}
		if ( ! empty( $product_status_arr ) && ! in_array( 'all', $product_status_arr, true ) ) {
			$args['post_status'] = $product_status_arr;
		} else {
			$args['post_status'] = 'any';
		}
		if ( ! empty( $filters['date_query'] ) ) {
			$args['date_query'] = $filters['date_query'];
		}
		$product_ids = get_posts( $args );
		if ( is_wp_error( $product_ids ) ) {
			return [ 'ids' => [], 'total' => 0 ];
		}
		$ids = $product_ids;

		$needs_variations = in_array( 'Parent ID', $fields, true ) || in_array( 'plytix_variant_of', $fields, true );
		if ( $needs_variations ) {
			$parents = $product_ids;
			if ( ! in_array( 'variable', $product_types, true ) && ! in_array( 'all', $product_types, true ) ) {
				$var_parent_args = [
					'post_type'      => 'product',
					'posts_per_page' => - 1,
					'fields'         => 'ids',
					'tax_query'      => [
						[
							'taxonomy' => 'product_type',
							'field'    => 'slug',
							'terms'    => [ 'variable' ]
						]
					],
					'post_status'    => $args['post_status'],
				];
				if ( ! empty( $args['date_query'] ) ) {
					$var_parent_args['date_query'] = $args['date_query'];
				}
				$variable_parents = get_posts( $var_parent_args );
				if ( ! is_wp_error( $variable_parents ) && ! empty( $variable_parents ) ) {
					$parents = array_unique( array_merge( $parents, $variable_parents ) );
				}
			}
			if ( ! empty( $parents ) ) {
				$variation_args = [
					'post_type'       => 'product_variation',
					'posts_per_page'  => - 1,
					'fields'          => 'ids',
					'post_parent__in' => $parents,
					'post_status'     => $args['post_status'],
				];
				if ( ! empty( $args['date_query'] ) ) {
					$variation_args['date_query'] = $args['date_query'];
				}
				$variation_ids = get_posts( $variation_args );
				if ( ! is_wp_error( $variation_ids ) && ! empty( $variation_ids ) ) {
					// Group parent then children
					$variation_map = [];
					foreach ( $variation_ids as $vid ) {
						$parent = (int) get_post_field( 'post_parent', $vid );
						if ( $parent ) {
							$variation_map[ $parent ][] = $vid;
						}
					}
					$ordered = [];
					$seen    = [];
					foreach ( $product_ids as $pid ) {
						$ordered[]    = $pid;
						$seen[ $pid ] = true;
						if ( ! empty( $variation_map[ $pid ] ) ) {
							foreach ( $variation_map[ $pid ] as $vid ) {
								$ordered[]    = $vid;
								$seen[ $vid ] = true;
							}
							unset( $variation_map[ $pid ] );
						}
					}
					foreach ( $variation_map as $vids ) {
						foreach ( $vids as $vid ) {
							if ( empty( $seen[ $vid ] ) ) {
								$ordered[]    = $vid;
								$seen[ $vid ] = true;
							}
						}
					}
					$ids = $ordered;
				}
			}
		}

		return [ 'ids' => $ids, 'total' => count( $ids ) ];
	}
}
