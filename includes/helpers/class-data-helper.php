<?php
/**
 * Data Helper Class
 */
class YWCE_Data_Helper {


	/**
	 * Get product data including all fields and meta
	 * @param $wc_product
	 * @param $selected_fields
	 * @param $selected_meta
	 * @param $selected_taxonomies
	 *
	 * @return array
	 */
	public function get_product_data($wc_product, $selected_fields, $selected_meta, $selected_taxonomies): array {
		$product_data = [];

		$product_data['ID'] = $wc_product->get_id();
		if ($wc_product->is_type('variation')) {
			$product_data['Parent ID'] = $wc_product->get_parent_id();
			$parent_id = $wc_product->get_parent_id();
			$parent_product = wc_get_product($parent_id);
			$product_data['plytix_variant_of'] = $parent_product ? $parent_product->get_sku() : '';
		} else {
			if ( in_array( 'Parent ID', $selected_fields, true ) ) {
				$product_data['Parent ID'] = '';
			}
			$product_data['plytix_variant_of'] = '';
		}

		foreach ($selected_fields as $field) {
			if (in_array($field, ['ID', 'Parent ID', 'plytix_variant_of'])) {
				continue;
			}

			switch ($field) {
				case 'Title':
					$product_data['Title'] = $wc_product->get_name();
					break;
				case 'Permalink':
					$product_data['Permalink'] = get_permalink($wc_product->get_id());
					break;
				case 'Short Description':
					$product_data['Short Description'] = $wc_product->get_short_description();
					break;
				case 'Description':
					$product_data['Description'] = $wc_product->get_description();
					break;
				case 'Featured Image':
					$product_data['Featured Image'] = wp_get_attachment_url($wc_product->get_image_id()) ?: '';
					break;
				case 'Product Gallery URLs':
					$product_data['Product Gallery URLs'] = implode(', ', $this->get_product_gallery_urls($wc_product->get_id()));
					break;
				case 'Product Categories':
					$product_data['Product Categories'] = $this->get_product_categories_hierarchy($wc_product->get_id());
					break;
				case 'Product Category URL':
					$product_data['Product Category URL'] = $this->get_final_category_url($wc_product->get_id());
					break;
				case 'Product type':
					$product_data['Product type'] = $wc_product->get_type();
					break;
				case 'Product status':
					$product_data['Product status'] = $wc_product->get_status();
					break;
				default:
					$product_data[$field] = '';
			}
		}

		foreach ($selected_meta as $meta_key) {
			$product_data[$meta_key] = get_post_meta($wc_product->get_id(), $meta_key, true) ?? '';
		}

		foreach ($selected_taxonomies as $taxonomy) {
			$terms = get_the_terms($wc_product->get_id(), $taxonomy);
			$product_data[$taxonomy] = (!is_wp_error($terms) && !empty($terms)) ? implode(', ', wp_list_pluck($terms, 'name')) : '';
		}

		return $product_data;
	}

	/**
	 * Get user data for export
	 * @param $user_id
	 * @param $selected_fields
	 * @param $selected_meta
	 *
	 * @return array
	 */
	public function get_user_data($user_id, $selected_fields, $selected_meta): array {
		$user = get_userdata($user_id);
		if (!$user) {
			return [];
		}

		$user_data = [];
		
		$user_data['ID'] = $user->ID;

		foreach ($selected_fields as $field) {
			if ($field === 'ID') {
				continue;
			}

			switch ($field) {
				case 'Username':
					$user_data['Username'] = $user->user_login;
					break;
				case 'Email':
					$user_data['Email'] = $user->user_email;
					break;
				case 'First Name':
					$user_data['First Name'] = get_user_meta($user->ID, 'first_name', true);
					break;
				case 'Last Name':
					$user_data['Last Name'] = get_user_meta($user->ID, 'last_name', true);
					break;
				case 'Role':
					$user_data['Role'] = implode(', ', $user->roles);
					break;
				case 'Registration Date':
					$user_data['Registration Date'] = $user->user_registered;
					break;
				default:
					$user_data[$field] = '';
			}
		}

		foreach ($selected_meta as $meta_key) {
			$user_data[$meta_key] = get_user_meta($user->ID, $meta_key, true) ?? '';
		}

		return $user_data;
	}


