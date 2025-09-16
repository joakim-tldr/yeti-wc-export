<?php
namespace YWCE;
use WC_Order;
/**
 * Data Helper Class
 */
class YWCE_Data_Helper {


	/**
	 * Get product data including all fields and meta
	 *
	 * @param $wc_product
	 * @param $selected_fields
	 * @param $selected_meta
	 * @param $selected_taxonomies
	 *
	 * @return array
	 */
	public function get_product_data( $wc_product, $selected_fields, $selected_meta, $selected_taxonomies ): array {
		// Use the new FieldRegistry + ProductDataMapper for core fields/meta/taxonomy mapping
		$registry = new \YWCE\Data\FieldRegistry();
		$mapper   = new \YWCE\Data\Mapper\ProductDataMapper( $registry );
		$product_data = $mapper->map( $wc_product, $selected_fields, $selected_meta, $selected_taxonomies );

		// Handle additional legacy fields that are not yet in the FieldRegistry
		$registered_fields = array_keys( $registry->get( 'product' ) );
		foreach ( $selected_fields as $field ) {
			if ( in_array( $field, $registered_fields, true ) ) {
				continue; // already mapped by registry
			}
			switch ( $field ) {
				case 'Short Description':
					$product_data['Short Description'] = $wc_product->get_short_description();
					break;
				case 'Description':
					$product_data['Description'] = $wc_product->get_description();
					break;
				case 'Featured Image':
					$product_data['Featured Image'] = wp_get_attachment_url( $wc_product->get_image_id() ) ?: '';
					break;
				case 'Product Gallery URLs':
					$product_data['Product Gallery URLs'] = implode( ', ', $this->get_product_gallery_urls( $wc_product->get_id() ) );
					break;
				case 'Product Categories':
					$product_data['Product Categories'] = $this->get_product_categories_hierarchy( $wc_product->get_id() );
					break;
				case 'Product Category URL':
					$product_data['Product Category URL'] = $this->get_final_category_url( $wc_product->get_id() );
					break;
				default:
					if ( ! isset( $product_data[ $field ] ) ) {
						$product_data[ $field ] = '';
					}
			}
		}

		return $product_data;
	}

	/**
	 * Get user data for export
	 *
	 * @param $user_id
	 * @param $selected_fields
	 * @param $selected_meta
	 *
	 * @return array
	 */
	public function get_user_data( $user_id, $selected_fields, $selected_meta ): array {
		$user = get_userdata( $user_id );
		if ( ! $user ) {
			return [];
		}
		$mapper = new \YWCE\Data\Mapper\UserDataMapper();
		return $mapper->map( $user, $selected_fields, $selected_meta );
	}


	/**
	 * Get product categories hierarchy
	 *
	 * @param $product_id
	 *
	 * @return string
	 */
	public function get_product_categories_hierarchy( $product_id ): string {
		$categories = get_the_terms( $product_id, 'product_cat' );
		if ( empty( $categories ) || is_wp_error( $categories ) ) {
			return '';
		}

		$category_tree = [];

		foreach ( $categories as $category ) {
			$full_hierarchy   = [];
			$current_category = $category;

			while ( $current_category ) {
				$full_hierarchy[] = $current_category->name;
				$current_category = get_term( $current_category->parent, 'product_cat' );
				if ( is_wp_error( $current_category ) ) {
					break;
				}
			}

			$category_tree[] = implode( ' > ', array_reverse( $full_hierarchy ) );
		}

		return implode( ', ', $category_tree );
	}


	/**
	 * Get final category URL for product
	 *
	 * @param $product_id
	 *
	 * @return string
	 */
	public function get_final_category_url( $product_id ): string {
		$categories = get_the_terms( $product_id, 'product_cat' );
		if ( empty( $categories ) || is_wp_error( $categories ) ) {
			return '';
		}

		$category_urls = array_map( function ( $category ) {
			return get_term_link( $category, 'product_cat' );
		}, $categories );

		return implode( ', ', $category_urls );
	}


	/**
	 * Get product gallery URLs for export
	 *
	 * @param $product_id
	 *
	 * @return array
	 */
	public function get_product_gallery_urls( $product_id ): array {
		$gallery_ids = get_post_meta( $product_id, '_product_image_gallery', true );
		if ( ! $gallery_ids ) {
			return [];
		}

		$image_ids = explode( ',', $gallery_ids );

		return array_map( 'wp_get_attachment_url', $image_ids );
	}


