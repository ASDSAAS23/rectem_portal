<?php
include("../includes/db.php");
include("../includes/auth_check.php");
requireStudent();

$studentId = $_SESSION["user_id"];
$sessionQ = trim($_GET["session"] ?? "");
$semesterQ = trim($_GET["semester"] ?? "");

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

$latestSession = $sessionQ;
$latestSemester = $semesterQ;
if ($latestSession === "" || $latestSemester === "") {
    $latestSql = "SELECT session, semester FROM course_registrations WHERE student_id = ? ORDER BY id DESC LIMIT 1";
    $latestStmt = $conn->prepare($latestSql);
    $latestStmt->bind_param("i", $studentId);
    $latestStmt->execute();
    $latestResult = $latestStmt->get_result();
    if ($latestResult && $latestResult->num_rows > 0) {
        $latestRow = $latestResult->fetch_assoc();
        if ($latestSession === "") $latestSession = $latestRow["session"];
        if ($latestSemester === "") $latestSemester = $latestRow["semester"];
    }
}

$courses = [];
$totalUnits = 0;
$maxUnits = 32;
$levelKey = "max_units_" . strtolower($student["level"]);
$maxRes = $conn->query("SELECT setting_value FROM portal_settings WHERE setting_key = '" . $conn->real_escape_string($levelKey) . "' LIMIT 1");
if ($maxRes && $maxRes->num_rows > 0) {
    $maxUnits = (int)$maxRes->fetch_assoc()["setting_value"];
}

if ($latestSession !== "" && $latestSemester !== "") {
    $courseSql = "SELECT c.course_code, c.course_title, c.unit
                  FROM course_registrations r
                  INNER JOIN courses c ON r.course_id = c.id
                  WHERE r.student_id = ? AND r.session = ? AND r.semester = ?
                  ORDER BY c.course_code";
    $courseStmt = $conn->prepare($courseSql);
    $courseStmt->bind_param("iss", $studentId, $latestSession, $latestSemester);
    $courseStmt->execute();
    $courseResult = $courseStmt->get_result();
    while ($row = $courseResult->fetch_assoc()) {
        $courses[] = $row;
        $totalUnits += (int)$row["unit"];
    }
}

$datePrinted = date("d/m/Y");
?>
<!DOCTYPE html>
<html lang="en">
<head><meta charset="UTF-8" /><meta name="viewport" content="width=device-width, initial-scale=1.0" />
<title>Print Course Form</title><link rel="stylesheet" href="../style.css" /><link rel="manifest" href="../manifest.json" /></head>
<body class="portal-bg">
<div class="bg-watermark"></div>
<div class="dashboard-shell">
<aside class="student-sidebar" id="studentSidebar">
  <div class="sidebar-brand"><img src="../rectem-logo.png" class="sidebar-brand-logo" alt="RECTEM Logo"><div class="sidebar-brand-text"><h3>RECTEM</h3><p>Student Portal</p></div></div>
  <nav class="sidebar-nav">
    <a href="dashboard.php" class="sidebar-link">Dashboard</a>
    <div class="sidebar-group open">
      <button class="sidebar-dropdown-btn" type="button"><span>Registration</span><span class="dropdown-icon">▼</span></button>
      <div class="sidebar-submenu">
        <a href="course-registration.php">Course Registration</a>
        <a href="course-records.php">Course Registration Details</a>
        <a href="print-course-form.php" class="active-submenu-link">Print Course Form</a>
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
  <div class="topbar-welcome"><h2>Welcome <span><?php echo htmlspecialchars(explode(" ", $student["full_name"])[0]); ?></span></h2><p>Print Course Form</p></div>
  <div class="topbar-actions"><img src="<?php echo htmlspecialchars($profileImage); ?>" class="topbar-profile-img" alt="Profile"></div>
</header>

<?php if (empty($courses)): ?>
  <section class="page-title-card"><h3>No Course Registration Found</h3><p>You need to register courses first before printing the form.</p></section>
<?php else: ?>
  <section class="rectem-print-sheet">
    <div class="rectem-sheet-header">
      <img src="../rectem-logo.png" alt="RECTEM Logo" class="sheet-school-logo">
      <div class="sheet-school-text"><h2>REDEEMER'S COLLEGE OF TECHNOLOGY AND MANAGEMENT</h2></div>
    </div>
    <div class="sheet-title-bar">COURSE REGISTRATION FORM</div>
    <div class="sheet-info-top">
      <div class="sheet-info-left">
        <p><span>MATRIC NUMBER:</span> <strong><?php echo htmlspecialchars($student["matric_number"]); ?></strong></p>
        <p><span>NAME:</span> <strong><?php echo htmlspecialchars($student["full_name"]); ?></strong></p>
        <p><span>LEVEL:</span> <strong><?php echo htmlspecialchars($student["level"]); ?></strong></p>
        <p><span>SEMESTER:</span> <strong><?php echo htmlspecialchars($latestSemester); ?></strong></p>
        <p><span>ACADEMIC SESSION:</span> <strong><?php echo htmlspecialchars($latestSession); ?></strong></p>
        <p><span>SCHOOL:</span> <strong><?php echo htmlspecialchars($student["department_name"]); ?></strong></p>
        <p><span>PROGRAMME:</span> <strong><?php echo htmlspecialchars($student["department_name"]); ?></strong></p>
      </div>
      <div class="sheet-info-right"><img src="<?php echo htmlspecialchars($profileImage); ?>" alt="Passport"></div>
    </div>

    <div class="table-wrap">
      <table class="sheet-table">
        <thead><tr><th>S/N</th><th>Course Title</th><th>Course Code</th><th>Course Unit</th><th>Remark</th></tr></thead>
        <tbody>
        <?php foreach ($courses as $index => $course): ?>
          <tr>
            <td><?php echo $index + 1; ?></td>
            <td><?php echo htmlspecialchars($course["course_title"]); ?></td>
            <td><?php echo htmlspecialchars($course["course_code"]); ?></td>
            <td><?php echo (int)$course["unit"]; ?></td>
            <td>C</td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>

    <div class="sheet-units-line">
      <span>Total Registered Unit(s): <strong><?php echo $totalUnits; ?></strong></span>
      <span>Maximum Allowed Unit(s): <strong><?php echo $maxUnits; ?></strong></span>
    </div>

    <div class="sheet-signatures">
      <div><div class="signature-line"></div><p>Class Adviser/HOD</p></div>
      <div><div class="signature-line"></div><p>School Officer</p></div>
    </div>

    <div class="sheet-dates">
      <p><span>DATE PRINTED:</span> <strong><?php echo $datePrinted; ?></strong></p>
    </div>

    <button class="print-btn" type="button" onclick="window.print()">Print Form</button>
  </section>
<?php endif; ?>
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
