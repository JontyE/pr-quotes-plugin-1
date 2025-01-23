<?php
/**
 * Database Functions for PR Quotes Plugin
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

/**
 * Create required database tables on plugin activation
 */
function pr_quotes_create_tables() {
    global $wpdb;
    $charset_collate = $wpdb->get_charset_collate();

    // Clients Table
    $sql_clients = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}pr_quotes_clients (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        first_name VARCHAR(100) NOT NULL,
        last_name VARCHAR(100) NOT NULL,
        company_name VARCHAR(255) NULL,
        email VARCHAR(255) NOT NULL UNIQUE,
        phone VARCHAR(50) NULL,
        address TEXT NULL,
        city VARCHAR(100) NULL,
        state VARCHAR(100) NULL,
        zip VARCHAR(20) NULL,
        country VARCHAR(100) NULL,
        last_changed TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) $charset_collate;";

    // Quotes Table
    $sql_quotes = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}pr_quotes_quotes (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        quote_number INT(11) NOT NULL UNIQUE,
        quote_title VARCHAR(255) NOT NULL,
        from_name VARCHAR(255) NOT NULL,
        for_name VARCHAR(255) NOT NULL,
        email VARCHAR(255) NOT NULL,
        total_value DECIMAL(10,2) NOT NULL,
        currency VARCHAR(10) NOT NULL,
        quote_status VARCHAR(50) NOT NULL,
        expiry_date DATE NULL
    ) $charset_collate;";

    // Line Items Table
    $sql_line_items = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}pr_quotes_line_items (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        quote_number INT(11) NOT NULL,
        item_code INT(11) NOT NULL,
        item_title VARCHAR(255) NOT NULL,
        unit_price DECIMAL(10,2) NOT NULL,
        quantity INT(11) NOT NULL,
        discount DECIMAL(10,2) NULL DEFAULT 0.00,
        item_total DECIMAL(10,2) NOT NULL,
        sales_category VARCHAR(100) NULL
    ) $charset_collate;";

    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql_clients);
    dbDelta($sql_quotes);
    dbDelta($sql_line_items);
}

// Hook table creation on plugin activation
register_activation_hook(__FILE__, 'pr_quotes_create_tables');

/**
 * Insert a client into the database, ensuring correct mapping.
 */
function pr_quotes_insert_client($data) {
    global $wpdb;

    if (!isset($data['email']) || empty($data['email'])) {
        error_log("Skipping client insert due to missing email: " . json_encode($data));
        return false;
    }

    if (isset($data['phone']) && strlen($data['phone']) > 50) {
        $data['phone'] = substr($data['phone'], 0, 50);
    }

    // Check if client exists
    $exists = $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM {$wpdb->prefix}pr_quotes_clients WHERE email = %s",
        $data['email']
    ));
    if ($exists > 0) {
        error_log("Skipping duplicate client: " . $data['email']);
        return true;
    }

    $result = $wpdb->insert("{$wpdb->prefix}pr_quotes_clients", [
        'first_name'   => $data['first_name'],
        'last_name'    => $data['last_name'],
        'company_name' => $data['company_name'],
        'email'        => $data['email'],
        'phone'        => $data['phone'],
        'address'      => $data['address'],
        'city'         => $data['city'],
        'state'        => $data['state'],
        'zip'          => $data['zip'],
        'country'      => $data['country'],
        'last_changed' => $data['last_changed'],
    ]);

    return $result !== false;
}

/**
 * Insert a quote into the database.
 */
function pr_quotes_insert_quote($data) {
    global $wpdb;

    if (!isset($data['quote_number']) || !isset($data['email'])) {
        error_log("Skipping quote insert due to missing required fields: " . json_encode($data));
        return false;
    }

    unset($data['first_name'], $data['last_name']);

    $exists = $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM {$wpdb->prefix}pr_quotes_quotes WHERE quote_number = %d",
        $data['quote_number']
    ));
    if ($exists > 0) {
        error_log("Skipping duplicate quote: " . $data['quote_number']);
        return true;
    }

    $result = $wpdb->insert("{$wpdb->prefix}pr_quotes_quotes", [
        'quote_number'    => $data['quote_number'],
        'quote_title'     => $data['quote_title'],
        'from_name'       => $data['from_name'],
        'for_name'        => $data['for_name'],
        'email'           => $data['email'],
        'total_value'     => $data['total_value'],
        'currency'        => $data['currency'],
        'quote_status'    => $data['quote_status'],
        'expiry_date'     => $data['expiry_date'],
    ]);

    return $result !== false;
}

/**
 * Insert a line item into the database.
 */
