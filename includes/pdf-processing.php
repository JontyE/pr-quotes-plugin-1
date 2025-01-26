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
    if (empty($pdf_file_path) || !is_string($pdf_file_path)) {
        error_log('Invalid PDF file path provided.');
        return '';
    }

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
     if (empty($pdf_file_path) || !is_string($pdf_file_path)) {
         error_log('❌ Invalid PDF file path provided.');
         return [];
     }
 
     // ✅ Get Plugin Root Directory (Ensure it is at plugin root, not "includes/")
     $plugin_root_dir = dirname(__DIR__, 1); // Moves up to plugin base folder
     $images_dir = $plugin_root_dir . '/pdf-images/';
     $hash_images_dir = $images_dir . 'hash-images/';
     
     // ✅ Correct URL for Image Access
     $plugin_url = plugin_dir_url(__FILE__) . '../pdf-images/'; // Matches plugin root URL
 
     // ✅ Ensure Directories Exist
     if (!file_exists($images_dir)) {
         mkdir($images_dir, 0755, true);
     }
     if (!file_exists($hash_images_dir)) {
         mkdir($hash_images_dir, 0755, true);
     }
 
     // ✅ Initialize PDF Parser
     $parser = new Parser();
     $pdf = $parser->parseFile($pdf_file_path);
     $objects = $pdf->getObjectsByType('XObject');
 
     // ✅ Load Existing Image Hashes
     $existing_images = [];
     foreach (glob($images_dir . "*.{jpg,png}", GLOB_BRACE) as $file) {
         $existing_images[sha1_file($file)] = $file;
     }
 
     // ✅ Load Excluded Image Hashes
     $excluded_hashes = [];
     foreach (glob($hash_images_dir . "*.{jpg,png}", GLOB_BRACE) as $file) {
         $excluded_hashes[sha1_file($file)] = true;
     }
 
     $saved_images = [];
 
     foreach ($objects as $key => $object) {
         if ($object->get('Subtype') == 'Image') {
             $image_data = $object->getContent();
             $image_hash = sha1($image_data);
 
             // ✅ Skip Excluded Images
             if (isset($excluded_hashes[$image_hash])) {
                 error_log("🚫 Excluded Image: $image_hash");
                 continue;
             }
 
             // ✅ Skip Duplicates
             if (isset($existing_images[$image_hash])) {
                 error_log("⚠️ Skipping Duplicate Image: $image_hash");
                 continue;
             }
 
             // ✅ Determine Image Type
             $image_extension = ($object->get('Filter') === 'DCTDecode') ? 'jpg' : 'png';
             $image_filename = 'image_' . time() . "_$key.$image_extension";
             $image_path = $images_dir . $image_filename;
 
             // ✅ Save Image
             if (file_put_contents($image_path, $image_data)) {
                 error_log("✅ Image Saved: $image_path");
                 $image_url = $plugin_url . $image_filename;
                 $saved_images[] = $image_url;
             } else {
                 error_log("❌ Failed to Save Image: $image_path");
             }
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
        exit;
    }

    $client_info = $_POST['client_info'] ?? [];
    $quote_info = $_POST['quote_info'] ?? [];
    $items = $_POST['items'] ?? [];
    $images = $_POST['images'] ?? [];
    $extra_instructions = $_POST['extra_instructions'] ?? [];

    if (empty($client_info) || empty($quote_info) || empty($items)) {
        error_log("❌ Missing required data.");
        wp_send_json_error(['message' => 'Missing required data.']);
        exit;
    }

    $plugin_root_dir = plugin_dir_path(__DIR__);
    $word_job_cards_dir = $plugin_root_dir . 'word-job-cards/';

    if (!file_exists($word_job_cards_dir)) {
        mkdir($word_job_cards_dir, 0755, true);
    }

    $quote_number = !empty($quote_info['quote_number']) ? preg_replace('/[^a-zA-Z0-9]/', '_', $quote_info['quote_number']) : 'default_quote';
    $client_name = !empty($client_info['name']) ? preg_replace('/[^a-zA-Z0-9]/', '_', $client_info['name']) : 'default_client';
    $filename = "jc-{$quote_number}-{$client_name}.docx";
    $file_path = $word_job_cards_dir . $filename;

    $phpWord = new \PhpOffice\PhpWord\PhpWord();
    $section = $phpWord->addSection();

// ✅ Add Header
$header = $section->addHeader();

$client_name_display_h = $client_info['name'] ?? 'N/A';
$quote_number_display_h = $quote_info['quote_number'] ?? 'N/A';

// First Line: Company & Date
$header->addText("Prompt Roofing Job Card | " . date('d M Y'), [
    'size' => 8,
    'color' => '888888',
    'italic' => true
], ['alignment' => 'center']);

// Second Line: Client Name & Quote Number
$header->addText("Client: {$client_name_display_h} | Quote #: {$quote_number_display_h}", [
    'size' => 8,
    'color' => '888888',
    'italic' => true
], ['alignment' => 'center']);

    // ✅ Add Footer with Page Numbers
    $footer = $section->addFooter();
    $footer->addPreserveText("Page {PAGE} of {NUMPAGES}", [
        'size' => 8,
        'color' => '888888'
    ], ['alignment' => 'center']);

    // ✅ Main Content
    $section->addText('Job Card', ['bold' => true, 'size' => 18, 'alignment' => 'center']);

    // ✅ Client Information
    $section->addText('Client Information', ['bold' => true, 'size' => 12]);
    foreach (['name', 'address', 'email', 'phone'] as $field) {
        $section->addText(ucfirst($field) . ': ' . ($client_info[$field] ?? 'N/A'));
    }
    $section->addTextBreak(1);

    // ✅ Quote Information
    $section->addText('Quote Information', ['bold' => true, 'size' => 12]);
    foreach (['quote_number', 'quote_date', 'expiry_date'] as $field) {
        $section->addText(ucfirst(str_replace('_', ' ', $field)) . ': ' . ($quote_info[$field] ?? 'N/A'));
    }
    $section->addTextBreak(1);

    // ✅ Line Items with Extra Instructions
    $section->addText('Line Items', ['bold' => true, 'size' => 12]);
    foreach ($items as $index => $item) {
        $extra_text = $extra_instructions[$index] ?? '';
        $section->addText("Item: " . ($item['item_name'] ?? 'N/A'));
        $section->addText("Extra Instructions: " . $extra_text);
        $section->addTextBreak(1);
        $section->addLine(['weight' => 0.3, 'width' => 450, 'height' => 0, 'color' => 'D3D3D3']);
    }



    // ✅ Add Images (if available)
    if (!empty($images)) {
        $section->addText('Site Images', ['bold' => true, 'size' => 12]);
        $table = $section->addTable();
        $image_count = 0;
        $max_images_per_row = 3;

        foreach ($images as $image_url) {
            $image_path = str_replace(plugin_dir_url(__FILE__) . '../pdf-images/', PR_QUOTES_PLUGIN_DIR . 'pdf-images/', $image_url);
            if (file_exists($image_path)) {
                if ($image_count % $max_images_per_row === 0) {
                    $table->addRow();
                }
                $cell = $table->addCell(1800);
                $cell->addImage($image_path, ['width' => 150, 'height' => 110]);
                $image_count++;
            }
        }
    } else {
        $section->addText("No images available.");
    }
        // ✅ ADD PAGE BREAK BEFORE CLIENT SIGN-OFF

$section->addText('Client Sign-off:', ['bold' => true, 'size' => 12, 'underline' => 'single']);

// ✅ ADD CLIENT NAME & QUOTE NUMBER NEXT TO SIGN-OFF
$client_name_display = $client_info['name'] ?? 'N/A';
$quote_number_display = $quote_info['quote_number'] ?? 'N/A';

$section->addText("Client: {$client_name_display}    |    Quote #: {$quote_number_display}", ['size' => 11, 'bold' => true]);
$section->addTextBreak(1);

// ✅ CLIENT SIGNATURE SECTION
$section->addText("Client Name: __________________________");
$section->addTextBreak(1);
$section->addText("Client Signature: _______________________                 Date: ________________________");

$section->addTextBreak(2);
$section->addText("Prompt Roofing:", ['bold' => true, 'size' => 12, 'underline' => 'single']);
$section->addTextBreak(1);
$section->addText("Name: __________________________");
$section->addTextBreak(1);
$section->addText("Prompt Roofing Signature: _______________________        Date: ________________________");
$section->addTextBreak(2);

    // ✅ Save Word Document with Error Handling
    try {
        $writer = \PhpOffice\PhpWord\IOFactory::createWriter($phpWord, 'Word2007');
        $writer->save($file_path);
    } catch (Exception $e) {
        error_log("❌ Word document generation failed: " . $e->getMessage());
        wp_send_json_error(['message' => 'Failed to generate Word document.']);
        exit;
    }

    if (!file_exists($file_path)) {
        error_log("❌ Failed to create Word document at: " . $file_path);
        wp_send_json_error(['message' => 'Failed to create Word document.']);
        exit;
    }

    $file_url = plugins_url('word-job-cards/' . $filename, __DIR__);
    delete_pdf_images();

    error_log("📥 Word Job Card Download URL: " . $file_url);
    wp_send_json_success(['download_url' => esc_url($file_url)]);
    exit;
}

