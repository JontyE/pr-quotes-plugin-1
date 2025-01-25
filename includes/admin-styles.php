<?php 
function pr_quotes_enqueue_admin_styles($hook) {
    if ($hook !== 'toplevel_page_pr-quotes') {
        return;
    }

    wp_enqueue_style('bootstrap-css', 'https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css');
    wp_enqueue_script('bootstrap-js', 'https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js', ['jquery'], null, true);

    // ✅ Enqueue Custom JavaScript
    wp_enqueue_script('pr-quotes-admin-js', plugin_dir_url(__FILE__) . 'admin.js', ['jquery'], null, true);

    // ✅ Localize Script for AJAX Calls
    wp_localize_script('pr-quotes-admin-js', 'PR_QUOTES', [
        'ajaxurl' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('generate_word_jc'),
    ]);
}
add_action('admin_enqueue_scripts', 'pr_quotes_enqueue_admin_styles');




?>