function pr_quotes_insert_line_item($data) {
    global $wpdb;

    if (!isset($data['quote_number']) || !isset($data['item_code'])) {
        error_log("Skipping line item insert due to missing required fields: " . json_encode($data));
        return false;
    }

    unset($data['cost_price']);

    $exists = $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM {$wpdb->prefix}pr_quotes_line_items WHERE item_code = %d",
        $data['item_code']
    ));
    if ($exists > 0) {
        error_log("Skipping duplicate line item: " . $data['item_code']);
        return true;
    }

    $result = $wpdb->insert("{$wpdb->prefix}pr_quotes_line_items", [
        'quote_number'   => $data['quote_number'],
        'item_code'      => $data['item_code'],
        'item_title'     => $data['item_title'],
        'unit_price'     => $data['unit_price'],
        'quantity'       => $data['quantity'],
        'discount'       => $data['discount'],
        'item_total'     => $data['item_total'],
        'sales_category' => $data['sales_category'],
    ]);

    return $result !== false;
}

/**
 * Sanitize CSV Data Before Inserting into the Database
 */
function pr_quotes_sanitize_csv_data($data) {
    $sanitized_data = [];

    foreach ($data as $key => $value) {
        // Remove non-ASCII characters
        $value = preg_replace('/[^\x20-\x7E]/', '', $value);

        // Trim whitespace and special characters
        $value = trim($value, "\" \t\n\r\0\x0B");

        // Replace empty values with "Empty" for NOT NULL fields
        if (empty($value)) {
            $value = "Empty";
        }

        $sanitized_data[$key] = $value;
    }

    return $sanitized_data;
}


/**
 * SQL from here
 * 
 * 
 */

/**
 * Retrieve all clients
 */
function pr_quotes_get_clients() {
    global $wpdb;
    return $wpdb->get_results("SELECT * FROM {$wpdb->prefix}pr_quotes_clients");
}

/**
 * Retrieve quotes for a specific client
 */
function pr_quotes_get_quotes_by_client($email) {
    global $wpdb;
    return $wpdb->get_results(
        $wpdb->prepare("SELECT * FROM {$wpdb->prefix}pr_quotes_quotes WHERE email = %s", $email)
    );
}

/**
 * Retrieve line items for a specific quote
 */
function pr_quotes_get_line_items($quote_number) {
    global $wpdb;
    return $wpdb->get_results(
        $wpdb->prepare("SELECT * FROM {$wpdb->prefix}pr_quotes_line_items WHERE quote_number = %d", $quote_number)
    );
}

/**
 * Delete a quote and its associated line items
 */
function pr_quotes_delete_quote($quote_number) {
    global $wpdb;
    $wpdb->delete("{$wpdb->prefix}pr_quotes_line_items", ['quote_number' => $quote_number]);
    return $wpdb->delete("{$wpdb->prefix}pr_quotes_quotes", ['quote_number' => $quote_number]);
}

add_action('wp_ajax_pr_quotes_search', 'pr_quotes_search');
add_action('wp_ajax_nopriv_pr_quotes_search', 'pr_quotes_search');

function pr_quotes_search() {
    global $wpdb;
    $query = isset($_POST['query']) ? sanitize_text_field($_POST['query']) : '';

    if (empty($query)) {
        wp_send_json_error(['message' => 'Invalid search query.']);
    }

    // Fetch quote details based on quote number or client name
    $quote = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}pr_quotes_quotes WHERE quote_number = %s OR for_name LIKE %s",
        $query, '%' . $query . '%'
    ), ARRAY_A);

    if (!$quote) {
        wp_send_json_error(['message' => 'Quote not found.']);
    }

    // Fetch line items for the quote
    $line_items = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}pr_quotes_line_items WHERE quote_number = %d",
        $quote['quote_number']
    ), ARRAY_A);

    // Generate HTML output
    ob_start();
    ?>
    <div class="card">
        <div class="card-header bg-dark text-white">Quote Details</div>
        <div class="card-body">
            <p><strong>Quote Number:</strong> <?php echo esc_html($quote['quote_number']); ?></p>
            <p><strong>Client:</strong> <?php echo esc_html($quote['for_name']); ?></p>
            <p><strong>Email:</strong> <?php echo esc_html($quote['email']); ?></p>
            <p><strong>Total Value:</strong> <?php echo esc_html($quote['total_value']); ?></p>
            <p><strong>Status:</strong> <?php echo esc_html($quote['quote_status']); ?></p>
            <p><strong>Expiry Date:</strong> <?php echo esc_html($quote['expiry_date']); ?></p>

            <h5>Line Items</h5>
            <table class="table table-striped">
                <thead>
                    <tr>
                        <th>Item Title</th>
                        <th>Unit Price</th>
                        <th>Quantity</th>
                        <th>Discount</th>
                        <th>Total</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($line_items as $item): ?>
                        <tr>
                            <td><?php echo esc_html($item['item_title']); ?></td>
                            <td><?php echo esc_html($item['unit_price']); ?></td>
                            <td><?php echo esc_html($item['quantity']); ?></td>
                            <td><?php echo esc_html($item['discount']); ?></td>
                            <td><?php echo esc_html($item['item_total']); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <a href="<?php echo esc_url(admin_url('admin-post.php?action=generate_quote_pdf&quote_number=' . $quote['quote_number'])); ?>" class="btn btn-success">
                Download Quote PDF
            </a>
        </div>
    </div>
    <?php
    $html = ob_get_clean();
    wp_send_json_success(['html' => $html]);
}

?>