// ✅ Register AJAX Actions
add_action('wp_ajax_generate_word_jc', 'generate_word_jc');
add_action('wp_ajax_nopriv_generate_word_jc', 'generate_word_jc'); // ✅ Allow frontend users to access




/**
 * Delete all images inside 'pdf-images/' but leave 'hash-images/' untouched.
 */
function delete_pdf_images()
{
    // ✅ Get Correct Path to `pdf-images` Folder
    $images_dir = plugin_dir_path(__DIR__) . 'pdf-images/';

    // ✅ Check if Directory Exists
    if (!is_dir($images_dir)) {
        error_log("🛑 Images directory does not exist: " . $images_dir);
        return;
    }

    // ✅ Scan Directory for Image Files
    $files = scandir($images_dir);
    if ($files === false) {
        error_log("🛑 Failed to read directory: " . $images_dir);
        return;
    }

    // ✅ Delete Each Image
    foreach ($files as $file) {
        if ($file !== "." && $file !== "..") {
            $file_path = $images_dir . $file;
            if (is_file($file_path)) {
                error_log("🗑 Deleting image: " . basename($file_path));
                unlink($file_path);
            } else {
                error_log("⚠️ Skipping non-file: " . basename($file_path));
            }
        }
    }

    error_log("✅ All images deleted successfully from: " . $images_dir);
}






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
 * Parse line items from raw text, ensuring unwanted text is removed.
 */
