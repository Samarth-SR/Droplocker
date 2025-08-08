<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

require_once 'db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $input = json_decode(file_get_contents('php://input'), true);
        
        if (!$input || !isset($input['fileId'])) {
            throw new Exception('Invalid request data');
        }

        $fileId = $input['fileId'];
        $expiryTime = time() + (int)($input['expiryTime'] ?? 86400); // Default 24 hours
        $password = $input['password'] ?? null;

        $db = new Database();
        
        // Verify file exists
        $file = $db->fetch("SELECT id FROM files WHERE id = ?", [$fileId]);
        if (!$file) {
            throw new Exception('File not found');
        }

        // Update file with expiry and password
        $passwordHash = $password ? password_hash($password, PASSWORD_BCRYPT) : null;
        
        $db->execute(
            "UPDATE files SET expires_at = ?, password_hash = ? WHERE id = ?",
            [$expiryTime, $passwordHash, $fileId]
        );

        // Generate secure link
        $baseUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') 
                  . '://' . $_SERVER['HTTP_HOST'] . dirname($_SERVER['REQUEST_URI']);
        
        $downloadUrl = str_replace('/php', '', $baseUrl) . '?download=' . $fileId;

        echo json_encode([
            'success' => true,
            'link' => $downloadUrl,
            'expiresAt' => $expiryTime
        ]);

    } catch (Exception $e) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => $e->getMessage()
        ]);
    }
} else {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
}
?>
