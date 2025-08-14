<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

require_once 'db.php';

class FileProcessor {
    private $uploadDir = '../uploads/';
    private $compressorPath = '../cpp/build/droplocker_compressor';
    private $db;

    public function __construct() {
        $this->db = new Database();
        if (!is_dir($this->uploadDir)) {
            mkdir($this->uploadDir, 0777, true);
        }
    }

    public function processUpload() {
        try {
            if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
                throw new Exception('File upload failed');
            }

            $file = $_FILES['file'];
            $originalName = $file['name'];
            $tempPath = $file['tmp_name'];
            $originalSize = $file['size'];

            // Generate unique file ID
            $fileId = $this->generateUniqueId();
            $tempFile = $this->uploadDir . 'temp_' . $fileId;
            $compressedFile = $this->uploadDir . 'compressed_' . $fileId;

            // Move uploaded file to temp location
            if (!move_uploaded_file($tempPath, $tempFile)) {
                throw new Exception('Failed to move uploaded file');
            }

            // Compress file using C++
            $compressionResult = $this->compressFile($tempFile, $compressedFile);
            
            if (!$compressionResult['success']) {
                unlink($tempFile);
                throw new Exception('Compression failed: ' . $compressionResult['error']);
            }

            // Encrypt compressed file
            $encryptionKey = $this->generateEncryptionKey();
            $encryptedFile = $this->uploadDir . 'encrypted_' . $fileId;
            
            if (!$this->encryptFile($compressedFile, '.gz', $encryptedFile, $encryptionKey)) {
                unlink($tempFile);
                unlink($compressedFile);
                throw new Exception('Encryption failed');
            }

            // Clean up temporary files
            unlink($tempFile);
            unlink($compressedFile . '.gz');

            // Store metadata in database
            $this->storeFileMetadata($fileId, $originalName, $originalSize, 
                                   $compressionResult, $encryptionKey);

            return [
                'success' => true,
                'fileId' => $fileId,
                'originalSize' => $originalSize,
                'compressedSize' => $compressionResult['compressedSize'],
                'compressionRatio' => $compressionResult['compressionRatio'],
                'algorithm' => $compressionResult['algorithm']
            ];

        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    private function compressFile($inputFile, $outputFile) {
        require_once 'compression_fallback.php';
        return PHPCompressor::compress($inputFile, $outputFile);
     }

    private function encryptFile($inputFile, $outputFile, $key) {
        $data = file_get_contents($inputFile);
        if ($data === false) {
            return false;
        }

        $iv = random_bytes(16);
        $encrypted = openssl_encrypt($data, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv);
        
        if ($encrypted === false) {
            return false;
        }

        return file_put_contents($outputFile, $iv . $encrypted) !== false;
    }

    private function generateUniqueId() {
        return bin2hex(random_bytes(16));
    }

    private function generateEncryptionKey() {
        return base64_encode(random_bytes(32));
    }

    private function storeFileMetadata($fileId, $originalName, $originalSize, 
                                     $compressionResult, $encryptionKey) {
        $expiryTime = time() + (24 * 60 * 60); // 24 hours default
        
        $this->db->execute(
            "INSERT INTO files (id, original_name, original_size, compressed_size, 
             compression_ratio, algorithm, encryption_key, created_at, expires_at) 
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)",
            [
                $fileId, $originalName, $originalSize, $compressionResult['compressedSize'],
                $compressionResult['compressionRatio'], $compressionResult['algorithm'],
                $encryptionKey, time(), $expiryTime
            ]
        );
    }
}

// Handle the upload request
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $processor = new FileProcessor();
    $result = $processor->processUpload();
    echo json_encode($result);
} else {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
}
?>
