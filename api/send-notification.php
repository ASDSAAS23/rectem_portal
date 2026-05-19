<?php
/**
 * Send Push Notification API
 * Called by admin when sending notifications - triggers device push.
 * Also supports sending a test notification.
 */
session_start();
include("../includes/db.php");
include("../includes/auth_check.php");
include("../includes/web_push.php");

header('Content-Type: application/json');

if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
if (!$input) {
    // Try form data
    $input = $_POST;
}

$title = trim($input['title'] ?? '');
$body = trim($input['message'] ?? '');
$target = trim($input['target'] ?? 'department');

if ($title === '' || $body === '') {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Title and message are required']);
    exit;
}

$adminId = $_SESSION['user_id'];
$adminSql = "SELECT department_id FROM admins WHERE id = ?";
$adminStmt = $conn->prepare($adminSql);
$adminStmt->bind_param("i", $adminId);
$adminStmt->execute();
$adminResult = $adminStmt->get_result();
$admin = $adminResult->fetch_assoc();
$adminDepartmentId = $admin['department_id'];

$pushSent = 0;
$pushFailed = 0;

if ($target === 'all') {
    // Insert notification for all
    $sql = "INSERT INTO notifications (student_id, title, message, audience, type, sent_via_email, is_read) VALUES (NULL, ?, ?, 'all', 'general', 0, 0)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ss", $title, $body);
    $stmt->execute();

    // Send push to all subscriptions
    $vapidKeys = getVAPIDKeys($conn);
    if (!empty($vapidKeys['vapid_public_key']) && !empty($vapidKeys['vapid_private_pem'])) {
        $payload = json_encode([
            'title' => $title,
            'body' => $body,
            'icon' => BASE_PATH . '/rectem-logo.png',
            'badge' => BASE_PATH . '/rectem-logo.png',
            'url' => BASE_PATH . '/student/notifications.php',
            'timestamp' => time(),
        ]);

        $result = $conn->query("SELECT * FROM push_subscriptions");
        while ($sub = $result->fetch_assoc()) {
            $pushResult = sendPushNotification($sub, $payload, $vapidKeys);
            if ($pushResult['success']) {
                $pushSent++;
            } else {
                $pushFailed++;
                if (in_array($pushResult['status'], [404, 410])) {
                    $delStmt = $conn->prepare("DELETE FROM push_subscriptions WHERE id = ?");
                    $delStmt->bind_param("i", $sub['id']);
                    $delStmt->execute();
                }
            }
        }
    }
} else {
    // Department students
    $studentSql = "SELECT id FROM students WHERE department_id = ?";
    $studentStmt = $conn->prepare($studentSql);
    $studentStmt->bind_param("i", $adminDepartmentId);
    $studentStmt->execute();
    $studentResult = $studentStmt->get_result();

    while ($student = $studentResult->fetch_assoc()) {
        $sql = "INSERT INTO notifications (student_id, title, message, audience, type, sent_via_email, is_read) VALUES (?, ?, ?, 'student', 'general', 0, 0)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("iss", $student['id'], $title, $body);
        $stmt->execute();

        // Send push
        sendPushToStudent($conn, $student['id'], $title, $body, BASE_PATH . '/student/notifications.php');
        $pushSent++; // Approximate - sendPushToStudent handles internally
    }
}

echo json_encode([
    'success' => true,
    'message' => 'Notification sent successfully',
    'push_sent' => $pushSent,
    'push_failed' => $pushFailed,
]);
?>
