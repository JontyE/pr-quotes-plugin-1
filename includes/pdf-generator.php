<?php
/**
 * Generate a PDF for a Quote using TCPDF with Error Logging
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

require_once(plugin_dir_path(__FILE__) . 'tcpdf/tcpdf.php');

/**
 * Handle PDF Generation Request
 */
add_action('admin_post_generate_quote_pdf', 'generate_quote_pdf');
add_action('admin_post_nopriv_generate_quote_pdf', 'generate_quote_pdf');

function generate_quote_pdf() {
    if (!isset($_GET['quote_number'])) {
        error_log('Error: Missing quote number in request.');
        wp_die('Invalid request. Quote number is missing.');
    }

    global $wpdb;
    $quote_number = intval($_GET['quote_number']);
    error_log("Generating PDF for Quote Number: $quote_number");

    // Fetch quote details
    $quote = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}pr_quotes_quotes WHERE quote_number = %d",
        $quote_number
    ), ARRAY_A);

    if (!$quote) {
        error_log("Error: Quote not found for number: $quote_number");
        wp_die('Quote not found.');
    }

    // Fetch line items
    $line_items = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}pr_quotes_line_items WHERE quote_number = %d",
        $quote_number
    ), ARRAY_A);

    if (!$line_items) {
        error_log("Warning: No line items found for quote number: $quote_number");
    }

    // Create a new PDF document
    $pdf = new TCPDF();
    $pdf->SetCreator(PDF_CREATOR);
    $pdf->SetAuthor('PR Quotes');
    $pdf->SetTitle('Quote ' . $quote['quote_number']);
    $pdf->SetSubject('Quote Document');
    $pdf->SetMargins(15, 15, 15);
    $pdf->AddPage();

    // Title
    $pdf->SetFont('helvetica', 'B', 16);
    $pdf->Cell(0, 10, 'Quote Details', 0, 1, 'C');
    $pdf->Ln(5);

    // Quote Details
    $pdf->SetFont('helvetica', '', 12);
    $pdf->Cell(0, 10, "Quote Number: " . $quote['quote_number'], 0, 1);
    $pdf->Cell(0, 10, "Client: " . $quote['for_name'], 0, 1);
    $pdf->Cell(0, 10, "Email: " . $quote['email'], 0, 1);
    $pdf->Cell(0, 10, "Total Value: " . $quote['total_value'] . " " . $quote['currency'], 0, 1);
    $pdf->Cell(0, 10, "Status: " . $quote['quote_status'], 0, 1);
    $pdf->Cell(0, 10, "Expiry Date: " . $quote['expiry_date'], 0, 1);
    $pdf->Ln(5);

    // Line Items Table
    $pdf->SetFont('helvetica', 'B', 12);
    $pdf->Cell(0, 10, 'Line Items', 0, 1, 'C');
    $pdf->Ln(3);

    $pdf->SetFont('helvetica', '', 10);
    $pdf->SetFillColor(230, 230, 230);
    $pdf->Cell(60, 10, 'Item Title', 1, 0, 'C', true);
    $pdf->Cell(25, 10, 'Unit Price', 1, 0, 'C', true);
    $pdf->Cell(20, 10, 'Quantity', 1, 0, 'C', true);
    $pdf->Cell(25, 10, 'Discount', 1, 0, 'C', true);
    $pdf->Cell(30, 10, 'Total', 1, 1, 'C', true);

    foreach ($line_items as $item) {
        $pdf->Cell(60, 10, $item['item_title'], 1);
        $pdf->Cell(25, 10, $item['unit_price'], 1);
        $pdf->Cell(20, 10, $item['quantity'], 1);
        $pdf->Cell(25, 10, $item['discount'], 1);
        $pdf->Cell(30, 10, $item['item_total'], 1, 1);
    }

    $pdf->Ln(5);
    $pdf->SetFont('helvetica', 'B', 12);
    $pdf->Cell(0, 10, "Grand Total: " . $quote['total_value'] . " " . $quote['currency'], 0, 1, 'R');

    // Define the output path
    $upload_dir = wp_upload_dir();
    $pdf_path = $upload_dir['path'] . '/Quote_' . $quote['quote_number'] . '.pdf';
    $pdf_url = $upload_dir['url'] . '/Quote_' . $quote['quote_number'] . '.pdf';
    
    // Save the PDF
    $pdf->Output($pdf_path, 'F');
    error_log("PDF saved successfully at: $pdf_path");

    // Return JSON response for AJAX
    wp_send_json_success([ 'message' => 'PDF generated successfully', 'pdf_url' => $pdf_url ]);
}
?>
