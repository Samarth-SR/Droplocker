<?php
require_once __DIR__ . '/config.php';

function sanitize_id($id) {
    return preg_replace('/[^a-zA-Z0-9]/', '', (string)$id);
}
function sanitize_ext($ext) {
    return strtolower(preg_replace('/[^a-z0-9]/', '', (string)$ext));
}
function meta_path($file_id) {
    return DB_DIR . $file_id . '.json';
}
function read_meta($file_id) {
    $p = meta_path($file_id);
    if (!file_exists($p)) return false;
    $j = json_decode(file_get_contents($p), true);
    return is_array($j) ? $j : false;
}
function delete_file_and_meta($file_id) {
    $pmeta = meta_path($file_id);
    // try delete files matching id.*
    $files = glob(UPLOAD_DIR . $file_id . '.*');
    foreach ($files as $f) @unlink($f);
    if (file_exists($pmeta)) @unlink($pmeta);
}
function master_decrypt($b64) {
    $master = base64_decode(MASTER_KEY);
    $data = base64_decode($b64);
    $ivlen = openssl_cipher_iv_length('aes-256-cbc');
    $iv = substr($data, 0, $ivlen);
    $hmac = substr($data, $ivlen, 32);
    $cipher = substr($data, $ivlen + 32);
    $calc = hash_hmac('sha256', $cipher . $iv, $master, true);
    if (!hash_equals($hmac, $calc)) return false;
    return openssl_decrypt($cipher, 'aes-256-cbc', $master, OPENSSL_RAW_DATA, $iv);
}

// Allow info endpoint
if (isset($_GET['id']) && isset($_GET['info'])) {
    header('Content-Type: application/json');
    $file_id = sanitize_id($_GET['id']);
    $meta = read_meta($file_id);
    if (!$meta) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'File not available']);
        exit;
    }  
     
    // check expiry
    if (!empty($meta['expiry']) && time() > (int)$meta['expiry']) {
        delete_file_and_meta($file_id);
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Expired']);
        exit;
    }

    // find upload filename (if ext changed)
    $matches = glob(UPLOAD_DIR . $file_id . '.*');
    $found = !empty($matches) ? $matches[0] : false;
    $name = $meta['originalName'] ?? ($found ? basename($found) : $file_id);
    $ext = $meta['ext'] ?? ($found ? pathinfo($found, PATHINFO_EXTENSION) : '');

    echo json_encode([
        'success' => true,
        'id' => $file_id,
        'name' => $name,
        'ext' => $ext,
        'originalSize' => $meta['originalSize'] ?? null,
        'compressedSize' => $meta['encryptedSize'] ?? null,
        'compressionRatio' => $meta['compressionRatio'] ?? 0,
        'algorithm' => $meta['algorithm'] ?? 'AES-256-CBC',
        'hasPassword' => !empty($meta['hasPassword'])
    ]);
    exit;
}

// Download logic
// Accept POST for password-protected downloads; support GET for non-password.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // expected form fields: id, ext (optional), password (optional)
    $file_id = isset($_POST['id']) ? sanitize_id($_POST['id']) : '';
    $ext = isset($_POST['ext']) ? sanitize_ext($_POST['ext']) : '';
    $password = isset($_POST['password']) ? (string)$_POST['password'] : null;
} else {
    // GET
    if (!isset($_GET['id'])) {
        http_response_code(400);
        echo "Invalid request.";
        exit;
    }
    $file_id = sanitize_id($_GET['id']);
    $ext = isset($_GET['ext']) ? sanitize_ext($_GET['ext']) : '';
    $password = null;
}

$meta = read_meta($file_id);
if (!$meta) {
    http_response_code(404);
    echo "File not found or expired.";
    exit;
}

// expiry check
if (!empty($meta['expiry']) && time() > (int)$meta['expiry']) {
    delete_file_and_meta($file_id);
    http_response_code(404);
    echo "File expired.";
    exit;
}

// password guard
if (!empty($meta['hasPassword'])) {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !$password) {
        // This endpoint requires POST with password
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Wrong Password']);
        exit;
    }
    if (empty($meta['password_hash']) || !password_verify($password, $meta['password_hash'])) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Wrong Password']);
        exit;
    }
}

// find encrypted file on disk
$matches = glob(UPLOAD_DIR . $file_id . '.*');
$found = !empty($matches) ? $matches[0] : false;
if (!$found || !file_exists($found)) {
    // nothing to serve
    delete_file_and_meta($file_id);
    http_response_code(404);
    echo "File not found.";
    exit;
}

// read encrypted blob
$blob = file_get_contents($found);
if ($blob === false) {
    http_response_code(500);
    echo "Server error reading file.";
    exit;
}

// parse iv(16) + hmac(32) + cipher
$ivlen = openssl_cipher_iv_length('aes-256-cbc');
if (strlen($blob) < ($ivlen + 32 + 1)) {
    http_response_code(500);
    echo "Corrupt file.";
    exit;
}
$iv = substr($blob, 0, $ivlen);
$hmac = substr($blob, $ivlen, 32);
$cipher = substr($blob, $ivlen + 32);

// decrypt file key using master key
if (empty($meta['file_key_enc'])) {
    http_response_code(500);
    echo "Missing key.";
    exit;
}
$master_key_plain = master_decrypt($meta['file_key_enc']);
if ($master_key_plain === false) {
    http_response_code(500);
    echo "Key decryption failed.";
    exit;
}

// verify integrity with stored HMAC (HMAC was created with file key — we don't store auth in metadata, we have it inside blob)
// The blob HMAC was computed during encryption: hash_hmac('sha256', $cipher . $iv, $file_key, true)
$calc_hmac = hash_hmac('sha256', $cipher . $iv, $master_key_plain, true);
if (!hash_equals($hmac, $calc_hmac)) {
    http_response_code(500);
    echo "Integrity check failed.";
    exit;
}

// decrypt ciphertext with file key
$plaintext = openssl_decrypt($cipher, 'aes-256-cbc', $master_key_plain, OPENSSL_RAW_DATA, $iv);
if ($plaintext === false) {
    http_response_code(500);
    echo "Decryption failed.";
    exit;
}

// stream decrypted content as download
$basename = $meta['originalName'] ?? basename($found);
$mime = 'application/octet-stream';
if (function_exists('mime_content_type')) {
    $m = @mime_content_type($found);
    if ($m) $mime = $m;
}

header('Content-Description: File Transfer');
header('Content-Type: ' . $mime);
header('Content-Disposition: attachment; filename="' . basename($basename) . '"');
header('Expires: 0');
header('Cache-Control: must-revalidate');
header('Pragma: public');
header('Content-Length: ' . strlen($plaintext));
flush();
echo $plaintext;
flush();

// mark as downloaded and delete file/metadata if configured
if (!empty($meta['one_time'])) {
    delete_file_and_meta($file_id);
} else {
    // mark downloaded true
    $meta['downloaded'] = true;
    file_put_contents(meta_path($file_id), json_encode($meta, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
}

exit;
