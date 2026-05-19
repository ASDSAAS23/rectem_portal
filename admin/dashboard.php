<?php
session_start();
include("../includes/db.php");
include("../includes/auth_check.php");
include("../includes/web_push.php");
requireAdmin();

$adminId = $_SESSION["user_id"];
$message = "";
$messageClass = "";

$adminSql = "SELECT a.*, d.department_name FROM admins a LEFT JOIN departments d ON a.department_id = d.id WHERE a.id = ? LIMIT 1";
$adminStmt = $conn->prepare($adminSql);
$adminStmt->bind_param("i", $adminId);
$adminStmt->execute();
$adminResult = $adminStmt->get_result();
if (!$adminResult || $adminResult->num_rows === 0) { session_unset(); session_destroy(); header("Location: ../admin-login.php"); exit(); }
$admin = $adminResult->fetch_assoc();
$adminDepartmentId = $admin["department_id"];
$adminDepartmentName = $admin["department_name"] ?? "Unknown Department";
$adminInitial = strtoupper(substr($admin["full_name"], 0, 1));

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $action = null; $title = ""; $body = ""; $message = ""; $messageClass = "success";
    if (isset($_POST["open_registration"])) { $action="registration_opened"; $title="📝 Course Registration Now Open!"; $body="Course registration is now open for all students. Log in to your portal to register your courses for this semester before the deadline."; $message="Course registration opened successfully."; $settingKey='course_registration_status'; $settingValue='open';}
    if (isset($_POST["close_registration"])) { $action="registration_closed"; $title="🚫 Course Registration Closed"; $body="Course registration has been closed. If you haven't registered your courses, please contact your department."; $message="Course registration closed successfully."; $settingKey='course_registration_status'; $settingValue='closed';}
    if (isset($_POST["open_consultation"])) { $action="consultation_opened"; $title="💬 Consultation Period Open"; $body="The consultation period is now open. You can visit your department office for academic consultation and guidance."; $message="Consultation opened successfully."; $settingKey='consultation_status'; $settingValue='open';}
    if (isset($_POST["close_consultation"])) { $action="consultation_closed"; $title="📋 Consultation Period Ended"; $body="The consultation period has ended. Thank you to all students who participated."; $message="Consultation closed successfully."; $settingKey='consultation_status'; $settingValue='closed';}
    if ($action) {
        $stmt = $conn->prepare("UPDATE portal_settings SET setting_value = ? WHERE setting_key = ?");
        $stmt->bind_param("ss", $settingValue, $settingKey);
        $stmt->execute();

        $notifSql = "INSERT INTO notifications (student_id, title, message, audience, type, sent_via_email, is_read) VALUES (NULL, ?, ?, 'all', ?, 0, 0)";
        $notifStmt = $conn->prepare($notifSql);
        $type = str_contains($action, 'registration') ? 'registration' : 'consultation';
        $notifStmt->bind_param("sss", $title, $body, $type);
        $notifStmt->execute();

        // Send push notification to all students with target URL
        $targetUrl = str_contains($action, 'registration') ? BASE_PATH . '/student/course-registration.php' : BASE_PATH . '/student/notifications.php';
        sendPushToAllStudents($conn, $title, $body, $targetUrl);

        $logSql = "INSERT INTO audit_logs (actor_role, actor_id, action_type, action_details) VALUES ('admin', ?, ?, ?)";
        $logStmt = $conn->prepare($logSql);
        $logStmt->bind_param("iss", $adminId, $action, $message);
        $logStmt->execute();
    }
}
$studentCount = 0; $resultCount = 0;
$studentCountStmt = $conn->prepare("SELECT COUNT(*) AS total_students FROM students WHERE department_id = ?");
$studentCountStmt->bind_param("i", $adminDepartmentId); $studentCountStmt->execute();
$studentCountResult = $studentCountStmt->get_result(); if ($studentCountResult) $studentCount=(int)$studentCountResult->fetch_assoc()["total_students"];
$resultCountStmt = $conn->prepare("SELECT COUNT(*) AS total_results FROM results WHERE department_id = ?");
$resultCountStmt->bind_param("i", $adminDepartmentId); $resultCountStmt->execute();
$resultCountResult = $resultCountStmt->get_result(); if ($resultCountResult) $resultCount=(int)$resultCountResult->fetch_assoc()["total_results"];
$registrationStatus='closed'; $consultationStatus='closed';
$settingsResult = $conn->query("SELECT setting_key, setting_value FROM portal_settings");
if ($settingsResult) { while($row=$settingsResult->fetch_assoc()){ if($row['setting_key']==='course_registration_status') $registrationStatus=$row['setting_value']; if($row['setting_key']==='consultation_status') $consultationStatus=$row['setting_value'];}}
?>
<!DOCTYPE html>
<html lang="en"><head><meta charset="UTF-8" /><meta name="viewport" content="width=device-width, initial-scale=1.0" />
<title>Admin Dashboard</title><link rel="stylesheet" href="../style.css" /></head>
<body class="portal-bg"><div class="bg-watermark"></div>
<div class="dashboard-shell">
<aside class="student-sidebar" id="studentSidebar">
<div class="sidebar-brand"><img src="../rectem-logo.png" class="sidebar-brand-logo" alt="RECTEM Logo"><div class="sidebar-brand-text"><h3>RECTEM</h3><p>Admin Portal</p></div></div>
<nav class="sidebar-nav">
<a href="dashboard.php" class="sidebar-link active-link">Dashboard</a>
<a href="view-students.php" class="sidebar-link">View Students</a>
<a href="upload-results.php" class="sidebar-link">Upload Results</a>
<a href="result-preview.php" class="sidebar-link">Result Preview Sheet</a>
<a href="notifications.php" class="sidebar-link">Send Notification</a>
<a href="change-password.php" class="sidebar-link">Change Password</a>
<a href="../logout.php" class="sidebar-link logout-link">Logout</a>
</nav></aside>
<div class="sidebar-overlay" id="sidebarOverlay"></div>
<main class="student-main">
<header class="student-topbar">
<button class="topbar-menu-btn" id="menuToggleBtn" type="button">☰</button>
<div class="topbar-welcome"><h2>Welcome <span><?php echo htmlspecialchars(explode(" ", $admin["full_name"])[0]); ?></span></h2><p>Department: <?php echo htmlspecialchars($adminDepartmentName); ?></p></div>
<div class="topbar-actions"><div class="topbar-profile-img" style="display:grid;place-items:center;background:#e2e8f0;color:#334155;font-weight:800;"><?php echo $adminInitial; ?></div></div>
</header>
<?php if ($message !== ""): ?><p class="course-message <?php echo htmlspecialchars($messageClass); ?>" style="margin-bottom:18px;"><?php echo htmlspecialchars($message); ?></p><?php endif; ?>
<section class="page-title-card admin-hero-card"><div class="admin-hero-text"><h3>Department Control Panel</h3><p>Manage students, upload results, control registration, and handle consultation settings for your department.</p></div></section>
<section class="admin-summary-grid">
<div class="admin-summary-card"><span class="admin-summary-label">Department</span><span class="admin-summary-value"><?php echo htmlspecialchars($adminDepartmentName); ?></span></div>
<div class="admin-summary-card"><span class="admin-summary-label">Total Students</span><span class="admin-summary-value"><?php echo $studentCount; ?></span></div>
<div class="admin-summary-card"><span class="admin-summary-label">Results Uploaded</span><span class="admin-summary-value"><?php echo $resultCount; ?></span></div>
</section>
<section class="registration-card">
<h3 class="admin-section-title">Quick Actions</h3><p class="admin-section-subtitle">Move quickly to the main tools for your department.</p>
<div class="quick-access-grid">
<a href="view-students.php" class="quick-card quick-card-link"><h4>View Students</h4><p>Filter and view students by level in your department.</p></a>
<a href="upload-results.php" class="quick-card quick-card-link"><h4>Upload Results</h4><p>Upload marks and prepare result processing.</p></a>
<a href="notifications.php" class="quick-card quick-card-link"><h4>Send Notification</h4><p>Send notices to students.</p></a>
<a href="change-password.php" class="quick-card quick-card-link"><h4>Change Password</h4><p>Update your department admin password.</p></a>
</div></section>
<section class="registration-card">
<h3 class="admin-section-title">Portal Controls</h3><p class="admin-section-subtitle">Use these controls to manage registration and consultation.</p>
<form method="POST">
<div class="admin-action-grid">
<button class="admin-action-btn open-btn" type="submit" name="open_registration"><span class="admin-btn-title">Open Course Registration</span><span class="admin-btn-subtitle">Allow students to register courses.</span></button>
<button class="admin-action-btn close-btn" type="submit" name="close_registration"><span class="admin-btn-title">Close Course Registration</span><span class="admin-btn-subtitle">Stop further course registration.</span></button>
<button class="admin-action-btn open-btn" type="submit" name="open_consultation"><span class="admin-btn-title">Open Consultation</span><span class="admin-btn-subtitle">Notify students that consultation is active.</span></button>
<button class="admin-action-btn close-btn" type="submit" name="close_consultation"><span class="admin-btn-title">Close Consultation</span><span class="admin-btn-subtitle">Notify students that consultation has ended.</span></button>
</div></form></section>
<section class="registration-card">
<h3 class="admin-section-title">Current Portal Status</h3>
<div class="admin-status-grid">
<div class="admin-status-box"><span class="admin-status-label">Course Registration</span><span class="admin-status-value"><?php echo strtoupper(htmlspecialchars($registrationStatus)); ?></span></div>
<div class="admin-status-box"><span class="admin-status-label">Consultation</span><span class="admin-status-value"><?php echo strtoupper(htmlspecialchars($consultationStatus)); ?></span></div>
</div></section>
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
  </script>

</body></html>
