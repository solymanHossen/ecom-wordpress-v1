<?php
/**
 * NexMart Orders Handler
 * Manages order creation, checkout, status tracking, and order management
 */

if (!defined('ABSPATH')) {
    exit;
}

class NexMart_Orders {
    
    private static $instance = null;
    private $wpdb;
    private $db;
    
    public static function get_instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        global $wpdb;
        $this->wpdb = $wpdb;
        $this->db = NexMart_Database::get_instance();
        $this->init_hooks();
    }
    
    private function init_hooks() {
        add_action('wp_ajax_nexmart_create_order', [$this, 'ajax_create_order']);
        add_action('wp_ajax_nopriv_nexmart_create_order', [$this, 'ajax_create_order']);
        add_action('wp_ajax_nexmart_get_orders', [$this, 'ajax_get_orders']);
        add_action('wp_ajax_nexmart_get_order', [$this, 'ajax_get_order']);
        add_action('wp_ajax_nexmart_update_order_status', [$this, 'ajax_update_order_status']);
        add_action('wp_ajax_nexmart_cancel_order', [$this, 'ajax_cancel_order']);
    }
    
    /**
     * Create order from cart
     */
    public function create_order($data) {
        $cart = NexMart_Cart::get_instance()->get_cart();
        
        if (empty($cart['items'])) {
            return new WP_Error('empty_cart', 'Your cart is empty.');
        }
        
        // Validate shipping info
        $required = ['first_name', 'last_name', 'email', 'address', 'city', 'country'];
        foreach ($required as $field) {
            if (empty($data[$field])) {
                return new WP_Error('missing_field', "Please provide your {$field}.");
            }
        }
        
        $user_id = get_current_user_id();
        $order_number = $this->generate_order_number();
        
        // Create order
        $order_data = [
            'order_number' => $order_number,
            'user_id' => $user_id ?: null,
            'status' => 'pending',
            'payment_status' => 'pending',
            'payment_method' => sanitize_text_field($data['payment_method'] ?? 'cod'),
            'subtotal' => $cart['subtotal'],
            'discount' => $cart['discount'],
            'shipping_cost' => $cart['shipping'],
            'tax' => $cart['tax'],
            'total' => $cart['total'],
            'coupon_code' => $cart['coupon'] ? $cart['coupon']['code'] : null,
            'currency' => 'USD',
            
            // Shipping info
            'shipping_first_name' => sanitize_text_field($data['first_name']),
            'shipping_last_name' => sanitize_text_field($data['last_name']),
            'shipping_email' => sanitize_email($data['email']),
            'shipping_phone' => sanitize_text_field($data['phone'] ?? ''),
            'shipping_address' => sanitize_text_field($data['address']),
            'shipping_address_2' => sanitize_text_field($data['address_2'] ?? ''),
            'shipping_city' => sanitize_text_field($data['city']),
            'shipping_state' => sanitize_text_field($data['state'] ?? ''),
            'shipping_postcode' => sanitize_text_field($data['postcode'] ?? ''),
            'shipping_country' => sanitize_text_field($data['country']),
            
            // Billing info (use shipping if not provided)
            'billing_first_name' => sanitize_text_field($data['billing_first_name'] ?? $data['first_name']),
            'billing_last_name' => sanitize_text_field($data['billing_last_name'] ?? $data['last_name']),
            'billing_email' => sanitize_email($data['billing_email'] ?? $data['email']),
            'billing_phone' => sanitize_text_field($data['billing_phone'] ?? $data['phone'] ?? ''),
            'billing_address' => sanitize_text_field($data['billing_address'] ?? $data['address']),
            'billing_address_2' => sanitize_text_field($data['billing_address_2'] ?? $data['address_2'] ?? ''),
            'billing_city' => sanitize_text_field($data['billing_city'] ?? $data['city']),
            'billing_state' => sanitize_text_field($data['billing_state'] ?? $data['state'] ?? ''),
            'billing_postcode' => sanitize_text_field($data['billing_postcode'] ?? $data['postcode'] ?? ''),
            'billing_country' => sanitize_text_field($data['billing_country'] ?? $data['country']),
            
            'notes' => sanitize_textarea_field($data['notes'] ?? ''),
        ];
        
        $this->wpdb->insert($this->db->orders_table, $order_data);
        $order_id = $this->wpdb->insert_id;
        
        if (!$order_id) {
            return new WP_Error('order_failed', 'Failed to create order.');
        }
        
        // Create order items
        foreach ($cart['items'] as $item) {
            $this->wpdb->insert($this->db->order_items_table, [
                'order_id' => $order_id,
                'product_id' => $item->product_id,
                'vendor_id' => $item->vendor_id,
                'product_name' => $item->name,
                'product_sku' => $item->sku,
                'product_image' => $item->image,
                'quantity' => $item->quantity,
                'unit_price' => $item->unit_price,
                'total' => $item->line_total,
                'attributes' => is_array($item->attributes) ? json_encode($item->attributes) : $item->attributes,
            ]);
            
            // Update product stock
            $this->wpdb->query($this->wpdb->prepare(
                "UPDATE {$this->db->products_table} 
                 SET stock_quantity = stock_quantity - %d,
                     sales_count = sales_count + %d
                 WHERE id = %d AND stock_quantity IS NOT NULL",
                $item->quantity, $item->quantity, $item->product_id
            ));
            
            // Update vendor sales
            if ($item->vendor_id) {
                $this->wpdb->query($this->wpdb->prepare(
                    "UPDATE {$this->db->vendors_table} 
                     SET total_sales = total_sales + %f
                     WHERE id = %d",
                    $item->line_total, $item->vendor_id
                ));
            }
        }
        
        // Update coupon usage
        if ($cart['coupon']) {
            $this->wpdb->query($this->wpdb->prepare(
                "UPDATE {$this->db->coupons_table} 
                 SET times_used = times_used + 1 WHERE code = %s",
                $cart['coupon']['code']
            ));
        }
        
        // Clear cart
        NexMart_Cart::get_instance()->clear_cart();
        
        // Process payment (simplified - for demo)
        if ($data['payment_method'] === 'cod') {
            $this->update_order_status($order_id, 'processing');
        }
        
        return [
            'order_id' => $order_id,
            'order_number' => $order_number,
            'total' => $cart['total'],
            'message' => 'Order placed successfully!',
        ];
    }
    
    /**
     * Generate unique order number
     */
    private function generate_order_number() {
        $prefix = 'NXM';
        $timestamp = date('ymd');
        $random = strtoupper(substr(uniqid(), -4));
        return $prefix . $timestamp . $random;
    }
    
    /**
     * Get user orders
     */
    public function get_user_orders($user_id = null, $limit = 10, $offset = 0) {
        if (!$user_id) {
            $user_id = get_current_user_id();
        }
        
        if (!$user_id) {
            return [];
        }
        
        $orders = $this->wpdb->get_results($this->wpdb->prepare(
            "SELECT * FROM {$this->db->orders_table} 
             WHERE user_id = %d 
             ORDER BY created_at DESC 
             LIMIT %d OFFSET %d",
            $user_id, $limit, $offset
        ));
        
        foreach ($orders as &$order) {
            $order->items = $this->get_order_items($order->id);
            $order->item_count = array_sum(array_column($order->items, 'quantity'));
        }
        
        return $orders;
    }
    
    /**
     * Get single order
     */
    public function get_order($order_id, $user_id = null) {
        $where = ['id = %d'];
        $params = [$order_id];
        
        if ($user_id) {
            $where[] = 'user_id = %d';
            $params[] = $user_id;
        }
        
        $order = $this->wpdb->get_row($this->wpdb->prepare(
            "SELECT * FROM {$this->db->orders_table} WHERE " . implode(' AND ', $where),
            ...$params
        ));
        
        if ($order) {
            $order->items = $this->get_order_items($order->id);
            $order->timeline = $this->get_order_timeline($order);
        }
        
        return $order;
    }
    
    /**
     * Get order by order number
     */
    public function get_order_by_number($order_number, $email = null) {
        $where = ['order_number = %s'];
        $params = [$order_number];
        
        if ($email) {
            $where[] = '(shipping_email = %s OR billing_email = %s)';
            $params[] = $email;
            $params[] = $email;
        }
        
        $order = $this->wpdb->get_row($this->wpdb->prepare(
            "SELECT * FROM {$this->db->orders_table} WHERE " . implode(' AND ', $where),
            ...$params
        ));
        
        if ($order) {
            $order->items = $this->get_order_items($order->id);
        }
        
        return $order;
    }
    
    /**
     * Get order items
     */
    public function get_order_items($order_id) {
        return $this->wpdb->get_results($this->wpdb->prepare(
            "SELECT oi.*, v.store_name as vendor_name 
             FROM {$this->db->order_items_table} oi
             LEFT JOIN {$this->db->vendors_table} v ON oi.vendor_id = v.id
             WHERE oi.order_id = %d",
            $order_id
        ));
    }
    
    /**
     * Get order timeline/history
     */
    private function get_order_timeline($order) {
        $timeline = [];
        
        $timeline[] = [
            'status' => 'ordered',
            'label' => 'Order Placed',
            'date' => $order->created_at,
            'completed' => true,
        ];
        
        $statuses = [
            'pending' => ['label' => 'Payment Pending', 'order' => 1],
            'processing' => ['label' => 'Processing', 'order' => 2],
            'shipped' => ['label' => 'Shipped', 'order' => 3],
            'delivered' => ['label' => 'Delivered', 'order' => 4],
        ];
        
        $current_order = $statuses[$order->status]['order'] ?? 0;
        
        foreach ($statuses as $status => $info) {
            if ($status === 'pending' && $order->payment_status === 'paid') {
                continue;
            }
            
            $timeline[] = [
                'status' => $status,
                'label' => $info['label'],
                'date' => $info['order'] <= $current_order ? $order->updated_at : null,
                'completed' => $info['order'] <= $current_order,
            ];
        }
        
        return $timeline;
    }
    
    /**
     * Update order status
     */
    public function update_order_status($order_id, $status, $note = '') {
        $valid_statuses = ['pending', 'processing', 'shipped', 'delivered', 'cancelled', 'refunded'];
        
        if (!in_array($status, $valid_statuses)) {
            return new WP_Error('invalid_status', 'Invalid order status.');
        }
        
        $update = ['status' => $status];
        
        if ($status === 'shipped') {
            $update['shipped_at'] = current_time('mysql');
        } elseif ($status === 'delivered') {
            $update['delivered_at'] = current_time('mysql');
        }
        
        $this->wpdb->update(
            $this->db->orders_table,
            $update,
            ['id' => $order_id]
        );
        
        return $this->get_order($order_id);
    }
    
    /**
     * Cancel order
     */
    public function cancel_order($order_id, $user_id = null) {
        $order = $this->get_order($order_id, $user_id);
        
        if (!$order) {
            return new WP_Error('order_not_found', 'Order not found.');
        }
        
        if (!in_array($order->status, ['pending', 'processing'])) {
            return new WP_Error('cannot_cancel', 'This order cannot be cancelled.');
        }
        
        // Restore stock
        foreach ($order->items as $item) {
            $this->wpdb->query($this->wpdb->prepare(
                "UPDATE {$this->db->products_table} 
                 SET stock_quantity = stock_quantity + %d,
                     sales_count = sales_count - %d
                 WHERE id = %d AND stock_quantity IS NOT NULL",
                $item->quantity, $item->quantity, $item->product_id
            ));
        }
        
        return $this->update_order_status($order_id, 'cancelled');
    }
    
    /**
     * Get orders by vendor
     */
    public function get_vendor_orders($vendor_id, $status = null, $limit = 20, $offset = 0) {
        $where = ['oi.vendor_id = %d'];
        $params = [$vendor_id];
        
        if ($status) {
            $where[] = 'o.status = %s';
            $params[] = $status;
        }
        
        $params[] = $limit;
        $params[] = $offset;
        
        $orders = $this->wpdb->get_results($this->wpdb->prepare(
            "SELECT DISTINCT o.* FROM {$this->db->orders_table} o
             INNER JOIN {$this->db->order_items_table} oi ON o.id = oi.order_id
             WHERE " . implode(' AND ', $where) . "
             ORDER BY o.created_at DESC
             LIMIT %d OFFSET %d",
            ...$params
        ));
        
        // Get only vendor's items for each order
        foreach ($orders as &$order) {
            $order->items = $this->wpdb->get_results($this->wpdb->prepare(
                "SELECT * FROM {$this->db->order_items_table} 
                 WHERE order_id = %d AND vendor_id = %d",
                $order->id, $vendor_id
            ));
            $order->vendor_total = array_sum(array_column($order->items, 'total'));
        }
        
        return $orders;
    }
    
    /**
     * Get order statistics
     */
    public function get_order_stats($vendor_id = null) {
        $where = '';
        $params = [];
        
        if ($vendor_id) {
            $where = "INNER JOIN {$this->db->order_items_table} oi ON o.id = oi.order_id AND oi.vendor_id = %d";
            $params[] = $vendor_id;
        }
        
        $stats = $this->wpdb->get_row($this->wpdb->prepare(
            "SELECT 
                COUNT(DISTINCT o.id) as total_orders,
                SUM(CASE WHEN o.status = 'pending' THEN 1 ELSE 0 END) as pending_orders,
                SUM(CASE WHEN o.status = 'processing' THEN 1 ELSE 0 END) as processing_orders,
                SUM(CASE WHEN o.status = 'shipped' THEN 1 ELSE 0 END) as shipped_orders,
                SUM(CASE WHEN o.status = 'delivered' THEN 1 ELSE 0 END) as delivered_orders,
                SUM(CASE WHEN o.status = 'cancelled' THEN 1 ELSE 0 END) as cancelled_orders
             FROM {$this->db->orders_table} o {$where}",
            ...$params ?: [1]
        ));
        
        // Get revenue stats
        if ($vendor_id) {
            $revenue = $this->wpdb->get_row($this->wpdb->prepare(
                "SELECT 
                    SUM(oi.total) as total_revenue,
                    SUM(CASE WHEN o.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY) THEN oi.total ELSE 0 END) as monthly_revenue,
                    SUM(CASE WHEN o.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY) THEN oi.total ELSE 0 END) as weekly_revenue
                 FROM {$this->db->order_items_table} oi
                 JOIN {$this->db->orders_table} o ON oi.order_id = o.id
                 WHERE oi.vendor_id = %d AND o.status NOT IN ('cancelled', 'refunded')",
                $vendor_id
            ));
        } else {
            $revenue = $this->wpdb->get_row(
                "SELECT 
                    SUM(total) as total_revenue,
                    SUM(CASE WHEN created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY) THEN total ELSE 0 END) as monthly_revenue,
                    SUM(CASE WHEN created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY) THEN total ELSE 0 END) as weekly_revenue
                 FROM {$this->db->orders_table}
                 WHERE status NOT IN ('cancelled', 'refunded')"
            );
        }
        
        return [
            'orders' => $stats,
            'revenue' => $revenue,
        ];
    }
    
    // AJAX Handlers
    
    public function ajax_create_order() {
        check_ajax_referer('nexmart_nonce', 'nonce');
        
        $data = [
            'first_name' => sanitize_text_field($_POST['first_name'] ?? ''),
            'last_name' => sanitize_text_field($_POST['last_name'] ?? ''),
            'email' => sanitize_email($_POST['email'] ?? ''),
            'phone' => sanitize_text_field($_POST['phone'] ?? ''),
            'address' => sanitize_text_field($_POST['address'] ?? ''),
            'address_2' => sanitize_text_field($_POST['address_2'] ?? ''),
            'city' => sanitize_text_field($_POST['city'] ?? ''),
            'state' => sanitize_text_field($_POST['state'] ?? ''),
            'postcode' => sanitize_text_field($_POST['postcode'] ?? ''),
            'country' => sanitize_text_field($_POST['country'] ?? ''),
            'payment_method' => sanitize_text_field($_POST['payment_method'] ?? 'cod'),
            'notes' => sanitize_textarea_field($_POST['notes'] ?? ''),
        ];
        
        $result = $this->create_order($data);
        
        if (is_wp_error($result)) {
            wp_send_json_error(['message' => $result->get_error_message()]);
        }
        
        wp_send_json_success($result);
    }
    
    public function ajax_get_orders() {
        if (!is_user_logged_in()) {
            wp_send_json_error(['message' => 'Please log in to view orders.']);
        }
        
        $limit = isset($_GET['limit']) ? intval($_GET['limit']) : 10;
        $offset = isset($_GET['offset']) ? intval($_GET['offset']) : 0;
        
        $orders = $this->get_user_orders(get_current_user_id(), $limit, $offset);
        wp_send_json_success(['orders' => $orders]);
    }
    
    public function ajax_get_order() {
        $order_id = intval($_GET['order_id'] ?? 0);
        $order_number = sanitize_text_field($_GET['order_number'] ?? '');
        
        if ($order_number) {
            $email = sanitize_email($_GET['email'] ?? '');
            $order = $this->get_order_by_number($order_number, $email);
        } elseif ($order_id && is_user_logged_in()) {
            $order = $this->get_order($order_id, get_current_user_id());
        } else {
            wp_send_json_error(['message' => 'Invalid request.']);
        }
        
        if (!$order) {
            wp_send_json_error(['message' => 'Order not found.']);
        }
        
        wp_send_json_success(['order' => $order]);
    }
    
    public function ajax_update_order_status() {
        check_ajax_referer('nexmart_nonce', 'nonce');
        
        if (!current_user_can('administrator')) {
            // Check if vendor owns this order
            $auth = NexMart_Auth::get_instance();
            if (!$auth->is_vendor()) {
                wp_send_json_error(['message' => 'Unauthorized.']);
            }
        }
        
        $order_id = intval($_POST['order_id']);
        $status = sanitize_text_field($_POST['status']);
        
        $result = $this->update_order_status($order_id, $status);
        
        if (is_wp_error($result)) {
            wp_send_json_error(['message' => $result->get_error_message()]);
        }
        
        wp_send_json_success(['order' => $result, 'message' => 'Order status updated.']);
    }
    
    public function ajax_cancel_order() {
        check_ajax_referer('nexmart_nonce', 'nonce');
        
        if (!is_user_logged_in()) {
            wp_send_json_error(['message' => 'Please log in.']);
        }
        
        $order_id = intval($_POST['order_id']);
        $result = $this->cancel_order($order_id, get_current_user_id());
        
        if (is_wp_error($result)) {
            wp_send_json_error(['message' => $result->get_error_message()]);
        }
        
        wp_send_json_success(['order' => $result, 'message' => 'Order cancelled successfully.']);
    }
}
