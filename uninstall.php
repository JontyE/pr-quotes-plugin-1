<?php
// File: uninstall.php

if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

// Check user preference
$delete_data = get_option('pr_quotes_delete_data_on_uninstall', 'no');
if ($delete_data === 'yes') {
    global $wpdb;

    $client_table = $wpdb->prefix . 'prompt_client';
    $quote_table = $wpdb->prefix . 'prompt_quote';
    $line_items_table = $wpdb->prefix . 'prompt_line_items';

    $wpdb->query("DROP TABLE IF EXISTS $client_table");
    $wpdb->query("DROP TABLE IF EXISTS $quote_table");
    $wpdb->query("DROP TABLE IF EXISTS $line_items_table");

    delete_option('pr_quotes_delete_data_on_uninstall');
}
