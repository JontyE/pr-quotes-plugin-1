<?php
// File: includes/pdf-processing.php

use Smalot\PdfParser\Parser;
use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\IOFactory;

// Include the PDF parser library
$autoload_path = PR_QUOTES_PLUGIN_DIR . 'vendor/autoload.php';
if (file_exists($autoload_path)) {
    require_once $autoload_path;
} else {
    error_log('PDF Parser library not found. Ensure dependencies are installed via Composer.');
    add_action('admin_notices', function () {
        echo '<div class="notice notice-error"><p>PDF Parser library is missing. Please install dependencies using Composer.</p></div>';
    });
}

/**
 * Extract raw text from a PDF file using Smalot PDF Parser.
 *
 * @param string $pdf_file_path Path to the PDF file.
 * @return string Extracted raw text or an empty string on failure.
 */
function extract_pdf_raw_text($pdf_file_path)
{
    if (!file_exists($pdf_file_path)) {
        error_log('PDF file does not exist: ' . $pdf_file_path);
        return '';
    }

    if (!class_exists('Smalot\PdfParser\Parser')) {
        error_log('PDF Parser library is not loaded.');
        return '';
    }

    try {
        $pdf = new Parser();
        $pdfDocument = $pdf->parseFile($pdf_file_path);
        $raw_text = $pdfDocument->getText();
        error_log('Extracted Raw Text: ' . $raw_text);
        return $raw_text;
    } catch (Exception $e) {
        error_log('Error parsing PDF: ' . $e->getMessage());
        return '';
    }
}


/**
 * Extract images from a PDF and save them to /pdf-images/, avoiding duplicates in hash-images.
 *
 * @param string $pdf_file_path Path to the PDF file.
 * @return array List of unique saved image paths.
 */
function extract_pdf_images($pdf_file_path)
{
    $images_dir = PR_QUOTES_PLUGIN_DIR . 'pdf-images/';
    $hash_images_dir = PR_QUOTES_PLUGIN_DIR . 'pdf-images/hash-images/';

    // Ensure both folders exist
    if (!file_exists($images_dir)) {
        mkdir($images_dir, 0755, true);
    }
    if (!file_exists($hash_images_dir)) {
        mkdir($hash_images_dir, 0755, true);
    }

    $parser = new Parser();
    $pdf = $parser->parseFile($pdf_file_path);
    $objects = $pdf->getObjectsByType('XObject');

    // SHA1 hash of the unwanted image
    $excluded_image_hash = '3cb93dbe5d2e6aa536cfe7d511d9eb69';

    // Load existing images and their hashes from pdf-images/
    $existing_images = [];
    foreach (glob($images_dir . "*.{jpg,png}", GLOB_BRACE) as $file) {
        $existing_images[sha1_file($file)] = $file;  
    }

    // Load existing images from hash-images/ to exclude them
    $excluded_hashes = [];
    foreach (glob($hash_images_dir . "*.{jpg,png}", GLOB_BRACE) as $file) {
        $excluded_hashes[sha1_file($file)] = true;
    }

    $saved_images = [];

    foreach ($objects as $key => $object) {
        if ($object->get('Subtype') == 'Image') {
            $image_data = $object->getContent();
            $image_hash = sha1($image_data);

            // Skip if the image is in hash-images/
            if (isset($excluded_hashes[$image_hash])) {
                error_log("Skipping previously deleted image: $image_hash");
                continue;
            }

            // Skip if the image is excluded
            if ($image_hash === $excluded_image_hash) {
                error_log("Excluded unwanted image: $image_hash");
                continue;
            }

            // Skip if the image already exists in pdf-images/
            if (isset($existing_images[$image_hash])) {
                error_log("Skipping duplicate image: $image_hash");
                continue;
            }

            $image_extension = ($object->get('Filter') === 'DCTDecode') ? 'jpg' : 'png';
            $image_path = $images_dir . 'image_' . time() . "_$key.$image_extension";

            file_put_contents($image_path, $image_data);
            $saved_images[] = plugin_dir_url(__FILE__) . '../pdf-images/' . basename($image_path);
        }
    }

    return $saved_images;
}

