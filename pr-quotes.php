<?php
/*
Plugin Name: PR Quotes
Plugin URI: https://www.example.com/
Description: A plugin to upload PDFs and dynamically populate and display quote data.
Version: 1.0
Author: Esser Digital
Author URI: https://www.example.com/
*/

define('PR_QUOTES_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('PR_QUOTES_PLUGIN_URL', plugin_dir_url(__FILE__));

// Include necessary files
require_once PR_QUOTES_PLUGIN_DIR . 'includes/admin-styles.php';
require_once PR_QUOTES_PLUGIN_DIR . 'includes/admin-menu.php';
require_once PR_QUOTES_PLUGIN_DIR . 'includes/pdf-processing.php';
require_once PR_QUOTES_PLUGIN_DIR . 'includes/database-functions.php';
require_once PR_QUOTES_PLUGIN_DIR . 'includes/activation-hooks.php';
require_once PR_QUOTES_PLUGIN_DIR . 'includes/csv-upload-plugin.php';




// Uninstall hook
register_uninstall_hook(__FILE__, 'pr_quotes_uninstall');

function pr_quotes_uninstall() {
    if (get_option('delete_tables_on_uninstall', false)) {
        require_once PR_QUOTES_PLUGIN_DIR . 'includes/database-functions.php';
        pr_quotes_delete_tables();
    }

    // Delete the option itself
    delete_option('delete_tables_on_uninstall');
}



