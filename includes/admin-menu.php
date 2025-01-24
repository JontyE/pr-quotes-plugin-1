<?php


function pr_quotes_enqueue_admin_assets() {
    wp_enqueue_style('bootstrap-css', 'https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css');
    wp_enqueue_script('jquery'); // ✅ Ensure jQuery is available
    wp_enqueue_script('bootstrap-js', 'https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js', array('jquery'), null, true);

    // ✅ Fix: Use `plugin_dir_path()` and check the correct path
    wp_enqueue_script('pr-quotes-admin-js', plugin_dir_url(__FILE__) . 'includes/admin.js', array('jquery'), null, true);
}
add_action('admin_enqueue_scripts', 'pr_quotes_enqueue_admin_assets');


// Add admin menu item
function pr_quotes_add_admin_menu() {
    add_menu_page(
        'PR Quotes', 
        'PR Quotes', 
        'manage_options', 
        'pr-quotes', 
        'pr_quotes_render_admin_page', 
        'dashicons-media-document', 
        25
    );
}
add_action('admin_menu', 'pr_quotes_add_admin_menu');

// Function to display uploaded PDF in an iframe
function pr_display_uploaded_file($upload_url) {
    if (!empty($upload_url)) {
        echo '<iframe src="' . esc_url($upload_url) . '" width="100%" height="1000px" style="border: none; padding-top: 10px;"></iframe>';
    }
}


// Render the admin page
function pr_quotes_render_admin_page() {
    $quote_data = [];
    $upload_url = ''; // Initialize variable

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['pdf_file'])) {
        $upload_dir = wp_upload_dir();
        $pdf_path = $upload_dir['path'] . '/' . basename($_FILES['pdf_file']['name']);

        if (move_uploaded_file($_FILES['pdf_file']['tmp_name'], $pdf_path)) {
            require_once plugin_dir_path(__FILE__) . 'pdf-processing.php';
            $quote_data = extract_pdf_data($pdf_path);
            $upload_url = $upload_dir['url'] . '/' . basename($_FILES['pdf_file']['name']);

            if (empty($quote_data)) {
                echo '<div class="alert alert-warning">No data could be extracted from the PDF.</div>';
            }
        } else {
            echo '<div class="alert alert-danger">File upload failed.</div>';
        }
    }
?>

<div class="wrap container-fluid">
    <h1 class="text-center">PR Quotes Admin Panel</h1>

    <ul class="nav nav-tabs">
        <li class="nav-item">
            <a class="nav-link" data-bs-toggle="tab" href="#upload">Job Card</a>
        </li>
        <li class="nav-item">
            <a class="nav-link" data-bs-toggle="tab" href="#quotes">Quotes</a>
        </li>
        <li class="nav-item">
            <a class="nav-link" data-bs-toggle="tab" href="#upload-csv">Upload CSV</a>
        </li>
        <li class="nav-item">
            <a class="nav-link" data-bs-toggle="tab" href="#settings">Settings</a>
        </li>
    </ul>
   
    <div class="tab-content mt-3">
    <div id="quotes" class="tab-pane fade">
        <h4>Search - Quote Number</h4>

        <div class="mb-3">
            <label for="quote-search" class="form-label">Search Quote</label>
            <input type="text" id="quote-search" class="form-control" placeholder="Enter Quote Number or Client Name">
            <button id="search-quote" class="btn btn-primary mt-2">Search</button>
        </div>

        <div id="quote-results" class="mt-3"></div>
    </div>


<script>
jQuery(document).ready(function($) {
    $("#search-quote").on("click", function() {
        let query = $("#quote-search").val().trim();
        if (!query) {
            $("#quote-results").html('<div class="alert alert-warning">Please enter a quote number or client name.</div>');
            return;
        }

        $.ajax({
            url: ajaxurl,
            type: "POST",
            data: {
                action: "pr_quotes_search",
                query: query
            },
            success: function(response) {
                if (response.success) {
                    $("#quote-results").html(response.data.html);
                } else {
                    $("#quote-results").html('<div class="alert alert-danger">No quotes found.</div>');
                }
            },
            error: function() {
                $("#quote-results").html('<div class="alert alert-danger">Error retrieving quote data.</div>');
            }
        });
    });
});
</script>