/**
 * Generate a Word document for the job card.
 */
function generate_word_jc()
{
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'generate_word_jc')) {
        wp_send_json_error(['message' => 'Invalid request']);
    }

    // ✅ Fix: Read POST variables without JSON decode
    $client_info = $_POST['client_info'] ?? [];
    $quote_info = $_POST['quote_info'] ?? [];
    $items = $_POST['items'] ?? [];
    $images = $_POST['images'] ?? [];
    $extra_instructions = $_POST['extra_instructions'] ?? [];

    error_log(print_r($_POST, true)); // ✅ Debugging added

    $word = new PhpWord();
    $section = $word->addSection();
    $section->addText('Job Card', ['bold' => true, 'size' => 20, 'alignment' => 'center']);
    $section->addTextBreak(2);

    // ✅ Fix: Properly adding data
    $section->addText('Client Information', ['bold' => true, 'size' => 14]);
    foreach (['name', 'address', 'email', 'phone'] as $field) {
        $section->addText(ucfirst($field) . ': ' . ($client_info[$field] ?? 'N/A'));
    }
    $section->addTextBreak(1);

    $section->addText('Quote Information', ['bold' => true, 'size' => 14]);
    foreach (['quote_number', 'quote_date', 'expiry_date'] as $field) {
        $section->addText(ucfirst(str_replace('_', ' ', $field)) . ': ' . ($quote_info[$field] ?? 'N/A'));
    }
    $section->addTextBreak(1);

    // ✅ Fix: Line Items with Extra Instructions
    $section->addText('Line Items', ['bold' => true, 'size' => 14]);
    foreach ($items as $index => $item) {
        $section->addText("Item: " . ($item['item_name'] ?? 'N/A'));
        $section->addText("Extra Instructions: " . ($extra_instructions["extra_instructions[$index]"] ?? 'N/A'));
        $section->addTextBreak(1);
    }

    // ✅ Fix: Images now added properly
    if (!empty($images)) {
        $section->addText('Site Images', ['bold' => true, 'size' => 14]);
        foreach ($images as $image_url) {
            $image_path = str_replace(plugin_dir_url(__FILE__) . '../pdf-images/', PR_QUOTES_PLUGIN_DIR . 'pdf-images/', $image_url);
            if (file_exists($image_path)) {
                $section->addImage($image_path, ['width' => 300, 'height' => 200]);
                $section->addTextBreak(1);
            }
        }
    } else {
        $section->addText("No images available.");
    }

    // ✅ Fix: Filename now includes Quote Number & Client Name
    $quote_number = $quote_info['quote_number'] ?? 'N/A';
    $client_name = preg_replace('/[^a-zA-Z0-9]/', '_', $client_info['name'] ?? 'N/A');
    $filename = "jc-{$quote_number}-{$client_name}.docx";

    $upload_dir = wp_upload_dir();
    $file_path = $upload_dir['path'] . '/' . $filename;
    $file_url = $upload_dir['url'] . '/' . $filename;

    $objWriter = IOFactory::createWriter($word, 'Word2007');
    $objWriter->save($file_path);

    wp_send_json_success(['download_url' => $file_url]);
}
add_action('wp_ajax_generate_word_jc', 'generate_word_jc');






/**
 * Move an image to hash-images/ on delete.
 *
 * @param string $image_url The image URL to delete.
 * @return bool True if deleted, false otherwise.
 */
function move_image_to_hash_folder($image_url)
{
    $image_path = str_replace(plugin_dir_url(__FILE__) . '../pdf-images/', PR_QUOTES_PLUGIN_DIR . 'pdf-images/', $image_url);
    $hash_images_dir = PR_QUOTES_PLUGIN_DIR . 'pdf-images/hash-images/';

    // Ensure hash-images/ folder exists
    if (!file_exists($hash_images_dir)) {
        mkdir($hash_images_dir, 0755, true);
    }

    if (file_exists($image_path)) {
        $new_path = $hash_images_dir . basename($image_path);
        return rename($image_path, $new_path);
    }
    return false;
}

