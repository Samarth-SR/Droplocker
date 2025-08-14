<?php
$upload_dir = '../uploads/';

if (isset($_GET['id'])) {
    $file_id = preg_replace('/[^a-f0-9]/', '', $_GET['id']); // sanitize
    $ext = isset($_GET['ext']) ? preg_replace('/[^a-zA-Z0-9]/', '', $_GET['ext']) : '';
    $filename = $upload_dir . $file_id . ($ext ? '.' . $ext : '');

    if (file_exists($filename)) {
        header('Content-Description: File Transfer');
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . basename($filename) . '"');
        header('Content-Length: ' . filesize($filename));
        flush();
        readfile($filename);
        exit;
    } else {
        echo "File not found";
    }
} else {
    echo "Invalid request";
}
?>