<!-- Upload Tab Job Card -->
<div class="tab-content mt-3">
    <div id="upload" class="tab-pane fade show active">
        <h4>Upload PDF and Process Data</h4>
        <form method="post" enctype="multipart/form-data" class="mb-4">
            <input type="file" name="pdf_file" class="form-control mb-3" required>
            <button type="submit" class="btn btn-primary">Upload</button>
        </form>

        <!-- Process and return to Job Card to screen -->
        <?php if (!empty($quote_data)) : ?>
            <div class="table-responsive">
                <table class="table table-striped table-bordered w-100">
                    <thead class="table-dark">
                        <tr><th colspan="2">Client Information</th></tr>
                    </thead>
                    <tbody>
                        <tr><td><strong>Name</strong></td><td><?php echo esc_html($quote_data['client_info']['name'] ?? 'N/A'); ?></td></tr>
                        <tr><td><strong>Address</strong></td><td><?php echo esc_html($quote_data['client_info']['address'] ?? 'N/A'); ?></td></tr>
                        <tr><td><strong>Email</strong></td><td><?php echo esc_html($quote_data['client_info']['email'] ?? 'N/A'); ?></td></tr>
                        <tr><td><strong>Phone</strong></td><td><?php echo esc_html($quote_data['client_info']['phone'] ?? 'N/A'); ?></td></tr>
                    </tbody>
                </table>

                <table class="table table-striped table-bordered w-100">
                    <thead class="table-dark">
                        <tr><th colspan="2">Quote Information</th></tr>
                    </thead>
                    <tbody>
                        <tr><td><strong>Quote Number</strong></td><td><?php echo esc_html($quote_data['quote_info']['quote_number'] ?? 'N/A'); ?></td></tr>
                        <tr><td><strong>Quote Date</strong></td><td><?php echo esc_html($quote_data['quote_info']['quote_date'] ?? 'N/A'); ?></td></tr>
                        <tr><td><strong>Expiry Date</strong></td><td><?php echo esc_html($quote_data['quote_info']['expiry_date'] ?? 'N/A'); ?></td></tr>
                    </tbody>
                </table>

                <table class="table table-striped table-bordered w-100">
                    <thead class="table-dark">
                        <tr><th>Line Items</th><th style="width: 200px;">Extra Instructions</th></tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($quote_data['items'])): ?>
                            <?php foreach ($quote_data['items'] as $index => $item): ?>
                                <tr>
                                    <td><?php echo esc_html($item['item_name'] ?? 'N/A'); ?></td>
                                    <td>
                                        <textarea name="extra_instructions[<?php echo $index; ?>]" class="form-control" placeholder="Enter extra instructions..."></textarea>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr><td colspan="2">No items found.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <?php if (!empty($quote_data['images'])) : ?>
                <div class="table-responsive">
                    <table class="table table-striped table-bordered w-100">
                        <thead class="table-dark">
                            <tr><th colspan="2">Site Images</th></tr>
                        </thead>
                        <tbody>
                            <?php
                            $count = 0;
                            foreach ($quote_data['images'] as $image_url) {
                                $image_path = str_replace(plugin_dir_url(__FILE__) . '../pdf-images/', PR_QUOTES_PLUGIN_DIR . 'pdf-images/', $image_url);
                                
                                // Skip if the image file doesn't exist
                                if (!file_exists($image_path)) {
                                    continue;
                                }

                                if ($count % 2 == 0) echo '<tr>'; // Start a new row every 2 images

                                ?>
                                <td><img src="<?php echo esc_url($image_url); ?>" alt="Extracted Image" style="max-width: 200px;"></td>
                                <?php 
                                $count++;
                                if ($count % 2 == 0) echo '</tr>'; // Close the row every 2 images
                            }
                            if ($count % 2 != 0) echo '</tr>'; // Close the last row if it's not complete
                            ?>
                        </tbody>
                    </table>
                </div>
            <?php else : ?>
                <div class="alert alert-warning">No images extracted from the PDF.</div>
            <?php endif; ?>

            <!-- Process Job Card Button -->
            <button id="process-job-card" class="btn btn-danger w-100 mt-4" style="border-radius: 10px; font-size: 18px; font-weight: bold;">
                Process Job Card
            </button>

            <div style="padding: 10px">
                <p>Check that the Job Card information matches the Quote below</p>
            </div>

            <?php pr_display_uploaded_file($upload_url); ?>

        <?php endif; ?> <!-- Closing tag for the quote_data check -->

    </div> <!-- Closing for #upload -->
    <div id="download-popup" style="display:none; position:fixed; top:50%; left:50%; transform:translate(-50%, -50%);
    background:white; padding:20px; border-radius:10px; box-shadow:0 0 10px rgba(0,0,0,0.3); width:300px; text-align:center;">
    <h4>Downloading Job Card...</h4>
    <div style="height:10px; background:#ddd; border-radius:5px; overflow:hidden;">
        <div id="progress-bar" style="width:0%; height:100%; background:#28a745;"></div>
    </div>
