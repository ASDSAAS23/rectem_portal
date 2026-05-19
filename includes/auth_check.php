<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
function requireStudent() {
    if (!isset($_SESSION["user_role"]) || $_SESSION["user_role"] !== "student") {
        header("Location: ../login.php");
        exit();
    }
}
function requireAdmin() {
    if (!isset($_SESSION["user_role"]) || $_SESSION["user_role"] !== "admin") {
        header("Location: ../admin-login.php");
        exit();
    }
}
?>
