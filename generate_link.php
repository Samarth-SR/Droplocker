<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);

    if (empty($input['fileId']) || empty($input['extension'])) {
        echo json_encode(['success' => false, 'error' => 'Invalid file data']);
        exit;
    }

    $file_id = $input['fileId'];
    $ext = $input['extension'];

    $baseUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http')
             . '://' . $_SERVER['HTTP_HOST'] . dirname($_SERVER['REQUEST_URI']);

    $download_url = $baseUrl . '/php/download.php?id=' . urlencode($file_id) . '&ext=' . urlencode($ext);

    echo json_encode(['success' => true, 'link' => $download_url]);
} else {
    echo json_encode(['success' => false, 'error' => 'Invalid request']);
}
?>