/**
 * Handle AJAX request for deleting images.
 */
function pr_delete_image_ajax()
{
    if (!isset($_POST['image_url']) || !wp_verify_nonce($_POST['nonce'], 'delete_image')) {
        wp_send_json_error(['message' => 'Invalid request']);
    }

    $image_url = sanitize_text_field($_POST['image_url']);
    if (move_image_to_hash_folder($image_url)) {
        wp_send_json_success(['message' => 'Image deleted successfully']);
    } else {
        wp_send_json_error(['message' => 'Image deletion failed']);
    }
}
add_action('wp_ajax_pr_delete_image', 'pr_delete_image_ajax');

/**
 * Parse client information from raw text.
 */
function parse_client_info(string $raw_text): array
{
    $client_name = 'N/A';

    // Case 1: If both FOR and TO exist, extract from TO to EMAIL
    if (preg_match('/TO\s*(.*?)\s*EMAIL/s', $raw_text, $to_match)) {
        $client_name = trim($to_match[1]);
    } 
    // Case 2: If only FOR exists, extract from FOR to EMAIL
    elseif (preg_match('/FOR\s*(.*?)\s*EMAIL/s', $raw_text, $for_match)) {
        $client_name = trim($for_match[1]);
    } 
    // Case 3: If only TO exists, extract from TO to EMAIL
    elseif (preg_match('/TO\s*(.*?)\s*EMAIL/s', $raw_text, $to_only_match)) {
        $client_name = trim($to_only_match[1]);
    }

    // Extract other fields
    preg_match('/EMAIL\s*([^\n]+)/', $raw_text, $email_match);
    preg_match('/ADDRESS\s*([\s\S]*?)\s*PHONE/', $raw_text, $address_match);
    preg_match('/PHONE\s*([\d\s\(\)-]+)/', $raw_text, $phone_match);

    return [
        'name' => $client_name,
        'address' => isset($address_match[1]) ? trim($address_match[1]) : 'N/A',
        'email' => isset($email_match[1]) ? trim($email_match[1]) : 'N/A',
        'phone' => isset($phone_match[1]) ? trim($phone_match[1]) : 'N/A',
    ];
}






/**
 * Parse quote details (number, date, expiry date).
 */
function parse_quote_info(string $raw_text): array
{
    preg_match('/QUOTE NUMBER\s*(\d+)/', $raw_text, $quote_number_match);
    preg_match('/DATE\s*(\d{1,2} \w+ \d{4})/', $raw_text, $quote_date_match);
    preg_match('/EXPIRY DATE\s*(\d{1,2} \w+ \d{4})/', $raw_text, $expiry_date_match);

    return [
        'quote_number' => trim($quote_number_match[1] ?? 'N/A'),
        'quote_date' => trim($quote_date_match[1] ?? 'N/A'),
        'expiry_date' => trim($expiry_date_match[1] ?? 'N/A'),
    ];
}

/**
 * Parse line items from raw text.
 */
function parse_line_items(string $raw_text): array
{
    preg_match('/Hope to hear from you soon\.(.*?)EXCLUSIONS/s', $raw_text, $line_item_section);

    if (empty($line_item_section[1])) {
        return [];
    }

    preg_match_all('/(.*?)(\d{1,3}[,\.]?\d{1,3}[,\.]?\d{1,3}\.\d{2})/s', $line_item_section[1], $line_items);

    $items = [];
    foreach ($line_items[1] as $index => $item) {
        if (!empty($line_items[2][$index])) {
            $items[] = [
                'item_name' => trim($item),
                'total_price' => trim($line_items[2][$index]),
            ];
        }
    }
    return $items;
}

/**
 * Parse total price and acceptance info.
 */
