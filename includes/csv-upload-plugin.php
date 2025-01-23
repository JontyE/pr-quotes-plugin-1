<?php
/**
 * CSV Upload Processing for PR Quotes Plugin - Background Processing with Error Logging
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

require_once plugin_dir_path(__FILE__) . 'database-functions.php';


if (!function_exists('pr_quotes_insert_quote')) {
    error_log("Error: pr_quotes_insert_quote() function is missing.");
    wp_send_json_error(['message' => 'Internal server error. Function missing.']);
    return;
}

add_action('wp_ajax_pr_quotes_process_csv', 'pr_quotes_process_csv');
add_action('wp_ajax_nopriv_pr_quotes_process_csv', 'pr_quotes_process_csv'); // Allow non-logged users

function pr_quotes_process_csv() {
    global $wpdb;

    if (!isset($_POST['file_order']) || !isset($_FILES['csv_file'])) {
        error_log("Error: Missing file data in AJAX request.");
        wp_send_json_error(['message' => 'Missing file data']);
    }

    $file_order = intval($_POST['file_order']); // Get the sequence number
    $upload_dir = wp_upload_dir()['path'] . '/';
    $file_tmp = $_FILES['csv_file']['tmp_name'];
    $destination = $upload_dir . basename($_FILES['csv_file']['name']);

    if (!move_uploaded_file($file_tmp, $destination)) {
        error_log("Error: Failed to move uploaded file - " . $_FILES['csv_file']['name']);
        wp_send_json_error(['message' => 'Failed to move uploaded file']);
    }

    error_log("Processing CSV file (Order: $file_order): " . $destination);

    $success = pr_quotes_insert_csv_data($destination);

    if ($success) {
        error_log("Success: File $file_order processed successfully.");
        wp_send_json_success(['message' => "File $file_order processed successfully"]);
    } else {
        error_log("Error: File $file_order processing failed.");
        wp_send_json_error(['message' => "File $file_order processing failed"]);
    }
}

/**
 * Insert CSV data into the database after validation and sanitization.
 */
/**
 * Insert CSV data into the correct tables, ensuring no duplicates.
 */
function pr_quotes_insert_csv_data($file_path) {
    global $wpdb;

    if (!file_exists($file_path)) {
        error_log("CSV file not found: $file_path");
        return false;
    }

    error_log("Processing CSV file: $file_path");

    if (($handle = fopen($file_path, 'r')) !== FALSE) {
        $headers = fgetcsv($handle, 1000, ",");
        
        if (!$headers) {
            error_log("Error reading CSV headers from: $file_path");
            fclose($handle);
            return false;
        }

        // Remove BOM and sanitize column names
        $headers = array_map(function($header) {
            $header = preg_replace('/^\x{FEFF}/u', '', $header); // Remove BOM
            $header = trim($header, "\" \t\n\r\0\x0B"); // Trim extra spaces
            $header = preg_replace('/[^A-Za-z0-9_]/', '_', $header); // Replace special characters
            return strtolower($header); // Convert to lowercase
        }, $headers);

        error_log("Sanitized CSV Headers: " . json_encode($headers));

        // Skip the first row (headers already extracted)
        fgetcsv($handle, 1000, ",");

        while (($row = fgetcsv($handle, 1000, ",")) !== FALSE) {
            if (count($headers) !== count($row)) {
                error_log("Skipping row due to column mismatch: " . json_encode($row));
                continue;
            }

            $data = array_combine($headers, $row);
            if (!$data) {
                error_log("Error processing row: " . json_encode($row));
                continue;
            }

            // Fill missing NOT NULL fields with "Empty"
            foreach ($data as $key => $value) {
                if (empty($value)) {
                    $data[$key] = "Empty";
                }
            }

            $data = pr_quotes_sanitize_csv_data($data);

            // Determine the table and check for existing records
            if (isset($data['quote_number']) && isset($data['email'])) {
                // Insert into Quotes Table
                $exists = $wpdb->get_var($wpdb->prepare(
                    "SELECT COUNT(*) FROM {$wpdb->prefix}pr_quotes_quotes WHERE quote_number = %d",
                    $data['quote_number']
                ));
                if ($exists > 0) {
                    error_log("Skipping duplicate quote: " . $data['quote_number']);
                    continue;
                }

                $result = pr_quotes_insert_quote($data);
            } elseif (isset($data['item_code']) && isset($data['quote_number'])) {
                // Insert into Line Items Table
                $exists = $wpdb->get_var($wpdb->prepare(
                    "SELECT COUNT(*) FROM {$wpdb->prefix}pr_quotes_line_items WHERE item_code = %d",
                    $data['item_code']
                ));
                if ($exists > 0) {
                    error_log("Skipping duplicate item: " . $data['item_code']);
                    continue;
                }

                $result = pr_quotes_insert_line_item($data);
            } elseif (isset($data['email'])) {
                // Insert into Clients Table
                $exists = $wpdb->get_var($wpdb->prepare(
                    "SELECT COUNT(*) FROM {$wpdb->prefix}pr_quotes_clients WHERE email = %s",
                    $data['email']
                ));
                if ($exists > 0) {
                    error_log("Skipping duplicate client: " . $data['email']);
                    continue;
                }

                $result = pr_quotes_insert_client($data);
            } else {
                error_log("Skipping row: Missing required identifiers.");
                continue;
            }

            if ($result === false) {
                error_log("Failed to insert row: " . json_encode($data));
            } else {
                error_log("Successfully inserted row: " . json_encode($data));
            }
        }
        fclose($handle);
        return true;
    } else {
        error_log("Failed to open CSV file: $file_path");
        return false;
    }
}

?>
