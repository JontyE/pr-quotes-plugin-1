jQuery(document).ready(function ($) {
    $("#download-job-card").on("click", function (e) {
        e.preventDefault();

        // 1. Gather all extra instructions from textareas
        let extraInstructions = {};
        $("[name^='extra_instructions']").each(function() {
            // name="extra_instructions[0]" => we extract the number '0'
            let regexMatch = $(this).attr("name").match(/\d+/);
            let index = regexMatch ? regexMatch[0] : 0; 
            extraInstructions[index] = $(this).val();
        });

        // 2. Show the popup & reset progress
        $("#download-popup").fadeIn(200);
        $("#progress-bar").css("width", "0%");

        // 3. Animate progress bar over 2.5 seconds
        $("#progress-bar").animate({ width: "100%" }, 2500);

        // 4. Prepare data for AJAX request
        let jobCardData = {
            action: "generate_word_jc",
            nonce: PR_QUOTES.nonce,
            client_info: PR_QUOTES.client_info,
            quote_info: PR_QUOTES.quote_info,
            items: PR_QUOTES.items,
            images: PR_QUOTES.images,
            // IMPORTANT: use the collected textareas
            extra_instructions: extraInstructions
        };

        // 5. AJAX Request
        $.ajax({
            url: PR_QUOTES.ajaxurl,
            type: "POST",
            data: jobCardData,
            success: function (response) {
                console.log("✅ AJAX Success:", response);

                if (response.success && response.data.download_url) {
                    let downloadUrl = response.data.download_url;
                    console.log("📂 Word document generated:", downloadUrl);

                    // Change popup message
                    $("#download-popup h4")
                        .text("✅ Job Card ready! Downloading now...")
                        .css("color", "green");

                    // Auto-download file
                    setTimeout(() => {
                        window.location.href = downloadUrl;
                    }, 3000);
                } else {
                    $("#download-popup h4")
                        .text("❌ ERROR Generating Job Card")
                        .css("color", "red");
                    console.error("⚠️ Error:", response.data.message || "Unknown error");
                }

                // Hide popup after 4 seconds
                setTimeout(() => {
                    $("#download-popup").fadeOut(300);
                }, 4000);
            },
            error: function (xhr) {
                console.error("❌ AJAX Error:", xhr.responseText);
                $("#download-popup h4")
                    .text("❌ ERROR ERROR ERROR")
                    .css("color", "red");

                setTimeout(() => {
                    $("#download-popup").fadeOut(300);
                }, 4000);
            }
        });
    });
});
