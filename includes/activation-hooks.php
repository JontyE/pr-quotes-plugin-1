<?php
// Function to create tables on plugin activation
register_activation_hook(__FILE__, 'pr_quotes_create_tables');

// Function to drop tables on plugin deactivation
function pr_quotes_drop_tables() {
    global $wpdb;

    $client_table = $wpdb->prefix . 'prompt_client';
    $quote_table = $wpdb->prefix . 'prompt_quote';
    $line_items_table = $wpdb->prefix . 'prompt_line_items';

    $wpdb->query("DROP TABLE IF EXISTS $line_items_table");
    $wpdb->query("DROP TABLE IF EXISTS $quote_table");
    $wpdb->query("DROP TABLE IF EXISTS $client_table");
}
register_deactivation_hook(__FILE__, 'pr_quotes_drop_tables');

require_once plugin_dir_path(__FILE__) . 'database-functions.php';

if (!function_exists('pr_quotes_activate')) {
    function pr_quotes_activate() {
        pr_quotes_create_tables(); // Call the function from database-functions.php
    }
}
register_activation_hook(PR_QUOTES_PLUGIN_DIR . 'pr-quotes.php', 'pr_quotes_activate');
