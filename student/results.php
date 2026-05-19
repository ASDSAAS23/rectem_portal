<?php
session_start();
include("../includes/db.php");
include("../includes/auth_check.php");
requireStudent();

$studentId = $_SESSION["user_id"];
$studentSql = "SELECT s.*, d.department_name FROM students s INNER JOIN departments d ON s.department_id = d.id WHERE s.id = ? LIMIT 1";
$studentStmt = $conn->prepare($studentSql);
$studentStmt->bind_param("i", $studentId);
$studentStmt->execute();
$studentResult = $studentStmt->get_result();
if (!$studentResult || $studentResult->num_rows === 0) { session_unset(); session_destroy(); header("Location: ../login.php"); exit(); }
$student = $studentResult->fetch_assoc();

$profileImage = "../profile.jpg";
if (!empty($student["profile_picture"]) && file_exists("../uploads/profile_pictures/" . $student["profile_picture"])) {
    $profileImage = "../uploads/profile_pictures/" . $student["profile_picture"];
}

$notificationCount = 0;
$countSql = "SELECT COUNT(*) AS unread_count FROM notifications WHERE (student_id = ? OR audience = 'all') AND is_read = 0";
$countStmt = $conn->prepare($countSql);
$countStmt->bind_param("i", $studentId);
$countStmt->execute();
$countResult = $countStmt->get_result();
if ($countResult && $countResult->num_rows > 0) $notificationCount = (int)$countResult->fetch_assoc()["unread_count"];

$resultRecords = [];
$resultSql = "SELECT session, semester, COUNT(*) AS total_courses, MAX(created_at) AS uploaded_at FROM results WHERE student_id = ? GROUP BY session, semester ORDER BY uploaded_at DESC";
$resultStmt = $conn->prepare($resultSql);
$resultStmt->bind_param("i", $studentId);
$resultStmt->execute();
$resultResult = $resultStmt->get_result();
while ($row = $resultResult->fetch_assoc()) $resultRecords[] = $row;
$resultCount = count($resultRecords);
?>
<!DOCTYPE html>
<html lang="en">
<head><meta charset="UTF-8" /><meta name="viewport" content="width=device-width, initial-scale=1.0" />
<title>Result Preview</title><link rel="stylesheet" href="../style.css" /><link rel="manifest" href="../manifest.json" /></head>
<body class="portal-bg"><div class="bg-watermark"></div>
<div class="dashboard-shell">
<aside class="student-sidebar" id="studentSidebar">
  <div class="sidebar-brand"><img src="../rectem-logo.png" class="sidebar-brand-logo" alt="RECTEM Logo"><div class="sidebar-brand-text"><h3>RECTEM</h3><p>Student Portal</p></div></div>
  <nav class="sidebar-nav">
    <a href="dashboard.php" class="sidebar-link">Dashboard</a>
    <div class="sidebar-group">
      <button class="sidebar-dropdown-btn" type="button"><span>Registration</span><span class="dropdown-icon">▼</span></button>
      <div class="sidebar-submenu">
        <a href="course-registration.php">Course Registration</a>
        <a href="course-records.php">Course Registration Details</a>
        <a href="print-course-form.php">Print Course Form</a>
      </div>
    </div>
    <div class="sidebar-group open">
      <button class="sidebar-dropdown-btn" type="button"><span>Results</span><span class="dropdown-icon">▼</span></button>
      <div class="sidebar-submenu">
        <a href="results.php" class="active-submenu-link">Result Preview</a>
        <a href="result-checker.php">Result Checker</a>
      </div>
    </div>
    <a href="notifications.php" class="sidebar-link">Notifications</a>
    <a href="profile.php" class="sidebar-link">Edit Profile Picture</a>
    <a href="../logout.php" class="sidebar-link logout-link">Logout</a>
  </nav>
</aside>
<div class="sidebar-overlay" id="sidebarOverlay"></div>
<main class="student-main">
<header class="student-topbar">
  <button class="topbar-menu-btn" id="menuToggleBtn" type="button">☰</button>
  <div class="topbar-welcome"><h2>Welcome <span><?php echo htmlspecialchars(explode(" ", $student["full_name"])[0]); ?></span></h2><p>Result Preview</p></div>
  <div class="topbar-actions">
    <button class="notification-btn" type="button" title="Notifications" onclick="window.location.href='notifications.php'">🔔<?php if ($notificationCount > 0): ?><span class="notification-badge"><?php echo $notificationCount; ?></span><?php endif; ?></button>
    <img src="<?php echo htmlspecialchars($profileImage); ?>" class="topbar-profile-img" alt="Profile">
  </div>
</header>

<section class="page-title-card">
  <h3>Result Preview</h3>
  <p>View the list of available semester results uploaded for your account.</p>
</section>

<section class="registration-card">
  <div class="table-wrap">
    <table class="course-table">
      <thead><tr><th>S/N</th><th>Academic Session</th><th>Level</th><th>Semester</th><th>Uploaded Courses</th><th>View</th></tr></thead>
      <tbody>
      <?php if ($resultCount === 0): ?>
        <tr><td colspan="6" class="empty-row">No result records found.</td></tr>
      <?php else: foreach ($resultRecords as $index => $record): ?>
        <tr>
          <td><?php echo $index + 1; ?></td>
          <td><?php echo htmlspecialchars($record["session"]); ?></td>
          <td><?php echo htmlspecialchars($student["level"]); ?></td>
          <td><?php echo htmlspecialchars($record["semester"]); ?></td>
          <td><?php echo (int)$record["total_courses"]; ?></td>
          <td><a href="result-checker.php?session=<?php echo urlencode($record['session']); ?>&semester=<?php echo urlencode($record['semester']); ?>" style="color:#2563eb;text-decoration:none;font-weight:700;">View Result</a></td>
        </tr>
      <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>
  <p style="margin-top:14px;color:#64748b;">Number of records: <?php echo $resultCount; ?></p>
</section>
</main></div>
<footer class="portal-footer">© 2026 RECTEM Student Portal<br>Developed by Adebowale Adeyinka Josiah</footer>

  <script>
    const dropdownButtons = document.querySelectorAll(".sidebar-dropdown-btn");
    dropdownButtons.forEach((button) => {
      button.addEventListener("click", function () {
        const parentGroup = this.parentElement;
        parentGroup.classList.toggle("open");
      });
    });
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
  <script src="../assets/js/notifications.js"></script>

</body></html>
