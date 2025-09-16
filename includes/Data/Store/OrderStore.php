<?php
namespace YWCE\Data\Store;

class OrderStore {
    /**
     * Resolve order IDs according to filters.
     * Mirrors previous exporter logic to avoid behavior changes.
     *
     * @param array $filters {
     *   @type array  $order_statuses Status slugs or ['all']
     *   @type string $date_range     all|last30|last90|last180|custom
     *   @type string $date_from      Y-m-d (for custom)
     *   @type string $date_to        Y-m-d (for custom)
     * }
     * @return array{ids: array<int>, total: int}
     */
    public function resolveItemIds(array $filters): array {
        $order_statuses = $filters['order_statuses'] ?? ['all'];
        $date_range     = $filters['date_range'] ?? 'all';
        $date_from      = $filters['date_from'] ?? '';
        $date_to        = $filters['date_to'] ?? '';

        $args = [
            'post_type'      => 'shop_order',
            'posts_per_page' => -1,
            'fields'         => 'ids',
        ];

        if (!empty($order_statuses) && !in_array('all', $order_statuses, true)) {
            $args['post_status'] = $order_statuses;
        } else {
            $args['post_status'] = array_keys( wc_get_order_statuses() );
        }

        if ($date_range !== 'all') {
            $date_query = [];
            switch ($date_range) {
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
                    if ($date_from) { $date_query['after'] = $date_from; }
                    if ($date_to) { $date_query['before'] = $date_to; }
                    $date_query['inclusive'] = true;
                    break;
            }
            if (!empty($date_query)) {
                $args['date_query'] = [ $date_query ];
            }
        }

        $order_ids = get_posts($args);
        if (is_wp_error($order_ids)) {
            return ['ids' => [], 'total' => 0];
        }

        return ['ids' => $order_ids, 'total' => count($order_ids)];
    }
}