	/**
	 * Get available product types that exist
	 * @return array
	 */
	public function get_product_types(): array {
		global $wpdb;

		// Get product types that actually exist in the database
		$query = $wpdb->prepare( "
			SELECT DISTINCT terms.slug, terms.name
			FROM {$wpdb->terms} terms
			INNER JOIN {$wpdb->term_taxonomy} tt ON terms.term_id = tt.term_id
			INNER JOIN {$wpdb->term_relationships} tr ON tt.term_taxonomy_id = tr.term_taxonomy_id
			INNER JOIN {$wpdb->posts} posts ON tr.object_id = posts.ID
			WHERE tt.taxonomy = %s
			AND posts.post_type = 'product'
			AND posts.post_status IN ('publish', 'draft', 'private', 'pending')
		", 'product_type' );

		$results = $wpdb->get_results( $query );

		$product_types = [];

		$product_types[] = [
			'value' => 'simple',
			'label' => 'Simple'
		];

		if ( ! empty( $results ) ) {
			foreach ( $results as $result ) {
				if ( $result->slug === 'simple' ) {
					continue;
				}
				$product_types[] = [
					'value' => $result->slug,
					'label' => ucfirst( $result->name )
				];
			}
		}

		return $product_types;
	}


	/**
	 * Get available product statuses that exist
	 * @return array
	 */
	public function get_user_roles(): array {
		global $wpdb;

		$query = $wpdb->prepare( "
			SELECT DISTINCT meta.meta_value
			FROM {$wpdb->usermeta} meta
			WHERE meta.meta_key = %s
			AND meta.user_id IN (
				SELECT ID FROM {$wpdb->users}
				WHERE user_status = 0
			)
		", $wpdb->prefix . 'capabilities' );

		$results = $wpdb->get_results( $query );

		$roles = [];
		if ( ! empty( $results ) ) {
			foreach ( $results as $result ) {
				$capabilities = maybe_unserialize( $result->meta_value );
				if ( is_array( $capabilities ) ) {
					foreach ( array_keys( $capabilities ) as $role ) {
						if ( ! isset( $roles[ $role ] ) ) {
							$role_names = wp_roles()->get_names();
							$label      = $role_names[ $role ] ?? ucfirst( str_replace( '_', ' ', $role ) );

							$roles[ $role ] = [
								'value' => $role,
								'label' => $label
							];
						}
					}
				}
			}
		}

		// Sort roles by label
		usort( $roles, static function ( $a, $b ) {
			return strcmp( $a['label'], $b['label'] );
		} );

		return array_values( $roles );
	}


	/**
	 * Get available order statuses that exist
	 * @return array
	 */
	public function get_order_statuses(): array {
		global $wpdb;

		// Get order statuses that actually have orders
		$query = "
			SELECT DISTINCT posts.post_status, COUNT(*) as count
			FROM {$wpdb->posts} posts
			WHERE posts.post_type = 'shop_order'
			AND posts.post_status != 'trash'
			AND posts.post_status != 'auto-draft'
			GROUP BY posts.post_status
		";

		$results = $wpdb->get_results( $query );

		$statuses = [];
		if ( ! empty( $results ) ) {
			$wc_statuses = wc_get_order_statuses();

			foreach ( $results as $result ) {
				$status = $result->post_status;

				// Skip auto-draft and trash statuses
				if ( in_array( $status, [ 'auto-draft', 'trash' ] ) ) {
					continue;
				}

				// Get the proper label
				if ( isset( $wc_statuses[ $status ] ) ) {
					$label = $wc_statuses[ $status ];
				} elseif ( isset( $wc_statuses[ 'wc-' . $status ] ) ) {
					$label = $wc_statuses[ 'wc-' . $status ];
				} else {
					$label = ucfirst( str_replace( [ 'wc-', '-' ], [ '', ' ' ], $status ) );
				}

				$statuses[] = [
					'value' => $status,
					'label' => $label,
					'count' => (int) $result->count
				];
			}
		}

		// Sort statuses by label
		usort( $statuses, static function ( $a, $b ) {
			return strcmp( $a['label'], $b['label'] );
		} );

		return $statuses;
	}

	/**
	 * Get order data for export
	 *
	 * @param WC_Order $order Order object
	 * @param array $selected_fields Selected fields
	 * @param array $selected_meta Selected meta-fields
	 *
	 * @return array Order data
	 */
	public function get_order_data( WC_Order $order, array $selected_fields, array $selected_meta ): array {
		$mapper = new \YWCE\Data\Mapper\OrderDataMapper();
		return $mapper->map($order, $selected_fields, $selected_meta);
	}
} 