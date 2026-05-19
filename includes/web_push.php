<?php
/**
 * RECTEM Portal - Pure PHP Web Push Notification Helper
 * Implements the Web Push protocol (RFC 8291) without external dependencies.
 * Uses VAPID (RFC 8292) for server authentication.
 */

/**
 * Get OpenSSL config.
 * On Windows/XAMPP locally, points to the bundled openssl.cnf.
 * On Linux (Render), OpenSSL is system-configured — no override needed.
 */
function getOpenSSLConfig() {
    $config = [];
    if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
        $candidates = [
            'C:/xampp/php/extras/ssl/openssl.cnf',
            'C:/Program Files/OpenSSL-Win64/bin/openssl.cfg',
        ];
        foreach ($candidates as $path) {
            if (file_exists($path)) {
                $config['config'] = $path;
                break;
            }
        }
    }
    return $config;
}

/**
 * Generate VAPID key pair (ECDSA P-256) and store in portal_settings.
 * Run this ONCE via generate-vapid.php
 */
function generateVAPIDKeys($conn) {
    // Ensure the setting_value column is large enough for PEM keys
    $conn->query("ALTER TABLE portal_settings MODIFY COLUMN setting_value TEXT");

    $config = getOpenSSLConfig();
    $key = openssl_pkey_new(array_merge([
        'curve_name'       => 'prime256v1',
        'private_key_type' => OPENSSL_KEYTYPE_EC,
    ], $config));

    if (!$key) {
        return false;
    }

    $details = openssl_pkey_get_details($key);
    openssl_pkey_export($key, $privatePEM, null, $config);

    // Ensure PEM is properly formatted (trim + trailing newline)
    $privatePEM = trim($privatePEM) . "\n";

    // The public key is the uncompressed point: 0x04 || x || y
    $x = $details['ec']['x'];
    $y = $details['ec']['y'];

    // Pad x and y to 32 bytes each
    $x = str_pad($x, 32, "\0", STR_PAD_LEFT);
    $y = str_pad($y, 32, "\0", STR_PAD_LEFT);

    $publicKeyUncompressed = "\x04" . $x . $y;
    $publicKeyB64 = base64url_encode($publicKeyUncompressed);

    // Store PEM private key as base64url of raw 32-byte scalar
    $d = $details['ec']['d'];
    $d = str_pad($d, 32, "\0", STR_PAD_LEFT);
    $privateKeyB64 = base64url_encode($d);

    // Save to database
    $stmt = $conn->prepare("INSERT INTO portal_settings (setting_key, setting_value) VALUES ('vapid_public_key', ?) ON DUPLICATE KEY UPDATE setting_value = ?");
    $stmt->bind_param("ss", $publicKeyB64, $publicKeyB64);
    $stmt->execute();

    $stmt = $conn->prepare("INSERT INTO portal_settings (setting_key, setting_value) VALUES ('vapid_private_key', ?) ON DUPLICATE KEY UPDATE setting_value = ?");
    $stmt->bind_param("ss", $privateKeyB64, $privateKeyB64);
    $stmt->execute();

    // Also save the PEM for signing (base64-encoded to safely store in DB)
    $pemB64 = base64_encode($privatePEM);
    $stmt = $conn->prepare("INSERT INTO portal_settings (setting_key, setting_value) VALUES ('vapid_private_pem', ?) ON DUPLICATE KEY UPDATE setting_value = ?");
    $stmt->bind_param("ss", $pemB64, $pemB64);
    $stmt->execute();

    // Clear old push subscriptions (new keys require re-subscription)
    $conn->query("DELETE FROM push_subscriptions");

    return [
        'publicKey'  => $publicKeyB64,
        'privateKey' => $privateKeyB64,
    ];
}

/**
 * Get VAPID keys from the database.
 */
function getVAPIDKeys($conn) {
    $keys = [];
    $result = $conn->query("SELECT setting_key, setting_value FROM portal_settings WHERE setting_key IN ('vapid_public_key', 'vapid_private_key', 'vapid_subject', 'vapid_private_pem')");

    while ($row = $result->fetch_assoc()) {
        $keys[$row['setting_key']] = $row['setting_value'];
    }

    return $keys;
}

/**
 * Send a push notification to a single subscription.
 *
 * @param array  $subscription ['endpoint', 'p256dh', 'auth_key']
 * @param string $payload      JSON string of notification data
 * @param array  $vapidKeys    From getVAPIDKeys()
 * @return array ['success' => bool, 'status' => int, 'reason' => string]
 */
