<?php
/**
 * Push Subscription API
 * Handles subscribe and unsubscribe requests from the browser.
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
$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);

    if (!$input || empty($input['endpoint'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid subscription data']);
        exit;
    }

    $action = $input['action'] ?? 'subscribe';

    if ($action === 'subscribe') {
        $endpoint = $input['endpoint'];
        $p256dh = $input['keys']['p256dh'] ?? '';
        $authKey = $input['keys']['auth'] ?? '';
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';

        if (empty($p256dh) || empty($authKey)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Missing encryption keys']);
            exit;
        }

        // Check if subscription already exists
        $checkStmt = $conn->prepare("SELECT id FROM push_subscriptions WHERE student_id = ? AND endpoint = ?");
        $checkStmt->bind_param("is", $studentId, $endpoint);
        $checkStmt->execute();
        $checkResult = $checkStmt->get_result();

        if ($checkResult->num_rows > 0) {
            // Update existing
            $updateStmt = $conn->prepare("UPDATE push_subscriptions SET p256dh = ?, auth_key = ?, user_agent = ? WHERE student_id = ? AND endpoint = ?");
            $updateStmt->bind_param("sssis", $p256dh, $authKey, $userAgent, $studentId, $endpoint);
            $updateStmt->execute();
        } else {
            // Insert new
            $insertStmt = $conn->prepare("INSERT INTO push_subscriptions (student_id, endpoint, p256dh, auth_key, user_agent) VALUES (?, ?, ?, ?, ?)");
            $insertStmt->bind_param("issss", $studentId, $endpoint, $p256dh, $authKey, $userAgent);
            $insertStmt->execute();
        }

        echo json_encode(['success' => true, 'message' => 'Subscribed successfully']);

    } elseif ($action === 'unsubscribe') {
        $endpoint = $input['endpoint'] ?? '';

        if (!empty($endpoint)) {
            $delStmt = $conn->prepare("DELETE FROM push_subscriptions WHERE student_id = ? AND endpoint = ?");
            $delStmt->bind_param("is", $studentId, $endpoint);
            $delStmt->execute();
        }

        echo json_encode(['success' => true, 'message' => 'Unsubscribed successfully']);
    }

} elseif ($method === 'GET') {
    // Return VAPID public key
    $result = $conn->query("SELECT setting_value FROM portal_settings WHERE setting_key = 'vapid_public_key'");
    $row = $result->fetch_assoc();
    $publicKey = $row['setting_value'] ?? '';

    echo json_encode([
        'success' => true,
        'publicKey' => $publicKey,
    ]);

} else {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
}
?>
