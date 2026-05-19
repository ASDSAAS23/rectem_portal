<?php
session_start();
include("../includes/db.php");
include("../includes/auth_check.php");
requireAdmin();

$adminId = $_SESSION["user_id"];
$adminSql = "SELECT a.*, d.department_name FROM admins a LEFT JOIN departments d ON a.department_id = d.id WHERE a.id = ? LIMIT 1";
$adminStmt = $conn->prepare($adminSql);
$adminStmt->bind_param("i", $adminId);
$adminStmt->execute();
$adminResult = $adminStmt->get_result();
if (!$adminResult || $adminResult->num_rows === 0) { session_unset(); session_destroy(); header("Location: ../admin-login.php"); exit(); }
$admin = $adminResult->fetch_assoc();
$adminDepartmentId = $admin["department_id"]; $adminDepartmentName = $admin["department_name"] ?? "Unknown Department";
$adminInitial = strtoupper(substr($admin["full_name"], 0, 1));
$students=[]; $message=""; $messageClass="";
if ($_SERVER["REQUEST_METHOD"] === "GET" && isset($_GET["level"])) {
    $level = trim($_GET["level"]);
    if ($level === "") { $message = "Please select a level first."; $messageClass = "error"; }
    else {
        $studentSql = "SELECT s.*, d.department_name FROM students s INNER JOIN departments d ON s.department_id = d.id WHERE s.department_id = ? AND s.level = ? ORDER BY s.full_name ASC";
        $studentStmt = $conn->prepare($studentSql);
        $studentStmt->bind_param("is", $adminDepartmentId, $level);
        $studentStmt->execute();
        $studentResult = $studentStmt->get_result();
        while($row=$studentResult->fetch_assoc()) $students[]=$row;
        $message = count($students) ? "Showing " . count($students) . " student(s) in " . htmlspecialchars($level) . "." : "No students found for the selected level.";
        $messageClass = count($students) ? "success" : "error";
    }
}
$studentCount=count($students);
?>
<!DOCTYPE html>
<html lang="en"><head><meta charset="UTF-8" /><meta name="viewport" content="width=device-width, initial-scale=1.0" />
<title>Admin View Students</title><link rel="stylesheet" href="../style.css" /></head>
<body class="portal-bg"><div class="bg-watermark"></div>
<div class="dashboard-shell">
<aside class="student-sidebar" id="studentSidebar">
<div class="sidebar-brand"><img src="../rectem-logo.png" class="sidebar-brand-logo" alt="RECTEM Logo"><div class="sidebar-brand-text"><h3>RECTEM</h3><p>Admin Portal</p></div></div>
<nav class="sidebar-nav">
<a href="dashboard.php" class="sidebar-link">Dashboard</a>
<a href="view-students.php" class="sidebar-link active-link">View Students</a>
<a href="upload-results.php" class="sidebar-link">Upload Results</a>
<a href="result-preview.php" class="sidebar-link">Result Preview Sheet</a>
<a href="notifications.php" class="sidebar-link">Send Notification</a>
<a href="change-password.php" class="sidebar-link">Change Password</a>
<a href="../logout.php" class="sidebar-link logout-link">Logout</a>
</nav></aside>
<div class="sidebar-overlay" id="sidebarOverlay"></div>
<main class="student-main">
<header class="student-topbar"><button class="topbar-menu-btn" id="menuToggleBtn" type="button">☰</button>
<div class="topbar-welcome"><h2>View Students</h2><p>Department: <?php echo htmlspecialchars($adminDepartmentName); ?></p></div>
<div class="topbar-actions"><div class="topbar-profile-img" style="display:grid;place-items:center;background:#e2e8f0;color:#334155;font-weight:800;"><?php echo $adminInitial; ?></div></div>
</header>
<section class="page-title-card admin-hero-card"><div class="admin-hero-text"><h3>Department Students</h3><p>Select a level, then load all students in your department for that level.</p></div></section>
<section class="registration-card">
<h3 class="admin-section-title">Filter Students By Level</h3><p class="admin-section-subtitle">Only students in your own department will be shown.</p>
<form method="GET">
<div class="registration-top-row"><div class="semester-select-wrap"><label for="viewStudentsLevel">Select Level</label>
<select id="viewStudentsLevel" name="level">
<option value="">-- Select Level --</option>
<option value="ND1" <?php echo (($_GET["level"] ?? "") === "ND1") ? "selected" : ""; ?>>ND1</option>
<option value="ND2" <?php echo (($_GET["level"] ?? "") === "ND2") ? "selected" : ""; ?>>ND2</option>
<option value="HND1" <?php echo (($_GET["level"] ?? "") === "HND1") ? "selected" : ""; ?>>HND1</option>
<option value="HND2" <?php echo (($_GET["level"] ?? "") === "HND2") ? "selected" : ""; ?>>HND2</option>
</select></div></div>
<p class="course-message <?php echo htmlspecialchars($messageClass); ?>"><?php echo $message; ?></p>
<div class="admin-upload-actions"><button class="submit-registration-btn" type="submit">View Students</button></div>
</form></section>
<section class="registration-card">
<h3 class="admin-section-title">Student List</h3>
<div class="table-wrap"><table class="course-table">
<thead><tr><th>S/N</th><th>Student Name</th><th>Matric Number</th><th>Department</th><th>Programme</th><th>Level</th><th>Email</th></tr></thead>
<tbody>
<?php if ($studentCount === 0): ?><tr><td colspan="7" class="empty-row">Select a level and click View Students.</td></tr>
<?php else: foreach($students as $index => $student): ?>
<tr>
<td><?php echo $index + 1; ?></td>
<td><?php echo htmlspecialchars($student["full_name"]); ?></td>
<td><?php echo htmlspecialchars($student["matric_number"]); ?></td>
<td><?php echo htmlspecialchars($student["department_name"]); ?></td>
<td><?php echo htmlspecialchars($student["department_name"]); ?></td>
<td><?php echo htmlspecialchars($student["level"]); ?></td>
<td><?php echo htmlspecialchars($student["email"]); ?></td>
</tr>
<?php endforeach; endif; ?>
</tbody></table></div>
<p style="margin-top:14px;color:#64748b;">Number of students: <?php echo $studentCount; ?></p>
</section></main></div>
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