	/**
	 * Get product categories hierarchy
	 * @param $product_id
	 *
	 * @return string
	 */
	public function get_product_categories_hierarchy($product_id): string {
		$categories = get_the_terms($product_id, 'product_cat');
		if (empty($categories) || is_wp_error($categories)) {
			return '';
		}

		$category_tree = [];

		foreach ($categories as $category) {
			$full_hierarchy = [];
			$current_category = $category;

			while ($current_category) {
				$full_hierarchy[] = $current_category->name;
				$current_category = get_term($current_category->parent, 'product_cat');
				if (is_wp_error($current_category)) {
					break;
				}
			}

			$category_tree[] = implode(' > ', array_reverse($full_hierarchy));
		}

		return implode(', ', $category_tree);
	}


	/**
	 * Get final category URL for product
	 * @param $product_id
	 *
	 * @return string
	 */
	public function get_final_category_url($product_id): string {
		$categories = get_the_terms($product_id, 'product_cat');
		if (empty($categories) || is_wp_error($categories)) {
			return '';
		}

		$category_urls = array_map(function ($category) {
			return get_term_link($category, 'product_cat');
		}, $categories);

		return implode(', ', $category_urls);
	}


	/**
	 * Get product gallery URLs for export
	 * @param $product_id
	 *
	 * @return array
	 */
	public function get_product_gallery_urls($product_id): array {
		$gallery_ids = get_post_meta($product_id, '_product_image_gallery', true);
		if (!$gallery_ids) return [];

		$image_ids = explode(',', $gallery_ids);
		return array_map('wp_get_attachment_url', $image_ids);
	}


