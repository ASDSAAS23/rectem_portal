<?php
/**
 * VAPID Key Generator - Run this ONCE to generate keys.
 * Access via: http://localhost/rectem_portal/api/generate-vapid.php
 */
include("../includes/db.php");
include("../includes/web_push.php");

$keys = generateVAPIDKeys($conn);

if ($keys) {
    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'message' => 'VAPID keys generated successfully.',
        'publicKey' => $keys['publicKey'],
    ]);
} else {
    header('Content-Type: application/json');
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Failed to generate VAPID keys. Make sure PHP openssl extension is enabled.',
    ]);
}
?>
