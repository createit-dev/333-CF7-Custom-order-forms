<?php
/**
 * Plugin Name: Contact Form 7 - Product Orders Extension
 * Description: Extends Contact Form 7 with product ordering capabilities including dynamic fields, validation, and database storage
 * Version: 1.0.0
 */

define('CF7_PRODUCT_ORDERS_VERSION', '1.0.0');

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Handle plugin activation
 */
function cf7_product_orders_activate() {
    global $wpdb;

    $table_name = $wpdb->prefix . 'cf7_orders';
    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE IF NOT EXISTS $table_name (
        id bigint(20) NOT NULL AUTO_INCREMENT,
        customer_email varchar(100) NOT NULL,
        customer_name varchar(100) NOT NULL,
        products longtext NOT NULL,
        created_at datetime NOT NULL,
        PRIMARY KEY  (id)
    ) $charset_collate;";

    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);

    // Set version in options table
    add_option('cf7_product_orders_version', '1.0.0');
}
register_activation_hook(__FILE__, 'cf7_product_orders_activate');

/**
 * Handle plugin deactivation
 */
function cf7_product_orders_deactivate() {
    // Cleanup tasks if needed
}
register_deactivation_hook(__FILE__, 'cf7_product_orders_deactivate');

// Plugin main class
class CF7_Product_Orders {
    // Database table name
    private $table_name;

    // Singleton instance
    private static $instance = null;

    /**
     * Get singleton instance
     */
    public static function get_instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor
     */
    private function __construct() {
        global $wpdb;
        $this->table_name = $wpdb->prefix . 'cf7_orders';


        // Check if Contact Form 7 is active
        add_action('admin_init', array($this, 'check_cf7_dependency'));

        // Register hooks
        if ($this->is_cf7_active()) {
            add_filter('wpcf7_posted_data', array($this, 'process_product_data'));
            add_filter('wpcf7_validate_text*', array($this, 'validate_product_quantity'), 10, 2);
            add_action('wpcf7_before_send_mail', array($this, 'customize_email'));
            add_action('wpcf7_mail_sent', array($this, 'save_order'));
            add_action('wp_enqueue_scripts', array($this, 'cf7_product_orders_enqueue_assets'));
        }
    }

    /**
     * Check if Contact Form 7 is active
     */
    private function is_cf7_active() {
        return class_exists('WPCF7');
    }

    /**
     * Check Contact Form 7 dependency
     */
    public function check_cf7_dependency() {
        if (!$this->is_cf7_active()) {
            add_action('admin_notices', function() {
                echo '<div class="error"><p>';
                echo esc_html__('Contact Form 7 - Product Orders Extension requires Contact Form 7 to be installed and activated.', 'cf7-product-orders');
                echo '</p></div>';
            });
        }
    }

    public function cf7_product_orders_enqueue_assets() {
        wp_enqueue_style(
            'cf7-product-orders-style',
            plugins_url('style.css', __FILE__),
            array(),
            CF7_PRODUCT_ORDERS_VERSION
        );

        wp_enqueue_script(
            'cf7-product-orders-script',
            plugins_url('script.js', __FILE__),
            array('jquery'),
            CF7_PRODUCT_ORDERS_VERSION,
            true
        );
    }

    /**
     * Process dynamic product data from the form
     */
    public function process_product_data($posted_data) {
        if (!isset($posted_data['product-name']) || !is_array($posted_data['product-name'])) {
            return $posted_data;
        }

        $products = array();

        // Get the first array (index 0) which contains our product data
        $names = isset($posted_data['product-name'][0]) ? $posted_data['product-name'][0] : array();
        $quantities = isset($posted_data['product-quantity'][0]) ? $posted_data['product-quantity'][0] : array();
        $notes = isset($posted_data['product-notes'][0]) ? $posted_data['product-notes'][0] : array();

        // Loop through each index
        foreach ($names as $index => $name) {
            // Make sure all required data exists for this index
            if (!empty($name) && isset($quantities[$index])) {
                $products[] = array(
                    'name' => sanitize_text_field($name),
                    'quantity' => absint($quantities[$index]),
                    'notes' => isset($notes[$index]) ? sanitize_textarea_field($notes[$index]) : ''
                );
            }
        }

        $posted_data['formatted_products'] = $products;
        return $posted_data;
    }

    /**
     * Validate product quantity
     */
    public function validate_product_quantity($result, $tag) {
        $name = $tag->name;

        if (strpos($name, 'product-quantity') === 0) {
            $value = isset($_POST[$name]) ? intval($_POST[$name]) : 0;

            if ($value <= 0 || $value > 100) {
                $result->invalidate($tag, 'Please enter a quantity between 1 and 100');
            }
        }

        return $result;
    }

    /**
     * Customize email with HTML table of products
     */
    public function customize_email($contact_form) {
        $submission = WPCF7_Submission::get_instance();

        if (!$submission || !isset($submission->get_posted_data()['formatted_products'])) {
            return;
        }

        $products = $submission->get_posted_data()['formatted_products'];

        // Create HTML table
        $html = '<h2>Order Details</h2>';
        $html .= '<table style="width:100%; border-collapse:collapse; margin-top:20px;">';
        $html .= '<tr style="background:#f8f8f8;">
                    <th style="padding:10px; border:1px solid #ddd;">Product</th>
                    <th style="padding:10px; border:1px solid #ddd;">Quantity</th>
                    <th style="padding:10px; border:1px solid #ddd;">Notes</th>
                  </tr>';

        foreach ($products as $product) {
            $html .= sprintf(
                '<tr>
                    <td style="padding:10px; border:1px solid #ddd;">%s</td>
                    <td style="padding:10px; border:1px solid #ddd;">%d</td>
                    <td style="padding:10px; border:1px solid #ddd;">%s</td>
                </tr>',
                esc_html($product['name']),
                esc_html($product['quantity']),
                esc_html($product['notes'])
            );
        }

        $html .= '</table>';

        // Add table to email
        $mail = $contact_form->prop('mail');
        $mail['body'] = str_replace('[product-table]', $html, $mail['body']);
        $contact_form->set_properties(array('mail' => $mail));
    }

    /**
     * Save order to database after form submission
     */
    public function save_order($contact_form) {
        global $wpdb;
        $submission = WPCF7_Submission::get_instance();

        if (!$submission) {
            error_log('No submission instance found');
            return;
        }

        $data = $submission->get_posted_data();

        if (!isset($data['formatted_products']) || empty($data['formatted_products'])) {
            error_log('No formatted products found in submission');
            return;
        }

        try {
            // Insert order into database
            $result = $wpdb->insert(
                $this->table_name,
                array(
                    'customer_email' => sanitize_email($data['your-email']),
                    'customer_name' => sanitize_text_field($data['your-name']),
                    'products' => json_encode($data['formatted_products']),
                    'created_at' => current_time('mysql')
                ),
                array('%s', '%s', '%s', '%s')
            );

            if ($result === false) {
                error_log('Database insert failed: ' . $wpdb->last_error);
            } else {
                error_log('Order saved successfully with ID: ' . $wpdb->insert_id);
            }
        } catch (Exception $e) {
            error_log('Exception while saving order: ' . $e->getMessage());
        }
    }
}

// Initialize the plugin
function cf7_product_orders_init() {
    return CF7_Product_Orders::get_instance();
}
add_action('plugins_loaded', 'cf7_product_orders_init');