function sendPushNotification($subscription, $payload, $vapidKeys) {
    $endpoint = $subscription['endpoint'];
    $clientPublicKey = base64url_decode($subscription['p256dh']);
    $clientAuthToken = base64url_decode($subscription['auth_key']);

    if (strlen($clientPublicKey) !== 65 || strlen($clientAuthToken) !== 16) {
        return ['success' => false, 'status' => 0, 'reason' => 'Invalid subscription keys'];
    }

    // --- VAPID JWT ---
    $parsedUrl = parse_url($endpoint);
    $audience = $parsedUrl['scheme'] . '://' . $parsedUrl['host'];

    $privatePEM = base64_decode($vapidKeys['vapid_private_pem']);
    $subject = $vapidKeys['vapid_subject'] ?? 'mailto:admin@rectem.edu.ng';

    $jwt = createVAPIDJWT($audience, $subject, $privatePEM);
    if (!$jwt) {
        return ['success' => false, 'status' => 0, 'reason' => 'Failed to create VAPID JWT'];
    }

    $vapidPublicKey = base64url_decode($vapidKeys['vapid_public_key']);

    // --- Encrypt payload (RFC 8291: aes128gcm) ---
    $encrypted = encryptPayload($payload, $clientPublicKey, $clientAuthToken);
    if (!$encrypted) {
        return ['success' => false, 'status' => 0, 'reason' => 'Failed to encrypt payload'];
    }

    // --- Send via cURL ---
    $headers = [
        'Content-Type: application/octet-stream',
        'Content-Encoding: aes128gcm',
        'Content-Length: ' . strlen($encrypted['cipherText']),
        'TTL: 2419200',
        'Authorization: vapid t=' . $jwt . ', k=' . base64url_encode($vapidPublicKey),
    ];

    $ch = curl_init($endpoint);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $encrypted['cipherText']);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 15);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);  // Always verify SSL in production

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);

    if ($error) {
        return ['success' => false, 'status' => 0, 'reason' => 'cURL error: ' . $error];
    }

    $success = ($httpCode >= 200 && $httpCode < 300);
    return ['success' => $success, 'status' => $httpCode, 'reason' => $response];
}

/**
 * Send push notifications to ALL subscriptions for a given student.
 */
function sendPushToStudent($conn, $studentId, $title, $body, $url = null) {
    $vapidKeys = getVAPIDKeys($conn);

    if (empty($vapidKeys['vapid_public_key']) || empty($vapidKeys['vapid_private_pem'])) {
        return; // VAPID not configured yet
    }

    $base = rtrim(getenv('APP_BASE_URL') ?: '', '/');
    $payload = json_encode([
        'title' => $title,
        'body'  => $body,
        'icon'  => $base . '/rectem-logo.png',
        'badge' => $base . '/rectem-logo.png',
        'url'   => $url ?? $base . '/student/notifications.php',
        'timestamp' => time(),
    ]);

    $stmt = $conn->prepare("SELECT * FROM push_subscriptions WHERE student_id = ?");
    $stmt->bind_param("i", $studentId);
    $stmt->execute();
    $result = $stmt->get_result();

    while ($sub = $result->fetch_assoc()) {
        $pushResult = sendPushNotification($sub, $payload, $vapidKeys);

        // If subscription is expired or invalid (410 Gone or 404), remove it
        if (in_array($pushResult['status'], [404, 410])) {
            $delStmt = $conn->prepare("DELETE FROM push_subscriptions WHERE id = ?");
            $delStmt->bind_param("i", $sub['id']);
            $delStmt->execute();
        }
    }
}

/**
 * Send push to ALL students (broadcast).
 */
function sendPushToAllStudents($conn, $title, $body, $url = null) {
    $vapidKeys = getVAPIDKeys($conn);

    if (empty($vapidKeys['vapid_public_key']) || empty($vapidKeys['vapid_private_pem'])) {
        return;
    }

    $base = rtrim(getenv('APP_BASE_URL') ?: '', '/');
    $payload = json_encode([
        'title' => $title,
        'body'  => $body,
        'icon'  => $base . '/rectem-logo.png',
        'badge' => $base . '/rectem-logo.png',
        'url'   => $url ?? $base . '/student/notifications.php',
        'timestamp' => time(),
    ]);

    $result = $conn->query("SELECT * FROM push_subscriptions");

    while ($sub = $result->fetch_assoc()) {
        $pushResult = sendPushNotification($sub, $payload, $vapidKeys);

        if (in_array($pushResult['status'], [404, 410])) {
            $delStmt = $conn->prepare("DELETE FROM push_subscriptions WHERE id = ?");
            $delStmt->bind_param("i", $sub['id']);
            $delStmt->execute();
        }
    }
}

