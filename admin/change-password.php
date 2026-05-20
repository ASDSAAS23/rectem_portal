<?php
include("../includes/db.php");
include("../includes/auth_check.php");
requireAdmin();
$adminId = $_SESSION["user_id"]; $message=""; $messageClass="";
$adminSql = "SELECT a.*, d.department_name FROM admins a LEFT JOIN departments d ON a.department_id = d.id WHERE a.id = ? LIMIT 1";
$adminStmt = $conn->prepare($adminSql); $adminStmt->bind_param("i", $adminId); $adminStmt->execute(); $adminResult = $adminStmt->get_result();
if (!$adminResult || $adminResult->num_rows === 0) { session_unset(); session_destroy(); header("Location: ../admin-login.php"); exit(); }
$admin = $adminResult->fetch_assoc();
$adminDepartmentName = $admin["department_name"] ?? "Unknown Department";
$adminInitial = strtoupper(substr($admin["full_name"], 0, 1));

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $currentPassword = trim($_POST["current_password"] ?? "");
    $newPassword = trim($_POST["new_password"] ?? "");
    $confirmPassword = trim($_POST["confirm_password"] ?? "");
    if ($currentPassword === "" || $newPassword === "" || $confirmPassword === "") { $message = "Please fill all password fields."; $messageClass = "error"; }
    elseif ($newPassword !== $confirmPassword) { $message = "New passwords do not match."; $messageClass = "error"; }
    elseif (strlen($newPassword) < 4) { $message = "New password must be at least 4 characters."; $messageClass = "error"; }
    else {
        $passwordValid = ($currentPassword === $admin["password"] || password_verify($currentPassword, $admin["password"]));
        if (!$passwordValid) { $message = "Current password is incorrect."; $messageClass = "error"; }
        else {
            $newHash = password_hash($newPassword, PASSWORD_DEFAULT);
            $updateSql = "UPDATE admins SET password = ? WHERE id = ?";
            $updateStmt = $conn->prepare($updateSql); $updateStmt->bind_param("si", $newHash, $adminId); $updateStmt->execute();
            $message = "Password changed successfully."; $messageClass = "success";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en"><head><meta charset="UTF-8" /><meta name="viewport" content="width=device-width, initial-scale=1.0" />
<title>Change Admin Password</title><link rel="stylesheet" href="../style.css" /></head>
<body class="portal-bg"><div class="bg-watermark"></div>
<div class="dashboard-shell">
<aside class="student-sidebar" id="studentSidebar">
<div class="sidebar-brand"><img src="../rectem-logo.png" class="sidebar-brand-logo" alt="RECTEM Logo"><div class="sidebar-brand-text"><h3>RECTEM</h3><p>Admin Portal</p></div></div>
<nav class="sidebar-nav">
<a href="dashboard.php" class="sidebar-link">Dashboard</a>
<a href="view-students.php" class="sidebar-link">View Students</a>
<a href="upload-results.php" class="sidebar-link">Upload Results</a>
<a href="result-preview.php" class="sidebar-link">Result Preview Sheet</a>
<a href="notifications.php" class="sidebar-link">Send Notification</a>
<a href="change-password.php" class="sidebar-link active-link">Change Password</a>
<a href="../logout.php" class="sidebar-link logout-link">Logout</a>
</nav></aside>
<div class="sidebar-overlay" id="sidebarOverlay"></div>
<main class="student-main">
<header class="student-topbar"><button class="topbar-menu-btn" id="menuToggleBtn" type="button">☰</button>
<div class="topbar-welcome"><h2>Change Password</h2><p>Department: <?php echo htmlspecialchars($adminDepartmentName); ?></p></div>
<div class="topbar-actions"><div class="topbar-profile-img" style="display:grid;place-items:center;background:#e2e8f0;color:#334155;font-weight:800;"><?php echo $adminInitial; ?></div></div></header>
<section class="registration-card">
<h3 class="admin-section-title">Update Admin Password</h3><p class="admin-section-subtitle">Your Staff ID remains fixed. Only password can be changed.</p>
<form method="POST">
<div class="form-group"><label for="current_password">Current Password</label><input type="password" id="current_password" name="current_password" required></div>
<div class="form-group"><label for="new_password">New Password</label><input type="password" id="new_password" name="new_password" required></div>
<div class="form-group"><label for="confirm_password">Confirm New Password</label><input type="password" id="confirm_password" name="confirm_password" required></div>
<p class="course-message <?php echo htmlspecialchars($messageClass); ?>"><?php echo htmlspecialchars($message); ?></p>
<button type="submit" class="submit-registration-btn">Change Password</button>
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
  </script>

</body></html>
