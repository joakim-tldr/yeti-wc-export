<?php

namespace YWCE\Data;

use WC_Product;

class FieldRegistry {
	private array $bySource;

	public function __construct() {
		$this->bySource = [
			'product' => [
				'ID'                => fn( WC_Product $p ) => $p->get_id(),
				'Parent ID'         => fn( WC_Product $p ) => $p->is_type( 'variation' ) ? $p->get_parent_id() : '',
				'plytix_variant_of' => function ( WC_Product $p ) {
					if ( ! $p->is_type( 'variation' ) ) {
						return '';
					}
					$parent = wc_get_product( $p->get_parent_id() );

					return $parent ? ( $parent->get_sku() ?: '' ) : '';
				},
				'Title'             => fn( WC_Product $p ) => $p->get_name(),
				'Permalink'         => fn( WC_Product $p ) => get_permalink( $p->get_id() ),
				'Product type'      => fn( WC_Product $p ) => $p->get_type(),
				'Product status'    => fn( WC_Product $p ) => $p->get_status(),
			],
		];

		$this->bySource = apply_filters( 'ywce_field_registry', $this->bySource );
	}

	public function get( string $source ): array {
		return $this->bySource[ $source ] ?? [];
	}
}
