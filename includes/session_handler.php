<?php
/**
 * Database Session Handler
 * Stores PHP sessions in Supabase PostgreSQL instead of files.
 * This allows multiple containers/servers to share sessions.
 */

class DatabaseSessionHandler implements SessionHandlerInterface {
    private PDO $pdo;
    private int $lifetime;

    public function __construct(PDO $pdo) {
        $this->pdo = $pdo;
        $this->lifetime = (int)ini_get('session.gc_maxlifetime') ?: 3600;
    }

    public function open(string $path, string $name): bool {
        return true;
    }

    public function close(): bool {
        return true;
    }

    public function read(string $id): string|false {
        try {
            $stmt = $this->pdo->prepare(
                "SELECT data FROM php_sessions 
                 WHERE id = :id 
                 AND last_activity > NOW() - ($this->lifetime * INTERVAL '1 second')"
            );
            $stmt->execute([':id' => $id]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            return $row ? $row['data'] : '';
        } catch (PDOException $e) {
            error_log("Session read error: " . $e->getMessage());
            return '';
        }
    }

    public function write(string $id, string $data): bool {
        try {
            $stmt = $this->pdo->prepare(
                "INSERT INTO php_sessions (id, data, last_activity)
                 VALUES (:id, :data, NOW())
                 ON CONFLICT (id) DO UPDATE 
                 SET data = EXCLUDED.data, last_activity = NOW()"
            );
            return $stmt->execute([':id' => $id, ':data' => $data]);
        } catch (PDOException $e) {
            error_log("Session write error: " . $e->getMessage());
            return false;
        }
    }

    public function destroy(string $id): bool {
        try {
            $stmt = $this->pdo->prepare(
                "DELETE FROM php_sessions WHERE id = :id"
            );
            return $stmt->execute([':id' => $id]);
        } catch (PDOException $e) {
            error_log("Session destroy error: " . $e->getMessage());
            return false;
        }
    }

    public function gc(int $max_lifetime): int|false {
        try {
            $stmt = $this->pdo->prepare(
                "DELETE FROM php_sessions 
                 WHERE last_activity < NOW() - ($max_lifetime * INTERVAL '1 second')"
            );
            $stmt->execute();
            return $stmt->rowCount();
        } catch (PDOException $e) {
            error_log("Session gc error: " . $e->getMessage());
            return false;
        }
    }
}