// ====================================================================
// CRYPTO HELPERS
// ====================================================================

/**
 * Create a VAPID JWT (ES256).
 */
function createVAPIDJWT($audience, $subject, $privatePEM) {
    $header = base64url_encode(json_encode(['typ' => 'JWT', 'alg' => 'ES256']));

    $payload = base64url_encode(json_encode([
        'aud' => $audience,
        'exp' => time() + 43200, // 12 hours
        'sub' => $subject,
    ]));

    $signingInput = $header . '.' . $payload;

    $privateKey = openssl_pkey_get_private($privatePEM);
    if (!$privateKey) {
        return false;
    }

    $signature = '';
    if (!openssl_sign($signingInput, $signature, $privateKey, OPENSSL_ALGO_SHA256)) {
        return false;
    }

    // Convert DER signature to raw r||s (64 bytes)
    $rawSignature = derToRaw($signature);
    if (!$rawSignature) {
        return false;
    }

    return $signingInput . '.' . base64url_encode($rawSignature);
}

/**
 * Encrypt payload using Web Push encryption (RFC 8291 aes128gcm).
 */
function encryptPayload($payload, $userPublicKey, $userAuthToken) {
    // Generate a local ECDH key pair
    $config = getOpenSSLConfig();
    $localKey = openssl_pkey_new(array_merge([
        'curve_name'       => 'prime256v1',
        'private_key_type' => OPENSSL_KEYTYPE_EC,
    ], $config));

    if (!$localKey) {
        return false;
    }

    $localDetails = openssl_pkey_get_details($localKey);

    // Local public key (uncompressed)
    $localX = str_pad($localDetails['ec']['x'], 32, "\0", STR_PAD_LEFT);
    $localY = str_pad($localDetails['ec']['y'], 32, "\0", STR_PAD_LEFT);
    $localPublicKey = "\x04" . $localX . $localY;

    // Compute shared secret using ECDH
    $sharedSecret = computeECDHSharedSecret($localKey, $userPublicKey);
    if (!$sharedSecret) {
        return false;
    }

    // Generate random salt (16 bytes)
    $salt = random_bytes(16);

    // --- Key derivation (RFC 8291 Section 3.4) ---

    // PRK for auth: HKDF-Extract(authToken, sharedSecret)
    $authInfo = "WebPush: info\x00" . $userPublicKey . $localPublicKey;
    $prkAuth = hash_hmac('sha256', $sharedSecret, $userAuthToken, true);

    // IKM = HKDF-Expand(prkAuth, authInfo, 32)
    $ikm = hkdfExpand($prkAuth, $authInfo, 32);

    // PRK = HKDF-Extract(salt, IKM)
    $prk = hash_hmac('sha256', $ikm, $salt, true);

    // Content Encryption Key (CEK): HKDF-Expand(PRK, "Content-Encoding: aes128gcm\0", 16)
    $cekInfo = "Content-Encoding: aes128gcm\x00";
    $cek = hkdfExpand($prk, $cekInfo, 16);

    // Nonce: HKDF-Expand(PRK, "Content-Encoding: nonce\0", 12)
    $nonceInfo = "Content-Encoding: nonce\x00";
    $nonce = hkdfExpand($prk, $nonceInfo, 12);

    // --- Encrypt with AES-128-GCM ---
    // Pad the payload: add 0x02 delimiter then padding
    $paddedPayload = $payload . "\x02";

    $tag = '';
    $encrypted = openssl_encrypt(
        $paddedPayload,
        'aes-128-gcm',
        $cek,
        OPENSSL_RAW_DATA,
        $nonce,
        $tag,
        '',
        16
    );

    if ($encrypted === false) {
        return false;
    }

    // Build aes128gcm content coding header:
    // salt (16) + rs (4 bytes big-endian) + idlen (1) + keyid (65 bytes = local public key)
    $rs = 4096;
    $header = $salt . pack('N', $rs) . chr(65) . $localPublicKey;

    $cipherText = $header . $encrypted . $tag;

    return ['cipherText' => $cipherText];
}

