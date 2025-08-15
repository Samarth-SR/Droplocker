<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

require_once __DIR__ . '/config.php';

// Helpers (same as upload.php)
function sanitize_id($id) {
    return preg_replace('/[^a-zA-Z0-9]/', '', (string)$id);
}
function master_encrypt($plaintext) {
    $master = base64_decode(MASTER_KEY);
    $ivlen = openssl_cipher_iv_length('aes-256-cbc');
    $iv = random_bytes($ivlen);
    $cipher = openssl_encrypt($plaintext, 'aes-256-cbc', $master, OPENSSL_RAW_DATA, $iv);
    $hmac = hash_hmac('sha256', $cipher . $iv, $master, true);
    return base64_encode($iv . $hmac . $cipher);
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
function write_metadata($metaPath, $meta) {
    $tmp = $metaPath . '.tmp';
    file_put_contents($tmp, json_encode($meta, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    rename($tmp, $metaPath);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Invalid request']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
if (!is_array($input) || empty($input['fileId'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid file data']);
    exit;
}

$file_id = sanitize_id($input['fileId']);
$ext = isset($input['extension']) ? strtolower(preg_replace('/[^a-z0-9]/', '', (string)$input['extension'])) : '';
$expirySeconds = isset($input['expiry']) ? (int)$input['expiry'] : 86400; // default 24 hours
$password = isset($input['password']) && $input['password'] !== '' ? (string)$input['password'] : null;

$uploadCandidate = UPLOAD_DIR . $file_id . ($ext !== '' ? '.' . $ext : '');
if (!file_exists($uploadCandidate)) {
    // try glob
    $matches = glob(UPLOAD_DIR . $file_id . '.*');
    if (!empty($matches)) {
        $uploadCandidate = $matches[0];
        $ext = pathinfo($uploadCandidate, PATHINFO_EXTENSION);
    } else {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'File not found on server']);
        exit;
    }
}

// metadata path
$metaPath = DB_DIR . $file_id . '.json';
$meta = null;

// if metadata exists, load it; otherwise, try to create minimal metadata (compatibility)
if (file_exists($metaPath)) {
    $meta = json_decode(file_get_contents($metaPath), true);
    if (!is_array($meta)) $meta = null;
}

if ($meta === null) {
    // attempt to create metadata by creating a random file key and encrypting in-place if needed.
    // If the file looks already encrypted 
    $content = file_get_contents($uploadCandidate);
    if ($content === false) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Cannot read file to create metadata']);
        exit;
    }

    // Heuristic: if file length > 48 and we expect it may be already encrypted by prior run — but we cannot derive file key.
    $file_key = random_bytes(32);
    $ivlen = openssl_cipher_iv_length('aes-256-cbc');
    $iv = random_bytes($ivlen);
    $cipher = openssl_encrypt($content, 'aes-256-cbc', $file_key, OPENSSL_RAW_DATA, $iv);
    $auth = hash_hmac('sha256', $cipher . $iv, $file_key, true);
    $encrypted_blob = $iv . $auth . $cipher;

    if (file_put_contents($uploadCandidate, $encrypted_blob) === false) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Failed to encrypt existing file']);
        exit;
    }

    $meta = [
        'fileId' => $file_id,
        'originalName' => basename($uploadCandidate),
        'ext' => $ext,
        'createdAt' => time(),
        'originalSize' => $size,
        'encryptedSize' => filesize($uploadCandidate),
        'compressionRatio' => $size > 0 ? round((1 - (filesize($uploadCandidate) / $size)) * 100, 2) : 0,
        'algorithm' => 'AES-256-CBC (server-side)',
        'file_key_enc' => master_encrypt($file_key),
        'hasPassword' => false,
        'password_hash' => null,
        'expiry' => null,
        'downloaded' => false,
        'one_time' => true
    ];
    write_metadata($metaPath, $meta);
}
else {
    // if meta file is present
    $data = file_get_contents($metaPath); // Fetches the already exsisting metadata fiel
    $meta = json_decode($data,true);
}


// update metadata with expiry and password options
if ($expirySeconds > 0) {
    $max = 60 * 60 * 24 * 30; // max 30 days | default 24 hours
    $expirySeconds = min($expirySeconds, $max);
    $meta['expiry'] = time() + $expirySeconds; // Give Current Time + 1 Hour
} else {
    $meta['expiry'] = null;
}

if ($password !== null) {
    $meta['hasPassword'] = true;
    $meta['password_hash'] = password_hash($password, PASSWORD_DEFAULT);
} else {
    $meta['hasPassword'] = false;
    $meta['password_hash'] = null;
}

// Keep file details up-to-date
$meta['ext'] = $ext;
$meta['updatedAt'] = time();

write_metadata($metaPath, $meta);

// build links 
$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'];
$scriptDir = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\');
$rootDir = dirname($scriptDir);
if ($rootDir === '/' || $rootDir === '\\' || $rootDir === '.') $rootDir = '';

$uiLink = $scheme . '://' . $host . $rootDir . '/?download=' . urlencode($file_id);
if ($ext !== '') $uiLink .= '&ext=' . urlencode($ext);

$directLink = $scheme . '://' . $host . $scriptDir . '/download.php?id=' . urlencode($file_id);
if ($ext !== '') $directLink .= '&ext=' . urlencode($ext);

echo json_encode([
    'success' => true,
    'link' => $uiLink,
    'directLink' => $directLink,
    'fileId' => $file_id,
    'extension' => $ext,
    'hasPassword' => !!$meta['hasPassword'],
    'expiry' => $meta['expiry']
]);
exit;
