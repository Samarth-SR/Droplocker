<?php
class Database {
    private $connection;
    private $dbPath = '../database/droplocker.db';

    public function __construct() {
        try {
            // Create database directory if it doesn't exist
            $dbDir = dirname($this->dbPath);
            if (!is_dir($dbDir)) {
                mkdir($dbDir, 0777, true);
            }

            $this->connection = new PDO('sqlite:' . $this->dbPath);
            $this->connection->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->initializeTables();
        } catch (PDOException $e) {
            throw new Exception('Database connection failed: ' . $e->getMessage());
        }
    }

    private function initializeTables() {
        $sql = "
        CREATE TABLE IF NOT EXISTS files (
            id TEXT PRIMARY KEY,
            original_name TEXT NOT NULL,
            original_size INTEGER NOT NULL,
            compressed_size INTEGER NOT NULL,
            compression_ratio REAL NOT NULL,
            algorithm TEXT NOT NULL,
            encryption_key TEXT NOT NULL,
            password_hash TEXT NULL,
            created_at INTEGER NOT NULL,
            expires_at INTEGER NOT NULL,
            downloaded INTEGER DEFAULT 0
        );

        CREATE INDEX IF NOT EXISTS idx_expires_at ON files(expires_at);
        CREATE INDEX IF NOT EXISTS idx_downloaded ON files(downloaded);
        ";

        $this->connection->exec($sql);
    }

    public function execute($query, $params = []) {
        try {
            $stmt = $this->connection->prepare($query);
            return $stmt->execute($params);
        } catch (PDOException $e) {
            throw new Exception('Database query failed: ' . $e->getMessage());
        }
    }

    public function fetch($query, $params = []) {
        try {
            $stmt = $this->connection->prepare($query);
            $stmt->execute($params);
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            throw new Exception('Database fetch failed: ' . $e->getMessage());
        }
    }

    public function fetchAll($query, $params = []) {
        try {
            $stmt = $this->connection->prepare($query);
            $stmt->execute($params);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            throw new Exception('Database fetchAll failed: ' . $e->getMessage());
        }
    }

    public function cleanupExpiredFiles() {
        // Remove expired file records and associated files
        $expiredFiles = $this->fetchAll(
            "SELECT id FROM files WHERE expires_at <= ? OR downloaded = 1",
            [time()]
        );

        foreach ($expiredFiles as $file) {
            $fileId = $file['id'];
            
            // Remove physical files
            $uploadDir = '../uploads/';
            $filesToDelete = [
                $uploadDir . 'encrypted_' . $fileId,
                $uploadDir . 'temp_' . $fileId,
                $uploadDir . 'compressed_' . $fileId,
                $uploadDir . 'decrypted_' . $fileId,
                $uploadDir . 'final_' . $fileId
            ];

            foreach ($filesToDelete as $filePath) {
                if (file_exists($filePath)) {
                    unlink($filePath);
                }
            }
        }

        // Remove database records
        $this->execute("DELETE FROM files WHERE expires_at <= ? OR downloaded = 1", [time()]);
    }
}

// Auto-cleanup expired files (call this periodically)
if (rand(1, 100) <= 5) { // 5% chance to run cleanup
    try {
        $db = new Database();
        $db->cleanupExpiredFiles();
    } catch (Exception $e) {
        error_log('Cleanup failed: ' . $e->getMessage());
    }
}
?>
