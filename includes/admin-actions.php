<?php 

if (isset($_GET['action']) && $_GET['action'] === 'generate-word-jc') {
    if (!empty($quote_data)) {
        $generated_file = generate_word_jc($quote_data);
        header('Content-Type: application/json');
        echo json_encode(['success' => true, 'url' => plugins_url('word-job-cards/' . basename($generated_file), dirname(__FILE__))]);
        exit;
    }
}

?>