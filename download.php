<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

require_once 'db.php';

class FileDownloader {
    private $uploadDir = '../uploads/';
    private $compressorPath = '../cpp/build/droplocker_compressor';
    private $db;

    public function __construct() {
        $this->db = new Database();
    }

    public function downloadFile($fileId, $password = null) {
        try {
            // Get file metadata
            $file = $this->db->fetch(
                "SELECT * FROM files WHERE id = ? AND expires_at > ? AND downloaded = 0",
                [$fileId, time()]
            );

            if (!$file) {
                throw new Exception('File not found or expired');
            }

            // Check password if required
            if (!empty($file['password_hash']) && !password_verify($password, $file['password_hash'])) {
                throw new Exception('Invalid password');
            }

            $encryptedFile = $this->uploadDir . 'encrypted_' . $fileId;
            
            if (!file_exists($encryptedFile)) {
                throw new Exception('File data not found');
            }

            // Decrypt file
            $decryptedFile = $this->uploadDir . 'decrypted_' . $fileId;
            if (!$this->decryptFile($encryptedFile, $decryptedFile, $file['encryption_key'])) {
                throw new Exception('Decryption failed');
            }

            // Decompress file
            $decompressedFile = $this->uploadDir . 'final_' . $fileId;
            $decompressionResult = $this->decompressFile($decryptedFile, $decompressedFile);

            if (!$decompressionResult['success']) {
                unlink($decryptedFile);
                throw new Exception('Decompression failed: ' . $decompressionResult['error']);
            }

            // Mark file as downloaded
            $this->db->execute("UPDATE files SET downloaded = 1 WHERE id = ?", [$fileId]);

            // Serve file for download
            $this->serveFile($decompressedFile, $file['original_name']);

            // Cleanup files
            unlink($encryptedFile);
            unlink($decryptedFile);
            unlink($decompressedFile);

        } catch (Exception $e) {
            http_response_code(400);
            echo json_encode(['error' => $e->getMessage()]);
        }
    }

    public function getFileInfo($fileId) {
        try {
            $file = $this->db->fetch(
                "SELECT original_name, original_size, compressed_size, compression_ratio, 
                        algorithm, password_hash IS NOT NULL as has_password, 
                        expires_at FROM files WHERE id = ? AND expires_at > ? AND downloaded = 0",
                [$fileId, time()]
            );

            if (!$file) {
                throw new Exception('File not found or expired');
            }

            return [
                'success' => true,
                'name' => $file['original_name'],
                'originalSize' => $file['original_size'],
                'compressedSize' => $file['compressed_size'],
                'compressionRatio' => $file['compression_ratio'],
                'algorithm' => $file['algorithm'],
                'hasPassword' => (bool)$file['has_password'],
                'expiresAt' => $file['expires_at']
            ];

        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    private function decryptFile($inputFile, $outputFile, $key) {
        $data = file_get_contents($inputFile);
        if ($data === false) {
            return false;
        }

        $iv = substr($data, 0, 16);
        $encrypted = substr($data, 16);
        
        $decrypted = openssl_decrypt($encrypted, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv);
        
        if ($decrypted === false) {
            return false;
        }

        return file_put_contents($outputFile, $decrypted) !== false;
    }

    private function decompressFile($inputFile, $outputFile) {
        require_once 'compression_fallback.php';
        return PHPCompressor::decompress($inputFile, $outputFile);
    }


    private function serveFile($filePath, $originalName) {
        if (!file_exists($filePath)) {
            throw new Exception('File not found');
        }

        $mimeType = mime_content_type($filePath) ?: 'application/octet-stream';
        $fileSize = filesize($filePath);

        header('Content-Type: ' . $mimeType);
        header('Content-Disposition: attachment; filename="' . $originalName . '"');
        header('Content-Length: ' . $fileSize);
        header('Cache-Control: no-cache, must-revalidate');

        readfile($filePath);
        exit;
    }
}

// Handle requests
$method = $_SERVER['REQUEST_METHOD'];
$fileId = $_GET['id'] ?? null;

if (!$fileId) {
    http_response_code(400);
    echo json_encode(['error' => 'File ID required']);
    exit;
}

$downloader = new FileDownloader();

if ($method === 'GET' && isset($_GET['info'])) {
    // Get file information
    $result = $downloader->getFileInfo($fileId);
    echo json_encode($result);
} elseif ($method === 'POST') {
    // Download file
    $input = json_decode(file_get_contents('php://input'), true);
    $password = $input['password'] ?? null;
    $downloader->downloadFile($fileId, $password);
} else {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
}
?>
