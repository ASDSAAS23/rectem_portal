<?php
/**
 * Database Connection
 * Reads credentials from environment variables in production (Render).
 * Falls back to XAMPP defaults for local development.
 */
$host     = getenv('DB_HOST')     ?: 'localhost';
$username = getenv('DB_USER')     ?: 'root';
$password = getenv('DB_PASSWORD') ?: '';
$database = getenv('DB_NAME')     ?: 'rectem_portal';
$port     = (int)(getenv('DB_PORT') ?: 3306);

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
