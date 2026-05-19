<?php
/**
 * Push Notification Diagnostic & Test Page
 * Open this in your browser to test if push notifications work on YOUR device.
 * URL: http://localhost/rectem_portal/test-push.php
 */
include("includes/db.php");
include("includes/web_push.php");

// Check VAPID keys
$vapidKeys = getVAPIDKeys($conn);
$vapidValid = !empty($vapidKeys['vapid_public_key']) && !empty($vapidKeys['vapid_private_pem']);
$pemValid = false;
if ($vapidValid) {
    $pem = base64_decode($vapidKeys['vapid_private_pem']);
    $pemValid = openssl_pkey_get_private($pem) !== false;
}

// Count subscriptions
$subResult = $conn->query("SELECT COUNT(*) AS cnt FROM push_subscriptions");
$subCount = (int)$subResult->fetch_assoc()['cnt'];

// Handle test send
$sendResult = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_test'])) {
    $title = trim($_POST['title'] ?? 'RECTEM Test');
    $body = trim($_POST['body'] ?? 'Push notifications are working!');
    
    $payload = json_encode([
        "title" => $title,
        "body" => $body,
        "icon" => "/rectem_portal/rectem-logo.png",
        "url" => "/rectem_portal/student/notifications.php",
        "timestamp" => time()
    ]);
    
    $result = $conn->query("SELECT * FROM push_subscriptions ORDER BY id DESC");
    $sendResult = ['sent' => 0, 'failed' => 0, 'details' => []];
    
    while ($sub = $result->fetch_assoc()) {
        $pushResult = sendPushNotification($sub, $payload, $vapidKeys);
        if ($pushResult['success']) {
            $sendResult['sent']++;
            $sendResult['details'][] = ['student' => $sub['student_id'], 'status' => 'sent', 'code' => $pushResult['status']];
        } else {
            $sendResult['failed']++;
            $sendResult['details'][] = ['student' => $sub['student_id'], 'status' => 'failed', 'code' => $pushResult['status'], 'reason' => $pushResult['reason']];
            if (in_array($pushResult['status'], [404, 410])) {
                $conn->query("DELETE FROM push_subscriptions WHERE id = " . (int)$sub['id']);
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Push Notification Test</title>
<link rel="stylesheet" href="style.css">
<link rel="manifest" href="manifest.json">
<style>
.diag-page { max-width: 700px; margin: 40px auto; padding: 0 18px; }
.diag-card { background: #fff; border-radius: 16px; padding: 20px; margin-bottom: 18px; box-shadow: 0 4px 16px rgba(0,0,0,0.06); }
.diag-card h3 { margin: 0 0 12px; font-size: 1.05rem; }
.check-item { display: flex; align-items: center; gap: 10px; padding: 8px 0; border-bottom: 1px solid #f1f5f9; font-size: 0.95rem; }
.check-item:last-child { border-bottom: none; }
.check-ok { color: #16a34a; font-weight: bold; }
.check-fail { color: #dc2626; font-weight: bold; }
.check-warn { color: #d97706; font-weight: bold; }
.test-btn { display: inline-block; padding: 12px 24px; background: var(--primary); color: #fff; border: none; border-radius: 10px; cursor: pointer; font-weight: 800; font-size: 0.95rem; transition: 0.2s; }
.test-btn:hover { background: var(--primary-dark); transform: translateY(-1px); }
.test-btn:disabled { background: #94a3b8; cursor: not-allowed; }
#browser-checks { min-height: 50px; }
input[type="text"] { width: 100%; padding: 10px 14px; border: 1px solid var(--line); border-radius: 10px; font-size: 0.95rem; margin: 4px 0 12px; }
.result-box { padding: 14px; border-radius: 12px; margin-top: 14px; font-size: 0.95rem; }
.result-success { background: rgba(22,163,74,0.08); border: 1px solid rgba(22,163,74,0.2); color: #16a34a; }
.result-fail { background: rgba(220,38,38,0.08); border: 1px solid rgba(220,38,38,0.2); color: #dc2626; }
</style>
</head>
<body class="portal-bg">
<div class="diag-page">
<h1 style="margin-bottom: 8px;">🔔 Push Notification Test</h1>
<p style="color:var(--muted); margin-bottom: 20px;">Diagnose and test push notifications on this device.</p>

<!-- Server Checks -->
<div class="diag-card">
  <h3>🖥️ Server Status</h3>
  <div class="check-item">
    <span><?= $vapidValid ? '✅' : '❌' ?></span>
    <span>VAPID Keys: <span class="<?= $vapidValid ? 'check-ok' : 'check-fail' ?>"><?= $vapidValid ? 'Configured' : 'Missing' ?></span></span>
  </div>
  <div class="check-item">
    <span><?= $pemValid ? '✅' : '❌' ?></span>
    <span>PEM Key: <span class="<?= $pemValid ? 'check-ok' : 'check-fail' ?>"><?= $pemValid ? 'Valid' : 'Invalid' ?></span></span>
  </div>
  <div class="check-item">
    <span><?= $subCount > 0 ? '✅' : '⚠️' ?></span>
    <span>Push Subscriptions: <span class="<?= $subCount > 0 ? 'check-ok' : 'check-warn' ?>"><?= $subCount ?> device<?= $subCount !== 1 ? 's' : '' ?></span></span>
  </div>
</div>

<!-- Browser Checks (JS) -->
<div class="diag-card">
  <h3>🌐 Browser Status</h3>
  <div id="browser-checks"><p style="color:var(--muted);">Running checks...</p></div>
</div>

<!-- Subscribe Button -->
<div class="diag-card" id="subscribe-card" style="display:none;">
  <h3>📱 Enable Push on This Device</h3>
  <p style="color:var(--muted); margin-bottom: 12px;">Click below to subscribe this browser to push notifications.</p>
  <button class="test-btn" id="subscribeBtn" onclick="doSubscribe()">🔔 Enable Push Notifications</button>
  <div id="subscribe-result"></div>
</div>

<!-- Test Local Notification -->
<div class="diag-card">
  <h3>🧪 Test Local Notification (Browser Only)</h3>
  <p style="color:var(--muted); margin-bottom: 12px;">This sends a notification directly from your browser — no server involved. If this doesn't show, your browser/OS is blocking notifications.</p>
  <button class="test-btn" id="localTestBtn" onclick="testLocalNotification()">Send Local Test</button>
  <div id="local-result"></div>
</div>

<!-- Send Server Push -->
<div class="diag-card">
  <h3>🚀 Send Server Push</h3>
  <p style="color:var(--muted); margin-bottom: 12px;">Send a real push notification from the server to all subscribed devices.</p>
  <form method="POST">
    <label><strong>Title:</strong></label>
    <input type="text" name="title" value="RECTEM Test Notification" required>
    <label><strong>Message:</strong></label>
    <input type="text" name="body" value="If you see this on your device, push is working! 🎉" required>
    <button type="submit" name="send_test" class="test-btn" <?= $subCount === 0 ? 'disabled title="No subscriptions"' : '' ?>>📤 Send Push to All Devices</button>
  </form>
  <?php if ($sendResult): ?>
  <div class="result-box <?= $sendResult['sent'] > 0 ? 'result-success' : 'result-fail' ?>">
    <strong>Results:</strong> <?= $sendResult['sent'] ?> sent, <?= $sendResult['failed'] ?> failed<br>
    <?php foreach ($sendResult['details'] as $d): ?>
      Student #<?= $d['student'] ?>: <?= $d['status'] === 'sent' ? '✅ HTTP ' . $d['code'] : '❌ HTTP ' . $d['code'] . ' — ' . ($d['reason'] ?? 'unknown') ?><br>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>
</div>

<!-- Troubleshooting -->
<div class="diag-card">
  <h3>🔧 Not Receiving Notifications?</h3>
  <div style="font-size: 0.9rem; color: var(--muted); line-height: 1.7;">
    <p><strong>Windows:</strong></p>
    <ol>
      <li>Open <strong>Settings → System → Notifications</strong></li>
      <li>Make sure <strong>Notifications</strong> is turned ON</li>
      <li>Scroll down and find your browser (Chrome/Edge/Firefox)</li>
      <li>Make sure it's set to <strong>ON</strong></li>
      <li>Also check: <strong>Focus Assist</strong> is not blocking notifications</li>
    </ol>
    <p><strong>Chrome:</strong></p>
    <ol>
      <li>Click the 🔒 lock icon in the address bar</li>
      <li>Find <strong>Notifications</strong> → set to <strong>Allow</strong></li>
      <li>Or go to <code>chrome://settings/content/notifications</code></li>
    </ol>
    <p><strong>Phone:</strong></p>
    <ol>
      <li>Open the site in Chrome (not in-app browsers)</li>
      <li>Accept the notification permission when prompted</li>
      <li>Check phone Settings → Apps → Chrome → Notifications → ON</li>
    </ol>
  </div>
</div>

</div>

<script>
const BASE_URL = '/rectem_portal';

async function runBrowserChecks() {
  const container = document.getElementById('browser-checks');
  const checks = [];

  // 1. Service Worker support
  const swSupported = 'serviceWorker' in navigator;
  checks.push({ ok: swSupported, label: 'Service Workers', value: swSupported ? 'Supported' : 'Not supported' });

  // 2. Push API support
  const pushSupported = 'PushManager' in window;
  checks.push({ ok: pushSupported, label: 'Push API', value: pushSupported ? 'Supported' : 'Not supported' });

  // 3. Notification API support
  const notifSupported = 'Notification' in window;
  checks.push({ ok: notifSupported, label: 'Notification API', value: notifSupported ? 'Supported' : 'Not supported' });

  // 4. Notification permission
  if (notifSupported) {
    const perm = Notification.permission;
    checks.push({ 
      ok: perm === 'granted', 
      warn: perm === 'default',
      label: 'Notification Permission', 
      value: perm === 'granted' ? 'Granted ✓' : perm === 'denied' ? 'DENIED (blocked)' : 'Not yet asked' 
    });
  }

  // 5. Service Worker registration
  let swReg = null;
  if (swSupported) {
    try {
      swReg = await navigator.serviceWorker.register(BASE_URL + '/sw.js', { scope: BASE_URL + '/' });
      await navigator.serviceWorker.ready;
      checks.push({ ok: true, label: 'Service Worker', value: 'Registered & Active' });
    } catch (e) {
      checks.push({ ok: false, label: 'Service Worker', value: 'Failed: ' + e.message });
    }
  }

  // 6. Push subscription
  if (swReg && pushSupported) {
    try {
      const sub = await swReg.pushManager.getSubscription();
      if (sub) {
        checks.push({ ok: true, label: 'Push Subscription', value: 'Active ✓' });
        document.getElementById('subscribe-card').style.display = 'none';
      } else {
        checks.push({ ok: false, warn: true, label: 'Push Subscription', value: 'Not subscribed' });
        document.getElementById('subscribe-card').style.display = 'block';
      }
    } catch (e) {
      checks.push({ ok: false, label: 'Push Subscription', value: 'Error: ' + e.message });
      document.getElementById('subscribe-card').style.display = 'block';
    }
  }

  // Render
  container.innerHTML = checks.map(c => {
    const icon = c.ok ? '✅' : (c.warn ? '⚠️' : '❌');
    const cls = c.ok ? 'check-ok' : (c.warn ? 'check-warn' : 'check-fail');
    return `<div class="check-item"><span>${icon}</span><span>${c.label}: <span class="${cls}">${c.value}</span></span></div>`;
  }).join('');
}

async function doSubscribe() {
  const btn = document.getElementById('subscribeBtn');
  const result = document.getElementById('subscribe-result');
  btn.disabled = true;
  btn.textContent = 'Subscribing...';

  try {
    const permission = await Notification.requestPermission();
    if (permission !== 'granted') {
      result.innerHTML = '<div class="result-box result-fail">Permission denied. Please allow notifications in your browser settings.</div>';
      btn.disabled = false;
      btn.textContent = '🔔 Enable Push Notifications';
      return;
    }

    const swReg = await navigator.serviceWorker.ready;
    
    // Get VAPID key
    const resp = await fetch(BASE_URL + '/api/push-subscribe.php');
    const data = await resp.json();
    
    if (!data.publicKey) {
      result.innerHTML = '<div class="result-box result-fail">VAPID keys not configured on server.</div>';
      return;
    }

    // Unsubscribe old
    const existingSub = await swReg.pushManager.getSubscription();
    if (existingSub) await existingSub.unsubscribe();

    // Subscribe
    const sub = await swReg.pushManager.subscribe({
      userVisibleOnly: true,
      applicationServerKey: urlBase64ToUint8Array(data.publicKey)
    });

    // Save to server
    const subJSON = sub.toJSON();
    const saveResp = await fetch(BASE_URL + '/api/push-subscribe.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ action: 'subscribe', endpoint: subJSON.endpoint, keys: subJSON.keys })
    });
    const saveData = await saveResp.json();
    
    if (saveData.success) {
      result.innerHTML = '<div class="result-box result-success">✅ Subscribed successfully! Refresh this page to see updated status.</div>';
      setTimeout(() => location.reload(), 2000);
    } else {
      result.innerHTML = '<div class="result-box result-fail">Server error: ' + (saveData.message || 'Unknown') + '</div>';
    }
  } catch (e) {
    result.innerHTML = '<div class="result-box result-fail">Error: ' + e.message + '</div>';
  }
  
  btn.disabled = false;
  btn.textContent = '🔔 Enable Push Notifications';
}

function testLocalNotification() {
  const result = document.getElementById('local-result');
  
  if (!('Notification' in window)) {
    result.innerHTML = '<div class="result-box result-fail">Notifications not supported in this browser.</div>';
    return;
  }

  if (Notification.permission === 'denied') {
    result.innerHTML = '<div class="result-box result-fail">Notifications are BLOCKED. Go to browser settings to unblock.</div>';
    return;
  }

  if (Notification.permission === 'default') {
    Notification.requestPermission().then(perm => {
      if (perm === 'granted') testLocalNotification();
      else result.innerHTML = '<div class="result-box result-fail">Permission denied.</div>';
    });
    return;
  }

  // Try service worker notification first (more reliable)
  navigator.serviceWorker.ready.then(reg => {
    reg.showNotification('RECTEM Portal Test', {
      body: 'If you can see this, notifications work on your device! 🎉 ' + new Date().toLocaleTimeString(),
      icon: BASE_URL + '/rectem-logo.png',
      badge: BASE_URL + '/rectem-logo.png',
      vibrate: [200, 100, 200],
      requireInteraction: true,
      tag: 'test-' + Date.now()
    });
    result.innerHTML = '<div class="result-box result-success">✅ Notification sent! Check your notification tray.</div>';
  }).catch(() => {
    // Fallback to basic Notification API
    new Notification('RECTEM Portal Test', {
      body: 'Notifications work! ' + new Date().toLocaleTimeString(),
      icon: BASE_URL + '/rectem-logo.png'
    });
    result.innerHTML = '<div class="result-box result-success">✅ Notification sent (basic API).</div>';
  });
}

function urlBase64ToUint8Array(base64String) {
  const padding = '='.repeat((4 - (base64String.length % 4)) % 4);
  const base64 = (base64String + padding).replace(/-/g, '+').replace(/_/g, '/');
  const rawData = window.atob(base64);
  const outputArray = new Uint8Array(rawData.length);
  for (let i = 0; i < rawData.length; i++) outputArray[i] = rawData.charCodeAt(i);
  return outputArray;
}

// Run checks on load
runBrowserChecks();
</script>
</body>
</html>
