<?php
// Enqueue Admin Styles
function pr_quotes_admin_styles() {
    echo '<style>
        .pr-quote-box { padding: 10px; background-color: #f1f1f1; border: 1px solid #ccc; margin-bottom: 20px; }
        .pr-quote-item { padding: 5px; background-color: #e9e9e9; margin-bottom: 10px; }
    </style>';
}
add_action('admin_head', 'pr_quotes_admin_styles');