function parse_line_items(string $raw_text): array
{
    error_log('Parsing Line Items: ' . $raw_text); // Log the raw text being parsed

    $line_item_text = '';

    // **Step 1: Check if "Hope to hear from you soon." exists**
    if (strpos($raw_text, 'Hope to hear from you soon.') !== false) {
        // **Extract text between "Hope to hear from you soon." and "Total"**
        if (preg_match('/Hope to hear from you soon\.(.*?)Total/s', $raw_text, $matches)) {
            $line_item_text = trim($matches[1] ?? '');
        }
    } else {
        // **Fallback: Extract text between "EXPIRY DATE" and "Total"**
        if (preg_match('/EXPIRY DATE[\s\S]*?Total/', $raw_text, $matches)) {
            $line_item_text = trim($matches[0] ?? '');
            // Remove everything between "EXPIRY DATE" and "Your Roofing expert" plus 13 characters
            $line_item_text = preg_replace('/EXPIRY DATE[\s\S]*?Your Roofing expert.{13}/', '', $line_item_text);
        }
    }

    // **Step 2: Ensure "Total" is removed**
    $line_item_text = str_replace('Total', '', $line_item_text);

    error_log('Cleaned Line Item Section: ' . $line_item_text);

    // **Step 3: Extract line items with prices**
    preg_match_all('/(.*?)(\d{1,3}[,\.]?\d{1,3}[,\.]?\d{1,3}\.\d{2})/s', $line_item_text, $matches);

    $items = [];
    foreach ($matches[1] as $index => $item) {
        if (!empty($matches[2][$index])) {
            $items[] = [
                'item_name' => trim($item),
                'total_price' => trim($matches[2][$index]),
            ];
        }
    }

    // **Ensure at least an empty placeholder item is returned**
    if (empty($items)) {
        error_log('No valid line items extracted. Returning a placeholder.');
        return [
            [
                'item_name' => 'No line items found',
                'total_price' => '0.00',
            ],
        ];
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