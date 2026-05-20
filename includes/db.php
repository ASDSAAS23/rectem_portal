<?php
/**
 * Database Connection — Supabase PostgreSQL via PDO
 * 
 * Uses a MySQLi-compatible wrapper so all existing code works without changes.
 * Reads credentials from environment variables in production.
 * Falls back to Supabase defaults for development.
 */

$host     = $_SERVER['DB_HOST']     ?? getenv('DB_HOST')     ?: 'aws-1-eu-west-2.pooler.supabase.com';
$username = $_SERVER['DB_USER']     ?? getenv('DB_USER')     ?: 'postgres.jkgcjbppnkyzwcaelgho';
$password = $_SERVER['DB_PASSWORD'] ?? getenv('DB_PASSWORD') ?: 'jEHyKmh1JKsOvarZ';
$database = $_SERVER['DB_NAME']     ?? getenv('DB_NAME')     ?: 'postgres';
$port     = (int)($_SERVER['DB_PORT'] ?? getenv('DB_PORT')   ?: 6543);

// Define dynamic base path to seamlessly switch between XAMPP subdirectory and Render root
$base_path = str_contains($_SERVER['SCRIPT_NAME'] ?? '', '/rectem_portal/') ? '/rectem_portal' : '';
define('BASE_PATH', $base_path);

// ============================================================
// MySQLi-compatible wrapper classes for PDO + PostgreSQL
// ============================================================

/**
 * Wraps PDO result data to mimic mysqli_result interface.
 */
class PgResult {
    public int $num_rows;
    private array $rows;
    private int $pointer = 0;

    public function __construct(array $rows) {
        $this->rows = $rows;
        $this->num_rows = count($rows);
    }

    public function fetch_assoc(): ?array {
        if ($this->pointer >= $this->num_rows) {
            return null;
        }
        return $this->rows[$this->pointer++];
    }

    public function fetch_all(int $mode = 0): array {
        return $this->rows;
    }
}

/**
 * Wraps PDOStatement to mimic mysqli_stmt interface.
 */
class PgStatement {
    private PDOStatement $stmt;
    private array $paramValues = [];
    private array $paramTypes = [];
    private ?PgResult $result = null;

    public function __construct(PDOStatement $stmt) {
        $this->stmt = $stmt;
    }

    /**
     * Mimics mysqli_stmt::bind_param("iss", $a, $b, $c)
     */
    public function bind_param(string $types, mixed &...$vars): bool {
        $this->paramTypes = str_split($types);
        $this->paramValues = [];
        foreach ($vars as $i => &$var) {
            $this->paramValues[$i] = &$var;
        }
        return true;
    }

    /**
     * Execute the prepared statement.
     */
    public function execute(): bool {
        try {
            if (!empty($this->paramValues)) {
                foreach ($this->paramValues as $i => $value) {
                    $pdoType = PDO::PARAM_STR;
                    if (isset($this->paramTypes[$i])) {
                        $pdoType = match ($this->paramTypes[$i]) {
                            'i' => PDO::PARAM_INT,
                            'd' => PDO::PARAM_STR,
                            'b' => PDO::PARAM_LOB,
                            default => PDO::PARAM_STR,
                        };
                    }
                    $this->stmt->bindValue($i + 1, $value, $pdoType);
                }
            }
            return $this->stmt->execute();
        } catch (PDOException $e) {
            error_log("PgStatement execute error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Mimics mysqli_stmt::get_result()
     */
    public function get_result(): PgResult|false {
        try {
            $rows = $this->stmt->fetchAll(PDO::FETCH_ASSOC);
            $this->result = new PgResult($rows);
            return $this->result;
        } catch (PDOException $e) {
            error_log("PgStatement get_result error: " . $e->getMessage());
            return false;
        }
    }
}

/**
 * Wraps PDO to mimic the mysqli interface used throughout the project.
 */
class PgConnection {
    private PDO $pdo;
    public bool $connect_error = false;

    public function __construct(PDO $pdo) {
        $this->pdo = $pdo;
    }

    public function prepare(string $sql): PgStatement|false {
        try {
            $sql = $this->translateSQL($sql);
            $stmt = $this->pdo->prepare($sql);
            return new PgStatement($stmt);
        } catch (PDOException $e) {
            error_log("PgConnection prepare error: " . $e->getMessage() . " | SQL: " . $sql);
            return false;
        }
    }

    public function query(string $sql): PgResult|false {
        try {
            $sql = $this->translateSQL($sql);
            $stmt = $this->pdo->query($sql);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            return new PgResult($rows);
        } catch (PDOException $e) {
            error_log("PgConnection query error: " . $e->getMessage() . " | SQL: " . $sql);
            return false;
        }
    }

    public function real_escape_string(string $str): string {
        $quoted = $this->pdo->quote($str);
        return substr($quoted, 1, -1);
    }

    public function set_charset(string $charset): bool {
        return true;
    }

    public function getPDO(): PDO {
        return $this->pdo;
    }

    private function translateSQL(string $sql): string {
        if (stripos($sql, 'MODIFY COLUMN') !== false) {
            $sql = preg_replace(
                '/ALTER\s+TABLE\s+(\S+)\s+MODIFY\s+COLUMN\s+(\S+)\s+(.+)/i',
                'ALTER TABLE $1 ALTER COLUMN $2 TYPE $3',
                $sql
            );
        }
        return $sql;
    }
}

// ============================================================
// Create the connection
// ============================================================

try {
    $dsn = "pgsql:host=$host;port=$port;dbname=$database;sslmode=require";
    $pdo = new PDO($dsn, $username, $password, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ]);
    $conn = new PgConnection($pdo);
} catch (PDOException $e) {
    http_response_code(500);
    error_log("Supabase connection failed: " . $e->getMessage());
    die(json_encode(['error' => 'Database connection failed.']));
}

// ============================================================
// Database-backed session handler (supports multiple users
// and multiple containers simultaneously)
// ============================================================

require_once __DIR__ . '/session_handler.php';

ini_set('session.gc_maxlifetime', 3600); // Sessions last 1 hour
session_set_cookie_params([
    'lifetime' => 3600,
    'path'     => '/',
    'secure'   => isset($_SERVER['HTTPS']), // HTTPS only in production
    'httponly' => true,                     // Prevent JS access to session cookie
    'samesite' => 'Lax',
]);

$sessionHandler = new DatabaseSessionHandler($pdo);
session_set_save_handler($sessionHandler, true);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}