<?php
/**
 * Notification Polling API
 * Returns new notifications since the student's last seen ID.
 */
session_start();
include("../includes/db.php");
include("../includes/auth_check.php");

header('Content-Type: application/json');

if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'student') {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$studentId = $_SESSION['user_id'];
$lastId = isset($_GET['last_id']) ? (int)$_GET['last_id'] : 0;

$notifications = [];
$maxId = $lastId;

if ($lastId === 0) {
    // Initial load: Just get the absolute latest ID so we don't paginate through old history
    $maxSql = "SELECT MAX(id) AS max_id FROM notifications WHERE student_id = ? OR audience = 'all'";
    $maxStmt = $conn->prepare($maxSql);
    $maxStmt->bind_param("i", $studentId);
    $maxStmt->execute();
    $maxResult = $maxStmt->get_result();
    if ($maxResult && $maxResult->num_rows > 0) {
        $maxId = (int)$maxResult->fetch_assoc()['max_id'];
    }
} else {
    // Polling for new notifications
    $sql = "SELECT id, title, message, type, created_at 
            FROM notifications 
            WHERE (student_id = ? OR audience = 'all') AND id > ? 
            ORDER BY id ASC 
            LIMIT 10";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ii", $studentId, $lastId);
    $stmt->execute();
    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {
        $notifications[] = $row;
        if ((int)$row['id'] > $maxId) {
            $maxId = (int)$row['id'];
        }
    }
}

// Get total unread count
$countSql = "SELECT COUNT(*) AS unread_count FROM notifications WHERE (student_id = ? OR audience = 'all') AND is_read = 0";
$countStmt = $conn->prepare($countSql);
$countStmt->bind_param("i", $studentId);
$countStmt->execute();
$countResult = $countStmt->get_result();
$unreadCount = 0;
if ($countResult && $countResult->num_rows > 0) {
    $unreadCount = (int)$countResult->fetch_assoc()['unread_count'];
}

echo json_encode([
    'success' => true,
    'notifications' => $notifications,
    'last_id' => $maxId,
    'unread_count' => $unreadCount,
]);
?>