/**
 * Compute ECDH shared secret between local private key and remote public key.
 */
function computeECDHSharedSecret($localPrivateKey, $remotePublicKeyBin) {
    // Extract local private key 'd' value
    $localDetails = openssl_pkey_get_details($localPrivateKey);
    $d = $localDetails['ec']['d'];
    $d = str_pad($d, 32, "\0", STR_PAD_LEFT);

    // We need to use openssl to derive the shared secret
    // Create a temporary PEM for the remote public key
    $remotePEM = createECPublicKeyPEM($remotePublicKeyBin);
    if (!$remotePEM) {
        return false;
    }

    $remoteKey = openssl_pkey_get_public($remotePEM);
    if (!$remoteKey) {
        return false;
    }

    // Use openssl_pkey_derive for ECDH (PHP 7.3+)
    $sharedSecret = openssl_pkey_derive($remoteKey, $localPrivateKey, 32);
    if ($sharedSecret === false) {
        return false;
    }

    return $sharedSecret;
}

/**
 * Create a PEM-encoded EC public key from uncompressed point bytes.
 */
function createECPublicKeyPEM($publicKeyBin) {
    if (strlen($publicKeyBin) !== 65 || $publicKeyBin[0] !== "\x04") {
        return false;
    }

    // ASN.1 DER encoding for an EC public key on prime256v1
    // SEQUENCE {
    //   SEQUENCE {
    //     OID 1.2.840.10045.2.1 (EC)
    //     OID 1.2.840.10045.3.1.7 (prime256v1)
    //   }
    //   BIT STRING (public key)
    // }

    $ecOid = "\x06\x07\x2a\x86\x48\xce\x3d\x02\x01";           // OID 1.2.840.10045.2.1
    $curveOid = "\x06\x08\x2a\x86\x48\xce\x3d\x03\x01\x07";     // OID 1.2.840.10045.3.1.7

    $algSeq = "\x30" . chr(strlen($ecOid . $curveOid)) . $ecOid . $curveOid;

    $bitString = "\x03" . chr(strlen($publicKeyBin) + 1) . "\x00" . $publicKeyBin;

    $der = "\x30" . chr(strlen($algSeq . $bitString)) . $algSeq . $bitString;

    $pem = "-----BEGIN PUBLIC KEY-----\n"
         . chunk_split(base64_encode($der), 64, "\n")
         . "-----END PUBLIC KEY-----";

    return $pem;
}

/**
 * HKDF-Expand (RFC 5869)
 */
function hkdfExpand($prk, $info, $length) {
    $t = '';
    $output = '';
    $counter = 1;

    while (strlen($output) < $length) {
        $t = hash_hmac('sha256', $t . $info . chr($counter), $prk, true);
        $output .= $t;
        $counter++;
    }

    return substr($output, 0, $length);
}

/**
 * Convert DER encoded ECDSA signature to raw r||s format (64 bytes).
 */
function derToRaw($der) {
    $offset = 0;

    if (ord($der[$offset++]) !== 0x30) return false;

    // Skip sequence length
    $len = ord($der[$offset++]);
    if ($len & 0x80) {
        $lenBytes = $len & 0x7F;
        $offset += $lenBytes;
    }

    // Read r
    if (ord($der[$offset++]) !== 0x02) return false;
    $rLen = ord($der[$offset++]);
    $r = substr($der, $offset, $rLen);
    $offset += $rLen;

    // Read s
    if (ord($der[$offset++]) !== 0x02) return false;
    $sLen = ord($der[$offset++]);
    $s = substr($der, $offset, $sLen);

    // Trim leading zeros and pad to 32 bytes
    $r = ltrim($r, "\x00");
    $s = ltrim($s, "\x00");
    $r = str_pad($r, 32, "\x00", STR_PAD_LEFT);
    $s = str_pad($s, 32, "\x00", STR_PAD_LEFT);

    return $r . $s;
}

/**
 * Base64url encode.
 */
function base64url_encode($data) {
    return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
}

/**
 * Base64url decode.
 */
function base64url_decode($data) {
    return base64_decode(strtr($data, '-_', '+/') . str_repeat('=', (4 - strlen($data) % 4) % 4));
}
?>
