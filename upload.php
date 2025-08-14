<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

$upload_dir = '../uploads/';

if (!is_dir($upload_dir)) {
    mkdir($upload_dir, 0777, true);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['file'])) {
    $file_id = bin2hex(random_bytes(8)); // short unique ID
    $original_name = basename($_FILES['file']['name']);
    $ext = pathinfo($original_name, PATHINFO_EXTENSION);
    $target_file = $upload_dir . $file_id . ($ext ? '.' . $ext : '');

    if (move_uploaded_file($_FILES['file']['tmp_name'], $target_file)) {
        echo json_encode([
            'success' => true,
            'fileId' => $file_id,
            'originalName' => $original_name,
            'extension' => $ext,
            'originalSize' => filesize($target_file),
            'compressedSize' => filesize($target_file), // keep for UI
            'compressionRatio' => 0, // no compression
            'algorithm' => 'None' // displayed in UI
        ]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Failed to save uploaded file']);
    }
} else {
    echo json_encode(['success' => false, 'error' => 'No file received']);
}
?>
