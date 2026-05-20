<?php
/**
 * Database Connection
 * Reads credentials from environment variables in production (Render).
 * Falls back to XAMPP defaults for local development.
 */
$host     = $_SERVER['DB_HOST'] ?? getenv('DB_HOST') ?: 'localhost';
$username = $_SERVER['DB_USER'] ?? getenv('DB_USER') ?: 'root';
$password = $_SERVER['DB_PASSWORD'] ?? getenv('DB_PASSWORD') ?: '';
$database = $_SERVER['DB_NAME'] ?? getenv('DB_NAME') ?: 'rectem_portal';
$port     = (int)($_SERVER['DB_PORT'] ?? getenv('DB_PORT') ?: 3306);

// Define dynamic base path to seamlessly switch between XAMPP subdirectory and Render root
$base_path = str_contains($_SERVER['SCRIPT_NAME'] ?? '', '/rectem_portal/') ? '/rectem_portal' : '';
define('BASE_PATH', $base_path);

$conn = new mysqli($host, $username, $password, $database, $port);
if ($conn->connect_error) {
    http_response_code(500);
    die(json_encode(['error' => 'Database connection failed.']));
}
$conn->set_charset('utf8mb4');
?>
