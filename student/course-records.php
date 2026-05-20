<?php
include("../includes/db.php");
include("../includes/auth_check.php");
requireStudent();

$studentId = $_SESSION["user_id"];
$sql = "SELECT s.*, d.department_name FROM students s INNER JOIN departments d ON s.department_id = d.id WHERE s.id = ? LIMIT 1";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $studentId);
$stmt->execute();
$result = $stmt->get_result();
if (!$result || $result->num_rows === 0) { session_unset(); session_destroy(); header("Location: ../login.php"); exit(); }
$student = $result->fetch_assoc();

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

$records = [];
$recordSql = "SELECT session, semester, MIN(created_at) AS created_at FROM course_registrations WHERE student_id = ? GROUP BY session, semester ORDER BY created_at DESC";
$recordStmt = $conn->prepare($recordSql);
$recordStmt->bind_param("i", $studentId);
$recordStmt->execute();
$recordResult = $recordStmt->get_result();
while ($row = $recordResult->fetch_assoc()) $records[] = $row;
$recordCount = count($records);
?>
<!DOCTYPE html>
<html lang="en"><head><meta charset="UTF-8" /><meta name="viewport" content="width=device-width, initial-scale=1.0" />
<title>Course Registration Details</title><link rel="stylesheet" href="../style.css" /><link rel="manifest" href="../manifest.json" /></head>
<body class="portal-bg"><div class="bg-watermark"></div>
<div class="dashboard-shell">
<aside class="student-sidebar" id="studentSidebar">
  <div class="sidebar-brand"><img src="../rectem-logo.png" class="sidebar-brand-logo" alt="RECTEM Logo"><div class="sidebar-brand-text"><h3>RECTEM</h3><p>Student Portal</p></div></div>
  <nav class="sidebar-nav">
    <a href="dashboard.php" class="sidebar-link">Dashboard</a>
    <div class="sidebar-group open"><button class="sidebar-dropdown-btn" type="button"><span>Registration</span><span class="dropdown-icon">▼</span></button>
      <div class="sidebar-submenu">
        <a href="course-registration.php">Course Registration</a>
        <a href="course-records.php" class="active-submenu-link">Course Registration Details</a>
        <a href="print-course-form.php">Print Course Form</a>
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
  <div class="topbar-welcome"><h2>Welcome <span><?php echo htmlspecialchars(explode(" ", $student["full_name"])[0]); ?></span></h2><p>Course Registration Details</p></div>
  <div class="topbar-actions">
    <button class="notification-btn" type="button" title="Notifications" onclick="window.location.href='notifications.php'">🔔<?php if ($notificationCount > 0): ?><span class="notification-badge"><?php echo $notificationCount; ?></span><?php endif; ?></button>
    <img src="<?php echo htmlspecialchars($profileImage); ?>" class="topbar-profile-img" alt="Profile">
  </div>
</header>

<section class="student-info-card">
  <div class="student-passport-wrap"><img src="<?php echo htmlspecialchars($profileImage); ?>" class="student-passport" alt="Student Passport"></div>
  <div class="student-info-details">
    <h3>Student Details</h3>
    <div class="info-grid">
      <div class="info-item"><span class="info-label">Student Name</span><span class="info-value"><?php echo htmlspecialchars($student["full_name"]); ?></span></div>
      <div class="info-item"><span class="info-label">Matric Number</span><span class="info-value"><?php echo htmlspecialchars($student["matric_number"]); ?></span></div>
      <div class="info-item"><span class="info-label">School</span><span class="info-value"><?php echo htmlspecialchars($student["department_name"]); ?></span></div>
      <div class="info-item"><span class="info-label">Programme</span><span class="info-value"><?php echo htmlspecialchars($student["department_name"]); ?></span></div>
      <div class="info-item"><span class="info-label">Level</span><span class="info-value"><?php echo htmlspecialchars($student["level"]); ?></span></div>
      <div class="info-item"><span class="info-label">Session</span><span class="info-value"><?php echo htmlspecialchars($student["current_session"]); ?></span></div>
    </div>
  </div>
</section>

<section class="registration-card">
  <h3 style="margin-top:0;">Course Registration Details</h3>
  <div class="table-wrap">
    <table class="course-table">
      <thead><tr><th>S/N</th><th>Matric Number</th><th>Academic Session</th><th>Level</th><th>Semester</th><th>Print</th></tr></thead>
      <tbody>
        <?php if ($recordCount === 0): ?>
          <tr><td colspan="6" class="empty-row">No course registration records found.</td></tr>
        <?php else: foreach ($records as $index => $record): ?>
          <tr>
            <td><?php echo $index + 1; ?></td>
            <td><?php echo htmlspecialchars($student["matric_number"]); ?></td>
            <td><?php echo htmlspecialchars($record["session"]); ?></td>
            <td><?php echo htmlspecialchars($student["level"]); ?></td>
            <td><?php echo htmlspecialchars($record["semester"]); ?></td>
            <td><a href="print-course-form.php?session=<?php echo urlencode($record['session']); ?>&semester=<?php echo urlencode($record['semester']); ?>" style="color:#2563eb;text-decoration:none;font-weight:700;">Click to Print</a></td>
          </tr>
        <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>
  <p style="margin-top:14px;color:#64748b;">Number of records: <?php echo $recordCount; ?></p>
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
