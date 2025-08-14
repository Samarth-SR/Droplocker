<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

require_once __DIR__ . '/config.php';

// Helpers
function safe_filename($name) {
    return preg_replace('/[^a-zA-Z0-9_\-\.]/', '_', $name);
}
function master_encrypt($plaintext) {
    $master = base64_decode(MASTER_KEY);
    $ivlen = openssl_cipher_iv_length('aes-256-cbc');
    $iv = random_bytes($ivlen);
    $cipher = openssl_encrypt($plaintext, 'aes-256-cbc', $master, OPENSSL_RAW_DATA, $iv);
    $hmac = hash_hmac('sha256', $cipher . $iv, $master, true);
    return base64_encode($iv . $hmac . $cipher);
}
function write_metadata($metaPath, $meta) {
    $tmp = $metaPath . '.tmp';
    file_put_contents($tmp, json_encode($meta, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    rename($tmp, $metaPath);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Invalid request method']);
    exit;
}

if (!isset($_FILES['file'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'No file uploaded']);
    exit;
}

$file = $_FILES['file'];
if ($file['error'] !== UPLOAD_ERR_OK) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Upload error code: ' . $file['error']]);
    exit;
}

// generate id
try {
    $file_id = bin2hex(random_bytes(8));
} catch (Exception $e) {
    $file_id = bin2hex(openssl_random_pseudo_bytes(8));
}

$original_name = basename($file['name']);
$original_name_safe = safe_filename($original_name);
$ext = strtolower(pathinfo($original_name, PATHINFO_EXTENSION));
$target = UPLOAD_DIR . $file_id . ($ext !== '' ? '.' . $ext : '');

// move uploaded file
if (!move_uploaded_file($file['tmp_name'], $target)) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Failed to save uploaded file']);
    exit;
}

// set safe permissions
@chmod($target, 0644);

// read original content
$original_size = filesize($target);
$original_content = file_get_contents($target);
if ($original_content === false) {
    @unlink($target);
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Failed to read saved file']);
    exit;
}

// generate random per-file key and encrypt file content with AES-256-CBC
$file_key = random_bytes(32);
$ivlen = openssl_cipher_iv_length('aes-256-cbc');
$iv = random_bytes($ivlen);
$cipher = openssl_encrypt($original_content, 'aes-256-cbc', $file_key, OPENSSL_RAW_DATA, $iv);
$auth = hash_hmac('sha256', $cipher . $iv, $file_key, true);
$encrypted_blob = $iv . $auth . $cipher;

// Overwrite file with encrypted blob 
if (file_put_contents($target, $encrypted_blob) === false) {
    @unlink($target);
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Failed to write encrypted file']);
    exit;
}
@chmod($target, 0644);

$encrypted_size = filesize($target);

// store metadata
$meta = [
    'fileId' => $file_id,
    'originalName' => $original_name,
    'ext' => $ext,
    'createdAt' => time(),
    'originalSize' => $original_size,
    'encryptedSize' => $encrypted_size,
    'compressionRatio' => $original_size > 0 ? round((1 - ($encrypted_size / $original_size)) * 100, 2) : 0,
    'algorithm' => 'AES-256-CBC (server-side)',
    // store file key encrypted with server master key
    'file_key_enc' => master_encrypt($file_key),
    'hasPassword' => false,
    'password_hash' => null,
    'expiry' => null, // set by generate_link.php
    'downloaded' => false,
    'one_time' => true // default: delete after first successful download
];

$metaPath = DB_DIR . $file_id . '.json';
write_metadata($metaPath, $meta);

// debug log
@file_put_contents(UPLOAD_DIR . 'upload_debug.log', date('c') . " saved {$target}\n", FILE_APPEND);

// response
echo json_encode([
    'success' => true,
    'fileId' => $file_id,
    'originalName' => $original_name,
    'extension' => $ext,
    'originalSize' => $original_size,
    'compressedSize' => $encrypted_size,
    'compressionRatio' => $meta['compressionRatio'],
    'algorithm' => $meta['algorithm']
]);
exit;