</div>
</div> <!-- Closing for .tab-content -->


<?php 

function pr_handle_pdf_upload()
{
    if (!isset($_FILES['pdf_file'])) {
        wp_send_json_error(['message' => 'No file uploaded.']);
    }

    // Define the new plugin directory for storing PDFs
    $pdf_dir = PR_QUOTES_PLUGIN_DIR . 'pdf-files/';

    // Ensure the folder exists
    if (!file_exists($pdf_dir)) {
        mkdir($pdf_dir, 0755, true);
    }

    // Sanitize filename to prevent security issues
    $filename = sanitize_file_name($_FILES['pdf_file']['name']);
    $pdf_path = $pdf_dir . $filename;

    // Ensure only PDF files are uploaded
    $filetype = wp_check_filetype($filename);
    if ($filetype['ext'] !== 'pdf') {
        wp_send_json_error(['message' => 'Only PDF files are allowed.']);
    }

    // Move the uploaded file to the new location
    if (!move_uploaded_file($_FILES['pdf_file']['tmp_name'], $pdf_path)) {
        wp_send_json_error(['message' => 'Failed to upload PDF file.']);
    }

    // Generate the PDF URL for the iframe
    $pdf_url = plugin_dir_url(__FILE__) . '../pdf-files/' . $filename;

    wp_send_json_success([
        'pdf_url' => $pdf_url
    ]);
}
add_action('wp_ajax_pr_handle_pdf_upload', 'pr_handle_pdf_upload');
?>
<script>
jQuery(document).ready(function ($) {
    $("#process-job-card").on("click", function () {
        let extraInstructions = {};
        $("textarea[name^='extra_instructions']").each(function () {
            let index = $(this).attr("name").match(/\d+/)[0]; // Extract index
            extraInstructions[index] = $(this).val();
        });

        // ✅ Show the popup window
        $("#download-popup").fadeIn(200);

        // ✅ Animate progress bar (fills in 2.5s)
        $("#progress-bar").css("width", "0%").animate({ width: "100%" }, 2500);

        // ✅ Hide popup after 4s
        setTimeout(() => {
            $("#download-popup").fadeOut(300);
        }, 4000);

        // ✅ AJAX request to process the job card
        $.ajax({
            url: ajaxurl,
            type: "POST",
            data: {
                action: "generate_word_jc",
                client_info: <?php echo json_encode($quote_data['client_info'] ?? []); ?>,
                quote_info: <?php echo json_encode($quote_data['quote_info'] ?? []); ?>,
                items: <?php echo json_encode($quote_data['items'] ?? []); ?>,
                images: <?php echo json_encode($quote_data['images'] ?? []); ?>,
                extra_instructions: extraInstructions,
                nonce: "<?php echo wp_create_nonce('generate_word_jc'); ?>"
            },
            success: function (response) {
                if (response.success) {
                    setTimeout(() => {
                        window.location.href = response.data.download_url;
                    }, 2500); // ✅ Delay download until progress completes
                } else {
                    alert("Error: " + response.data.message);
                }
            },
            error: function (xhr, status, error) {
                alert("AJAX request failed: " + xhr.responseText);
            }
        });
    });
});

