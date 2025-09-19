<?php

namespace YWCE\Data\Mapper;

use WC_Product;
use YWCE\Data\FieldRegistry;

class ProductDataMapper {
	public function __construct( private readonly FieldRegistry $fields ) {
	}

	/**
	 * Map product data to an array.
	 *
	 * @param WC_Product $p
	 * @param array $selected
	 * @param array $meta
	 * @param array $tax
	 *
	 * @return array
	 */
	public function map( WC_Product $p, array $selected, array $meta, array $tax ): array {
		$row       = [];
		$callbacks = $this->fields->get( 'product' );
		foreach ( $selected as $field ) {
			if ( isset( $callbacks[ $field ] ) ) {
				$row[ $field ] = $callbacks[ $field ]( $p );
			} else {
				$row[ $field ] = '';
			}
		}
		foreach ( $meta as $m ) {
			$row[ $m ] = get_post_meta( $p->get_id(), $m, true ) ?? '';
		}
		foreach ( $tax as $t ) {
			$terms     = get_the_terms( $p->get_id(), $t );
			$row[ $t ] = ( ! is_wp_error( $terms ) && ! empty( $terms ) ) ? implode( ', ', wp_list_pluck( $terms, 'name' ) ) : '';
		}

		return $row;
	}
}