	/**
	 * Get available product types that exist
	 * @return array
	 */
	public function get_product_types(): array {
		global $wpdb;
		
		// Get product types that actually exist in the database
		$query = $wpdb->prepare("
			SELECT DISTINCT terms.slug, terms.name
			FROM {$wpdb->terms} terms
			INNER JOIN {$wpdb->term_taxonomy} tt ON terms.term_id = tt.term_id
			INNER JOIN {$wpdb->term_relationships} tr ON tt.term_taxonomy_id = tr.term_taxonomy_id
			INNER JOIN {$wpdb->posts} posts ON tr.object_id = posts.ID
			WHERE tt.taxonomy = %s
			AND posts.post_type = 'product'
			AND posts.post_status IN ('publish', 'draft', 'private', 'pending')
		", 'product_type');
		
		$results = $wpdb->get_results($query);
		
		$product_types = [];
		
		$product_types[] = [
			'value' => 'simple',
			'label' => 'Simple'
		];
		
		if (!empty($results)) {
			foreach ($results as $result) {
				if ($result->slug === 'simple') {
					continue;
				}
				$product_types[] = [
					'value' => $result->slug,
					'label' => ucfirst($result->name)
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
		
		$query = $wpdb->prepare("
			SELECT DISTINCT meta.meta_value
			FROM {$wpdb->usermeta} meta
			WHERE meta.meta_key = %s
			AND meta.user_id IN (
				SELECT ID FROM {$wpdb->users}
				WHERE user_status = 0
			)
		", $wpdb->prefix . 'capabilities');
		
		$results = $wpdb->get_results($query);
		
		$roles = [];
		if (!empty($results)) {
			foreach ($results as $result) {
				$capabilities = maybe_unserialize($result->meta_value);
				if (is_array($capabilities)) {
					foreach (array_keys($capabilities) as $role) {
						if (!isset($roles[$role])) {
							$role_names = wp_roles()->get_names();
							$label = $role_names[ $role ] ?? ucfirst( str_replace( '_', ' ', $role ) );
							
							$roles[$role] = [
								'value' => $role,
								'label' => $label
							];
						}
					}
				}
			}
		}
		
		// Sort roles by label
		usort($roles, static function($a, $b) {
			return strcmp($a['label'], $b['label']);
		});
		
		return array_values($roles);
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
		
		$results = $wpdb->get_results($query);
		
		$statuses = [];
		if (!empty($results)) {
			$wc_statuses = wc_get_order_statuses();
			
			foreach ($results as $result) {
				$status = $result->post_status;
				
				// Skip auto-draft and trash statuses
				if (in_array($status, ['auto-draft', 'trash'])) {
					continue;
				}
				
				// Get the proper label
				if (isset($wc_statuses[$status])) {
					$label = $wc_statuses[$status];
				} elseif (isset($wc_statuses['wc-' . $status])) {
					$label = $wc_statuses['wc-' . $status];
				} else {
					$label = ucfirst(str_replace(['wc-', '-'], ['', ' '], $status));
				}
				
				$statuses[] = [
					'value' => $status,
					'label' => $label,
					'count' => (int)$result->count
				];
			}
		}
		
		// Sort statuses by label
		usort($statuses, static function($a, $b) {
			return strcmp($a['label'], $b['label']);
		});
		
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
	public function get_order_data( WC_Order $order, array $selected_fields, array $selected_meta): array {
		$data = [];
		
		foreach ($selected_fields as $field) {
			switch ($field) {
				case 'ID':
					$data[$field] = $order->get_id();
					break;
				case 'Order Number':
					$data[$field] = $order->get_order_number();
					break;
				case 'Order Status':
					$data[$field] = $order->get_status();
					break;
				case 'Order Date':
					$data[$field] = $order->get_date_created() ? $order->get_date_created()->date('Y-m-d H:i:s') : '';
					break;
				case 'Customer ID':
					$data[$field] = $order->get_customer_id();
					break;
				case 'Customer Email':
					$data[$field] = $order->get_billing_email();
					break;
				case 'Customer First Name':
					$data[$field] = $order->get_billing_first_name();
					break;
				case 'Customer Last Name':
					$data[$field] = $order->get_billing_last_name();
					break;
				case 'Billing First Name':
					$data[$field] = $order->get_billing_first_name();
					break;
				case 'Billing Last Name':
					$data[$field] = $order->get_billing_last_name();
					break;
				case 'Billing Company':
					$data[$field] = $order->get_billing_company();
					break;
				case 'Billing Address 1':
					$data[$field] = $order->get_billing_address_1();
					break;
				case 'Billing Address 2':
					$data[$field] = $order->get_billing_address_2();
					break;
				case 'Billing City':
					$data[$field] = $order->get_billing_city();
					break;
				case 'Billing State':
					$data[$field] = $order->get_billing_state();
					break;
				case 'Billing Postcode':
					$data[$field] = $order->get_billing_postcode();
					break;
				case 'Billing Country':
					$data[$field] = $order->get_billing_country();
					break;
				case 'Billing Email':
					$data[$field] = $order->get_billing_email();
					break;
				case 'Billing Phone':
					$data[$field] = $order->get_billing_phone();
					break;
				case 'Shipping First Name':
					$data[$field] = $order->get_shipping_first_name();
					break;
				case 'Shipping Last Name':
					$data[$field] = $order->get_shipping_last_name();
					break;
				case 'Shipping Company':
					$data[$field] = $order->get_shipping_company();
					break;
				case 'Shipping Address 1':
					$data[$field] = $order->get_shipping_address_1();
					break;
				case 'Shipping Address 2':
					$data[$field] = $order->get_shipping_address_2();
					break;
				case 'Shipping City':
					$data[$field] = $order->get_shipping_city();
					break;
				case 'Shipping State':
					$data[$field] = $order->get_shipping_state();
					break;
				case 'Shipping Postcode':
					$data[$field] = $order->get_shipping_postcode();
					break;
				case 'Shipping Country':
					$data[$field] = $order->get_shipping_country();
					break;
				case 'Payment Method':
					$data[$field] = $order->get_payment_method();
					break;
				case 'Payment Method Title':
					$data[$field] = $order->get_payment_method_title();
					break;
				case 'Transaction ID':
					$data[$field] = $order->get_transaction_id();
					break;
				case 'Order Total':
					$data[$field] = $order->get_total();
					break;
				case 'Order Subtotal':
					$data[$field] = $order->get_subtotal();
					break;
				case 'Order Tax':
					$data[$field] = $order->get_total_tax();
					break;
				case 'Order Shipping':
					$data[$field] = $order->get_shipping_total();
					break;
				case 'Order Shipping Tax':
					$data[$field] = $order->get_shipping_tax();
					break;
				case 'Order Discount':
					$data[$field] = $order->get_discount_total();
					break;
				case 'Order Currency':
					$data[$field] = $order->get_currency();
					break;
				case 'Order Items':
					$items = [];
					foreach ($order->get_items() as $item) {
						$items[] = $item->get_name() . ' x ' . $item->get_quantity();
					}
					$data[$field] = implode(', ', $items);
					break;
				case 'Order Notes':
					$notes = [];
					$order_notes = wc_get_order_notes(['order_id' => $order->get_id()]);
					foreach ($order_notes as $note) {
						$notes[] = $note->content;
					}
					$data[$field] = implode(', ', $notes);
					break;
				default:
					$data[$field] = '';
					break;
			}
		}
		
		// Add meta fields
		foreach ($selected_meta as $meta_key) {
			$meta_value = get_post_meta($order->get_id(), $meta_key, true);
			if (is_array($meta_value) || is_object($meta_value)) {
				$meta_value = wp_json_encode($meta_value);
			}
			$data[$meta_key] = $meta_value;
		}
		
		return $data;
	}
} 