function parse_additional_info(string $raw_text): array
{
    preg_match('/Total\s*R([\d,]+\.\d{2})/', $raw_text, $total_match);
    preg_match('/Accepted on(.*?)ACCEPTED/s', $raw_text, $acceptance_section);

    $acceptance_info = ['accepted_by' => 'N/A', 'acceptance_date' => 'N/A'];

    if (isset($acceptance_section[1])) {
        preg_match('/by\s*(.*?)(\d{1,2} \w+ \d{4} at \d{1,2}:\d{2} \w{2})/', $acceptance_section[1], $acceptance_match);
        $acceptance_info = [
            'accepted_by' => trim($acceptance_match[1] ?? 'N/A'),
            'acceptance_date' => trim($acceptance_match[2] ?? 'N/A'),
        ];
    }

    return [
        'total_quote' => $total_match[1] ?? 'N/A',
        'acceptance_info' => $acceptance_info,
    ];
}

/**
 * Main function to extract all quote data, including images.
 */
function extract_pdf_data(string $pdf_file_path): array
{
    $raw_text = extract_pdf_raw_text($pdf_file_path);
    $images = extract_pdf_images($pdf_file_path);

    if (empty($raw_text) && empty($images)) {
        error_log('No text or images extracted.');
        return [];
    }

    return [
        'client_info' => parse_client_info($raw_text),
        'quote_info' => parse_quote_info($raw_text),
        'items' => parse_line_items($raw_text),
        'additional_info' => parse_additional_info($raw_text),
        'images' => $images, // Ensure images are included
        'pdf_path' => $pdf_file_path,
    ];
}



/**
 * Display extracted quote data in the admin page.
 */
function display_quote_data($quote_data)
{
    if (empty($quote_data)) {
        echo '<p>No data available to display.</p>';
        return;
    }

    // Display client information
    echo '<h3>Client Information</h3>';
    echo '<p><strong>Name:</strong> ' . esc_html($quote_data['client_info']['name'] ?? 'N/A') . '</p>';
    echo '<p><strong>Address:</strong> ' . esc_html($quote_data['client_info']['address'] ?? 'N/A') . '</p>';
    echo '<p><strong>Email:</strong> ' . esc_html($quote_data['client_info']['email'] ?? 'N/A') . '</p>';
    echo '<p><strong>Phone:</strong> ' . esc_html($quote_data['client_info']['phone'] ?? 'N/A') . '</p>';

    // Display quote information
    echo '<h3>Quote Information</h3>';
    echo '<p><strong>Quote Number:</strong> ' . esc_html($quote_data['quote_info']['quote_number'] ?? 'N/A') . '</p>';
    echo '<p><strong>Quote Date:</strong> ' . esc_html($quote_data['quote_info']['quote_date'] ?? 'N/A') . '</p>';
    echo '<p><strong>Expiry Date:</strong> ' . esc_html($quote_data['quote_info']['expiry_date'] ?? 'N/A') . '</p>';

    // Display line items
    echo '<h3>Line Items</h3>';
    foreach ($quote_data['items'] as $item) {
        echo '<p><strong>Item:</strong> ' . esc_html($item['item_name'] ?? 'N/A') . '</p>';
        echo '<p><strong>Price:</strong> ' . esc_html($item['total_price'] ?? 'N/A') . '</p>';
    }

    // Display total price and acceptance info
    echo '<h3>Total Quote</h3>';
    echo '<p>' . esc_html($quote_data['additional_info']['total_quote'] ?? 'N/A') . '</p>';

    echo '<h3>Acceptance Info</h3>';
    echo '<p><strong>Accepted By:</strong> ' . esc_html($quote_data['additional_info']['acceptance_info']['accepted_by'] ?? 'N/A') . '</p>';
    echo '<p><strong>Accepted Date:</strong> ' . esc_html($quote_data['additional_info']['acceptance_info']['acceptance_date'] ?? 'N/A') . '</p>';
}
