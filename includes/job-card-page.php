<?php
// Ensure this is being accessed from WordPress
if (!defined('ABSPATH')) exit;

// Get uploaded data
$quote_data = isset($quote_data) ? $quote_data : [];
$upload_url = '';
$word_doc_url = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['pdf_file'])) {
    $upload_dir = wp_upload_dir();
    $pdf_filename = basename($_FILES['pdf_file']['name']);
    $pdf_path = trailingslashit($upload_dir['path']) . $pdf_filename;

    if (move_uploaded_file($_FILES['pdf_file']['tmp_name'], $pdf_path)) {
        require_once plugin_dir_path(__FILE__) . 'pdf-processing.php';
        $quote_data = extract_pdf_data($pdf_path);
        $upload_url = trailingslashit($upload_dir['url']) . $pdf_filename;
    } else {
        echo '<div class="alert alert-danger">File upload failed.</div>';
    }
}

// ✅ Define Plugin Root & Word Job Cards Directory
$plugin_root_dir = dirname(__DIR__, 1);
$word_job_cards_dir = $plugin_root_dir . '/word-job-cards/';

// ✅ Ensure Directory Exists
if (!file_exists($word_job_cards_dir)) {
    mkdir($word_job_cards_dir, 0755, true);
}

// ✅ Retrieve Latest Word Document
$word_files = glob($word_job_cards_dir . "*.docx");
if (!empty($word_files)) {
    $latest_file = end($word_files); // Get the latest generated file
    $word_doc_url = plugins_url('word-job-cards/' . basename($latest_file), dirname(__FILE__));
}
?>

<div class="wrap container-fluid">
    <h2 class="text-center">Upload PDF and Process Job Card</h2>

    <form method="post" enctype="multipart/form-data" class="mb-4">
        <input type="file" name="pdf_file" class="form-control mb-3" required>
        <div class="d-flex justify-content-center" style="width: 100%; padding: 20px 0;">
    <button type="submit" class="btn btn-primary" style="padding: 10px 20px; width: 300px;">
        Upload
    </button>
</div>
 </form>


    <?php if (!empty($quote_data)) : ?>
        


        <div class="table-responsive">
            <table class="table table-striped table-bordered w-100" style="border:0.5 solid #333;">
                <thead class="table-dark">
                    <tr style="border:0.5 solid #333;"><th colspan="2">Client Information</th></tr>
                </thead>
                <tbody>
                    <tr style="border:0.5 solid #333;"><td style="border:0.5 solid #333;"><strong>Name</strong></td><td style="border:0.5 solid #333;"><?php echo esc_html($quote_data['client_info']['name'] ?? 'N/A'); ?></td></tr>
                    <tr style="border:0.5 solid #333;"><td style="border:0.5 solid #333;"><strong>Address</strong></td><td style="border:0.5 solid #333;"><?php echo esc_html($quote_data['client_info']['address'] ?? 'N/A'); ?></td></tr>
                    <tr style="border:0.5 solid #333;"><td style="border:0.5 solid #333;"><strong>Email</strong></td><td style="border:0.5 solid #333;"><?php echo esc_html($quote_data['client_info']['email'] ?? 'N/A'); ?></td></tr>
                    <tr style="border:0.5 solid #333;"><td style="border:0.5 solid #333;"><strong>Phone</strong></td><td style="border:0.5 solid #333;"><?php echo esc_html($quote_data['client_info']['phone'] ?? 'N/A'); ?></td></tr>
                </tbody>
            </table>

            <table class="table table-striped table-bordered w-100" style="border:0.5 solid #333;">
                <thead class="table-dark">
                    <tr style="border:0.5 solid #333;"><th colspan="2">Quote Information</th></tr>
                </thead>
                <tbody>
                    <tr style="border:0.5 solid #333;"><td style="border:0.5 solid #333;"><strong>Quote Number</strong></td><td style="border:0.5 solid #333;"><?php echo esc_html($quote_data['quote_info']['quote_number'] ?? 'N/A'); ?></td></tr>
                    <tr style="border:0.5 solid #333;"><td style="border:0.5 solid #333;"><strong>Quote Date</strong></td><td style="border:0.5 solid #333;"><?php echo esc_html($quote_data['quote_info']['quote_date'] ?? 'N/A'); ?></td></tr>
                    <tr style="border:0.5 solid #333;"><td style="border:0.5 solid #333;"><strong>Expiry Date</strong></td><td style="border:0.5 solid #333;"><?php echo esc_html($quote_data['quote_info']['expiry_date'] ?? 'N/A'); ?></td></tr>
                </tbody>
            </table>

            <table class="table table-striped table-bordered w-100" style="border:0.5 solid #333;">
                <thead class="table-dark">
                    <tr style="border:0.5 solid #333;"><th>Line Items</th><th style="width: 300px;">Extra Instructions</th></tr>
                </thead>
                <tbody>
                    <?php foreach ($quote_data['items'] as $index => $item): ?>
                        <tr style="border:0.5 solid #333;">
                            <td style="border:0.5 solid #333;"><?php echo esc_html($item['item_name'] ?? 'N/A'); ?></td>
                            <td style="border:0.5 solid #333;">
                                <textarea name="extra_instructions[<?php echo $index; ?>]" class="form-control" placeholder="Enter extra instructions..."></textarea>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        
        <button id="download-job-card" class="btn btn-primary" style="margin-top: 10px; margin-bottom: 20px; padding: 10px; width: 300px;">
    Download Job Card
    </button>
    <?php endif; ?> 

        <?php if (!empty($quote_data['images'])) : ?>
            <div class="table-responsive">
                <h3>Site Images</h3>
                <div class="d-flex flex-wrap" style="display: flex; flex-wrap: wrap; gap: 8px;">
                    <?php foreach ($quote_data['images'] as $image_url) : ?>
                        <img src="<?php echo esc_url($image_url); ?>" 
                             onerror="this.onerror=null; this.src='https://via.placeholder.com/200?text=Missing+Image';"
                             alt="Extracted Image" 
                             style="max-width: 200px; height: auto; margin-right: 8px;">
                    <?php endforeach; ?>
                </div>
            </div>
        <?php else : ?>
            <div style="margin-bottom: 100px;"><p>No images found in job card.</p></div>
        <?php endif; ?>



    <script>
var PR_QUOTES = {
    ajaxurl: "<?php echo admin_url('admin-ajax.php'); ?>",
    nonce: "<?php echo wp_create_nonce('generate_word_jc'); ?>",
    client_info: <?php echo json_encode($quote_data['client_info'] ?? []); ?>,
    quote_info: <?php echo json_encode($quote_data['quote_info'] ?? []); ?>,
    items: <?php echo json_encode($quote_data['items'] ?? []); ?>,
    images: <?php echo json_encode($quote_data['images'] ?? []); ?>,
    extra_instructions: []
};
</script>


   <!-- ✅ Popup for Feedback -->
<div id="download-popup" style="display:none; position:fixed; top:50%; left:50%; transform:translate(-50%, -50%);
    background:white; padding:20px; border-radius:10px; box-shadow:0 0 10px rgba(0,0,0,0.3); width:300px; text-align:center;">
    <h4>Processing Job Card...</h4>
    <div style="height:10px; background:#ddd; border-radius:5px; overflow:hidden;">
        <div id="progress-bar" style="width:0%; height:100%; background:#28a745;"></div>
    </div>
</div>

</div>