</script>




        <div id="upload-csv" class="tab-pane fade">
    <h4>Upload CSV and Process Data</h4>
        <form id="csv-upload-form" enctype="multipart/form-data" class="mb-4">
             <label class="form-label">Upload Contacts CSV from Quotient</label>
                <input type="file" name="csv_file_1" class="form-control mb-3" accept=".csv" required>

            <label class="form-label">Upload Quotes CSV from Quotient</label>
                <input type="file" name="csv_file_2" class="form-control mb-3" accept=".csv" required>

            <label class="form-label">Upload Line Items CSV from Quotient</label>
                 <input type="file" name="csv_file_3" class="form-control mb-3" accept=".csv" required>

            <button type="button" id="upload-button" class="btn btn-primary">Upload & Process</button>
        </form>

    <div id="upload-status"></div>
    <div id="database-preview"></div>
    <!-- Upload Progress Modal -->
<div class="modal fade" id="uploadProgressModal" tabindex="-1" aria-labelledby="uploadProgressModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="uploadProgressModalLabel">Uploading CSV Files</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p>Processing files, please wait...</p>
                <div id="progress-container">
                    <div class="mb-3">
                        <label>Contacts CSV</label>
                        <div class="progress">
                            <div id="progress-bar-1" class="progress-bar progress-bar-striped progress-bar-animated" role="progressbar" style="width: 0%;"></div>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label>Quotes CSV</label>
                        <div class="progress">
                            <div id="progress-bar-2" class="progress-bar progress-bar-striped progress-bar-animated" role="progressbar" style="width: 0%;"></div>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label>Line Items CSV</label>
                        <div class="progress">
                            <div id="progress-bar-3" class="progress-bar progress-bar-striped progress-bar-animated" role="progressbar" style="width: 0%;"></div>
                        </div>
                    </div>
                </div>
                <div id="upload-status" class="mt-3"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

</div>

<script>
jQuery(document).ready(function($) {
    $("#upload-button").on("click", function() {
        let files = [
            $("input[name='csv_file_1']")[0].files[0],
            $("input[name='csv_file_2']")[0].files[0],
            $("input[name='csv_file_3']")[0].files[0]
        ];
        
        let progressBars = [
            $("#progress-bar-1"),
            $("#progress-bar-2"),
            $("#progress-bar-3")
        ];

        // Reset progress bars and open modal
        progressBars.forEach(bar => bar.css("width", "0%"));
        $("#uploadProgressModal").modal("show");
        $("#upload-status").html("");

        function uploadFile(index) {
            if (index >= files.length || !files[index]) {
                loadDatabasePreview();
                $("#uploadProgressModal").modal("hide");
                return;
            }

            let formData = new FormData();
            formData.append("action", "pr_quotes_process_csv");
            formData.append("file_order", index + 1);
            formData.append("csv_file", files[index]);

            $.ajax({
                url: ajaxurl,
                type: "POST",
                data: formData,
                processData: false,
                contentType: false,
                beforeSend: function() {
                    progressBars[index].css("width", "0%");
                },
                xhr: function() {
                    let xhr = new window.XMLHttpRequest();
                    xhr.upload.addEventListener("progress", function(evt) {
                        if (evt.lengthComputable) {
                            let percentComplete = (evt.loaded / evt.total) * 100;
                            progressBars[index].css("width", percentComplete + "%");
                        }
                    }, false);
                    return xhr;
                },
                success: function(response) {
                    $("#upload-status").append("<p>" + response.data.message + "</p>");
                    progressBars[index].css("width", "100%");
                    uploadFile(index + 1);
                },
                error: function() {
                    $("#upload-status").append("<p class='text-danger'>Error processing file " + (index + 1) + "</p>");
                }
            });
        }

        uploadFile(0);
    });

    function loadDatabasePreview() {
        $.ajax({
            url: ajaxurl,
            type: "POST",
            data: { action: "pr_quotes_fetch_database_preview" },
            success: function(response) {
                $("#database-preview").html(response.data.html);
            }
        });
    }
});
</script>

        <div id="settings" class="tab-pane fade">
            <h4>Plugin Settings</h4>
            <p>Current settings:</p>
            <span class="badge bg-success">Keep Data</span>
            <span class="badge bg-danger">Delete Data on Uninstall</span>
        </div>
    </div>
</div>

<?php } ?>
