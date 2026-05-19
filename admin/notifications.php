<?php
session_start();
include("../includes/db.php");
include("../includes/auth_check.php");
include("../includes/web_push.php");
requireAdmin();

$adminId = $_SESSION["user_id"]; $message=""; $messageClass=""; $pushInfo = "";
$adminSql = "SELECT a.*, d.department_name FROM admins a LEFT JOIN departments d ON a.department_id = d.id WHERE a.id = ? LIMIT 1";
$adminStmt = $conn->prepare($adminSql); $adminStmt->bind_param("i", $adminId); $adminStmt->execute(); $adminResult = $adminStmt->get_result();
if (!$adminResult || $adminResult->num_rows === 0) { session_unset(); session_destroy(); header("Location: ../admin-login.php"); exit(); }
$admin = $adminResult->fetch_assoc();
$adminDepartmentId = $admin["department_id"]; $adminDepartmentName = $admin["department_name"] ?? "Unknown Department";
$adminInitial = strtoupper(substr($admin["full_name"], 0, 1));

// Get push subscription count
$subCountResult = $conn->query("SELECT COUNT(*) AS cnt FROM push_subscriptions");
$subCount = (int)$subCountResult->fetch_assoc()['cnt'];

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $title = trim($_POST["title"] ?? "");
    $body = trim($_POST["message"] ?? "");
    $target = trim($_POST["target"] ?? "department");
    if ($title === "" || $body === "") { $message="Please fill in both title and message."; $messageClass="error"; }
    else {
        $pushSent = 0; $pushFailed = 0;
        if ($target === "all") {
            $sql = "INSERT INTO notifications (student_id, title, message, audience, type, sent_via_email, is_read) VALUES (NULL, ?, ?, 'all', 'general', 0, 0)";
            $stmt = $conn->prepare($sql); $stmt->bind_param("ss", $title, $body); $stmt->execute();
            // Send push to all students
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
                    if ($pushResult['success']) { $pushSent++; }
                    else {
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
            $studentSql = "SELECT id FROM students WHERE department_id = ?";
            $studentStmt = $conn->prepare($studentSql); $studentStmt->bind_param("i", $adminDepartmentId); $studentStmt->execute(); $studentResult = $studentStmt->get_result();
            $vapidKeys = getVAPIDKeys($conn);
            while ($student = $studentResult->fetch_assoc()) {
                $sql = "INSERT INTO notifications (student_id, title, message, audience, type, sent_via_email, is_read) VALUES (?, ?, ?, 'student', 'general', 0, 0)";
                $stmt = $conn->prepare($sql); $stmt->bind_param("iss", $student["id"], $title, $body); $stmt->execute();
                // Send push to individual student
                if (!empty($vapidKeys['vapid_public_key']) && !empty($vapidKeys['vapid_private_pem'])) {
                    $payload = json_encode([
                        'title' => $title,
                        'body' => $body,
                        'icon' => BASE_PATH . '/rectem-logo.png',
                        'badge' => BASE_PATH . '/rectem-logo.png',
                        'url' => BASE_PATH . '/student/notifications.php',
                        'timestamp' => time(),
                    ]);
                    $subStmt = $conn->prepare("SELECT * FROM push_subscriptions WHERE student_id = ?");
                    $subStmt->bind_param("i", $student["id"]);
                    $subStmt->execute();
                    $subResult = $subStmt->get_result();
                    while ($sub = $subResult->fetch_assoc()) {
                        $pushResult = sendPushNotification($sub, $payload, $vapidKeys);
                        if ($pushResult['success']) { $pushSent++; }
                        else {
                            $pushFailed++;
                            if (in_array($pushResult['status'], [404, 410])) {
                                $delStmt = $conn->prepare("DELETE FROM push_subscriptions WHERE id = ?");
                                $delStmt->bind_param("i", $sub['id']);
                                $delStmt->execute();
                            }
                        }
                    }
                }
            }
        }
        $message = "Notification sent successfully!"; $messageClass = "success";
        if ($pushSent > 0) {
            $pushInfo = "📱 Push delivered to $pushSent device" . ($pushSent > 1 ? 's' : '') . ".";
        } elseif ($subCount === 0) {
            $pushInfo = "ℹ️ No students have enabled push notifications yet.";
        } else {
            $pushInfo = "⚠️ Push delivery attempted but $pushFailed device(s) failed.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en"><head><meta charset="UTF-8" /><meta name="viewport" content="width=device-width, initial-scale=1.0" />
<title>Admin Notifications</title><link rel="stylesheet" href="../style.css" /></head>
<body class="portal-bg"><div class="bg-watermark"></div><div class="dashboard-shell">
<aside class="student-sidebar" id="studentSidebar">
<div class="sidebar-brand"><img src="../rectem-logo.png" class="sidebar-brand-logo" alt="RECTEM Logo"><div class="sidebar-brand-text"><h3>RECTEM</h3><p>Admin Portal</p></div></div>
<nav class="sidebar-nav">
<a href="dashboard.php" class="sidebar-link">Dashboard</a>
<a href="view-students.php" class="sidebar-link">View Students</a>
<a href="upload-results.php" class="sidebar-link">Upload Results</a>
<a href="result-preview.php" class="sidebar-link">Result Preview Sheet</a>
<a href="notifications.php" class="sidebar-link active-link">Send Notification</a>
<a href="change-password.php" class="sidebar-link">Change Password</a>
<a href="../logout.php" class="sidebar-link logout-link">Logout</a>
</nav></aside>
<div class="sidebar-overlay" id="sidebarOverlay"></div>
<main class="student-main">
<header class="student-topbar"><button class="topbar-menu-btn" id="menuToggleBtn" type="button">☰</button>
<div class="topbar-welcome"><h2>Send Notification</h2><p>Department: <?php echo htmlspecialchars($adminDepartmentName); ?></p></div>
<div class="topbar-actions"><div class="topbar-profile-img" style="display:grid;place-items:center;background:#e2e8f0;color:#334155;font-weight:800;"><?php echo $adminInitial; ?></div></div></header>

<!-- Push status indicator -->
<div style="background:<?php echo $subCount > 0 ? 'rgba(22,163,74,0.08)' : 'rgba(245,158,11,0.08)'; ?>; border:1px solid <?php echo $subCount > 0 ? 'rgba(22,163,74,0.2)' : 'rgba(245,158,11,0.2)'; ?>; border-radius:14px; padding:14px 18px; margin-bottom:18px; display:flex; align-items:center; gap:12px;">
  <span style="font-size:1.5rem;"><?php echo $subCount > 0 ? '📱' : '📵'; ?></span>
  <div>
    <strong style="font-size:0.95rem; color:var(--text);"><?php echo $subCount; ?> device<?php echo $subCount !== 1 ? 's' : ''; ?> subscribed to push notifications</strong>
    <p style="margin:4px 0 0; font-size:0.85rem; color:var(--muted);">
      <?php echo $subCount > 0 ? 'Notifications will be delivered directly to student devices.' : 'Students need to enable notifications on their browser to receive push alerts.'; ?>
    </p>
  </div>
</div>

<section class="registration-card">
<h3 class="admin-section-title">Send Portal Notice</h3><p class="admin-section-subtitle">Send a message to your department or all students. Push notifications will be sent to subscribed devices.</p>
<form method="POST" id="notificationForm">
<div class="form-group"><label for="title">Notification Title</label><input type="text" id="title" name="title" placeholder="Enter title" required></div>
<div class="form-group"><label for="message">Notification Message</label><textarea id="message" name="message" placeholder="Enter your notification message..." required style="width:100%;min-height:100px;border:1px solid var(--line);border-radius:var(--radius-sm);background:#fff;padding:14px;font-size:0.96rem;color:var(--text);outline:none;resize:vertical;font-family:inherit;transition:0.25s ease;"></textarea></div>
<div class="semester-select-wrap" style="max-width:100%;"><label for="target">Target</label><select id="target" name="target"><option value="department">My Department Students</option><option value="all">All Students</option></select></div>
<?php if ($message): ?>
<p class="course-message <?php echo htmlspecialchars($messageClass); ?>" style="margin-top:14px;"><?php echo htmlspecialchars($message); ?></p>
<?php endif; ?>
<?php if ($pushInfo): ?>
<p style="margin-top:8px; font-size:0.9rem; color:var(--muted);"><?php echo $pushInfo; ?></p>
<?php endif; ?>
<button type="submit" class="submit-registration-btn" id="sendBtn">
  <span id="sendBtnText">📤 Send Notification</span>
</button>
</form></section>
</main></div>
<footer class="portal-footer">© 2026 RECTEM Student Portal<br>Developed by Adebowale Adeyinka Josiah</footer>

  <script>
    const menuToggleBtn = document.getElementById("menuToggleBtn");
    const studentSidebar = document.getElementById("studentSidebar");
    const sidebarOverlay = document.getElementById("sidebarOverlay");
    if (menuToggleBtn && studentSidebar && sidebarOverlay) {
      menuToggleBtn.onclick = function () {
        studentSidebar.classList.toggle("show-sidebar");
        sidebarOverlay.classList.toggle("show-overlay");
      };
      sidebarOverlay.onclick = function () {
        studentSidebar.classList.remove("show-sidebar");
        sidebarOverlay.classList.remove("show-overlay");
      };
    }

    // Add textarea focus styling
    const textarea = document.getElementById('message');
    if (textarea) {
      textarea.addEventListener('focus', function() {
        this.style.borderColor = 'var(--primary)';
        this.style.boxShadow = '0 0 0 4px var(--primary-soft)';
      });
      textarea.addEventListener('blur', function() {
        this.style.borderColor = 'var(--line)';
        this.style.boxShadow = 'none';
      });
    }
  </script>

</body></html>
