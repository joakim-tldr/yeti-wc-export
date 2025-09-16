<?php
namespace YWCE\Data\Mapper;

use WC_Order;

class OrderDataMapper {
    public function map(WC_Order $order, array $selected, array $meta): array {
        $data = [];
        foreach ($selected as $field) {
            switch ($field) {
                case 'ID': $data[$field] = $order->get_id(); break;
                case 'Order Number': $data[$field] = $order->get_order_number(); break;
                case 'Order Status': $data[$field] = $order->get_status(); break;
                case 'Order Date': $data[$field] = $order->get_date_created() ? $order->get_date_created()->date('Y-m-d H:i:s') : ''; break;
                case 'Customer ID': $data[$field] = $order->get_customer_id(); break;
	            case 'Billing Email':
	            case 'Customer Email': $data[$field] = $order->get_billing_email(); break;
	            case 'Billing First Name':
	            case 'Customer First Name': $data[$field] = $order->get_billing_first_name(); break;
	            case 'Billing Last Name':
	            case 'Customer Last Name': $data[$field] = $order->get_billing_last_name(); break;
	            case 'Billing Company': $data[$field] = $order->get_billing_company(); break;
                case 'Billing Address 1': $data[$field] = $order->get_billing_address_1(); break;
                case 'Billing Address 2': $data[$field] = $order->get_billing_address_2(); break;
                case 'Billing City': $data[$field] = $order->get_billing_city(); break;
                case 'Billing State': $data[$field] = $order->get_billing_state(); break;
                case 'Billing Postcode': $data[$field] = $order->get_billing_postcode(); break;
                case 'Billing Country': $data[$field] = $order->get_billing_country(); break;
	            case 'Billing Phone': $data[$field] = $order->get_billing_phone(); break;
                case 'Shipping First Name': $data[$field] = $order->get_shipping_first_name(); break;
                case 'Shipping Last Name': $data[$field] = $order->get_shipping_last_name(); break;
                case 'Shipping Company': $data[$field] = $order->get_shipping_company(); break;
                case 'Shipping Address 1': $data[$field] = $order->get_shipping_address_1(); break;
                case 'Shipping Address 2': $data[$field] = $order->get_shipping_address_2(); break;
                case 'Shipping City': $data[$field] = $order->get_shipping_city(); break;
                case 'Shipping State': $data[$field] = $order->get_shipping_state(); break;
                case 'Shipping Postcode': $data[$field] = $order->get_shipping_postcode(); break;
                case 'Shipping Country': $data[$field] = $order->get_shipping_country(); break;
                case 'Payment Method': $data[$field] = $order->get_payment_method(); break;
                case 'Payment Method Title': $data[$field] = $order->get_payment_method_title(); break;
                case 'Transaction ID': $data[$field] = $order->get_transaction_id(); break;
                case 'Order Total': $data[$field] = $order->get_total(); break;
                case 'Order Subtotal': $data[$field] = $order->get_subtotal(); break;
                case 'Order Tax': $data[$field] = $order->get_total_tax(); break;
                case 'Order Shipping': $data[$field] = $order->get_shipping_total(); break;
                case 'Order Shipping Tax': $data[$field] = $order->get_shipping_tax(); break;
                case 'Order Discount': $data[$field] = $order->get_discount_total(); break;
                case 'Order Currency': $data[$field] = $order->get_currency(); break;
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
                default: $data[$field] = ''; break;
            }
        }
        foreach ($meta as $m) {
            $meta_value = get_post_meta($order->get_id(), $m, true);
            if (is_array($meta_value) || is_object($meta_value)) {
                $meta_value = wp_json_encode($meta_value);
            }
            $data[$m] = $meta_value;
        }
        return $data;
    }
}
