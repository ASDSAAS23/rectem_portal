<?php
session_start();
include("../includes/db.php");
include("../includes/auth_check.php");
requireStudent();

$studentId = $_SESSION["user_id"];
$sessionName = trim($_GET["session"] ?? "");
$semester = trim($_GET["semester"] ?? "");

$studentSql = "SELECT s.*, d.department_name
               FROM students s
               INNER JOIN departments d ON s.department_id = d.id
               WHERE s.id = ?
               LIMIT 1";
$studentStmt = $conn->prepare($studentSql);
$studentStmt->bind_param("i", $studentId);
$studentStmt->execute();
$studentResult = $studentStmt->get_result();

if (!$studentResult || $studentResult->num_rows === 0) {
    session_unset();
    session_destroy();
    header("Location: ../login.php");
    exit();
}

$student = $studentResult->fetch_assoc();

$profileImage = "../profile.jpg";
if (!empty($student["profile_picture"]) && file_exists("../uploads/profile_pictures/" . $student["profile_picture"])) {
    $profileImage = "../uploads/profile_pictures/" . $student["profile_picture"];
}

$notificationCount = 0;
$countSql = "SELECT COUNT(*) AS unread_count
             FROM notifications
             WHERE (student_id = ? OR audience = 'all')
             AND is_read = 0";
$countStmt = $conn->prepare($countSql);
$countStmt->bind_param("i", $studentId);
$countStmt->execute();
$countResult = $countStmt->get_result();
if ($countResult && $countResult->num_rows > 0) {
    $notificationCount = (int)$countResult->fetch_assoc()["unread_count"];
}

$results = [];
$totalUnits = 0;
$totalWgp = 0;

if ($sessionName !== "" && $semester !== "") {
    $sql = "SELECT *
            FROM results
            WHERE student_id = ? AND session = ? AND semester = ?
            ORDER BY course_code ASC";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("iss", $studentId, $sessionName, $semester);
    $stmt->execute();
    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {
        $results[] = $row;
        $totalUnits += (int)$row["course_unit"];
        $totalWgp += ((float)$row["grade_point"] * (int)$row["course_unit"]);
    }
}

$gpa = $totalUnits > 0 ? ($totalWgp / $totalUnits) : 0;

$cumulativeUnits = 0;
$cumulativeWgp = 0;

$cumSql = "SELECT course_unit, grade_point FROM results WHERE student_id = ?";
$cumStmt = $conn->prepare($cumSql);
$cumStmt->bind_param("i", $studentId);
$cumStmt->execute();
$cumResult = $cumStmt->get_result();

while ($cumRow = $cumResult->fetch_assoc()) {
    $cumulativeUnits += (int)$cumRow["course_unit"];
    $cumulativeWgp += ((float)$cumRow["grade_point"] * (int)$cumRow["course_unit"]);
}

$cgpa = $cumulativeUnits > 0 ? ($cumulativeWgp / $cumulativeUnits) : 0;

?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Result Checker</title>
  <link rel="stylesheet" href="../style.css" />
  <link rel="manifest" href="../manifest.json" />
</head>
<body class="portal-bg">
  <div class="bg-watermark"></div>

  <div class="dashboard-shell">
    <aside class="student-sidebar" id="studentSidebar">
      <div class="sidebar-brand">
        <img src="../rectem-logo.png" class="sidebar-brand-logo" alt="RECTEM Logo">
        <div class="sidebar-brand-text">
          <h3>RECTEM</h3>
          <p>Student Portal</p>
        </div>
      </div>

      <nav class="sidebar-nav">
        <a href="dashboard.php" class="sidebar-link">Dashboard</a>

        <div class="sidebar-group open">
          <button class="sidebar-dropdown-btn" type="button">
            <span>Results</span>
            <span class="dropdown-icon">▼</span>
          </button>
          <div class="sidebar-submenu">
            <a href="results.php">Result Preview</a>
            <a href="result-checker.php" class="active-submenu-link">Result Checker</a>
            <a href="print-result.php?session=<?php echo urlencode($sessionName); ?>&semester=<?php echo urlencode($semester); ?>">Print Result</a>
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

        <div class="topbar-welcome">
          <h2>Welcome <span><?php echo htmlspecialchars(explode(" ", $student["full_name"])[0]); ?></span></h2>
          <p>Result Checker</p>
        </div>

        <div class="topbar-actions">
          <button class="notification-btn" type="button" title="Notifications" onclick="window.location.href='notifications.php'">
            🔔
            <?php if ($notificationCount > 0): ?>
              <span class="notification-badge"><?php echo $notificationCount; ?></span>
            <?php endif; ?>
          </button>
          <img src="<?php echo htmlspecialchars($profileImage); ?>" class="topbar-profile-img" alt="Profile">
        </div>
      </header>

      <?php if (count($results) === 0): ?>
        <section class="page-title-card">
          <h3>No Result Found</h3>
          <p>No uploaded result was found for this session and semester.</p>
        </section>
      <?php else: ?>
        <section class="student-info-card">
          <div class="student-passport-wrap">
            <img src="<?php echo htmlspecialchars($profileImage); ?>" class="student-passport" alt="Student Passport">
          </div>

          <div class="student-info-details">
            <h3>Result Information</h3>
            <div class="info-grid">
              <div class="info-item">
                <span class="info-label">Student Name</span>
                <span class="info-value"><?php echo htmlspecialchars($student["full_name"]); ?></span>
              </div>
              <div class="info-item">
                <span class="info-label">Matric Number</span>
                <span class="info-value"><?php echo htmlspecialchars($student["matric_number"]); ?></span>
              </div>
              <div class="info-item">
                <span class="info-label">Programme</span>
                <span class="info-value"><?php echo htmlspecialchars($student["department_name"]); ?></span>
              </div>
              <div class="info-item">
                <span class="info-label">Level</span>
                <span class="info-value"><?php echo htmlspecialchars($student["level"]); ?></span>
              </div>
              <div class="info-item">
                <span class="info-label">Session</span>
                <span class="info-value"><?php echo htmlspecialchars($sessionName); ?></span>
              </div>
              <div class="info-item">
                <span class="info-label">Semester</span>
                <span class="info-value"><?php echo htmlspecialchars($semester); ?></span>
              </div>
            </div>
          </div>
        </section>

        

        <section class="registration-card">
          <div class="table-wrap">
            <table class="course-table">
              <thead>
                <tr>
                  <th>S/N</th>
                  <th>Course Title</th>
                  <th>Course Code</th>
                  <th>Unit</th>
                  <th>Score</th>
                  <th>Grade</th>
                  <th>GP</th>
                  <th>WGP</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($results as $index => $row): ?>
                  <?php $wgp = ((float)$row["grade_point"] * (int)$row["course_unit"]); ?>
                  <tr>
                    <td><?php echo $index + 1; ?></td>
                    <td><?php echo htmlspecialchars($row["course_title"]); ?></td>
                    <td><?php echo htmlspecialchars($row["course_code"]); ?></td>
                    <td><?php echo (int)$row["course_unit"]; ?></td>
                    <td><?php echo htmlspecialchars($row["score"]); ?></td>
                    <td><?php echo htmlspecialchars($row["grade"]); ?></td>
                    <td><?php echo number_format((float)$row["grade_point"], 2); ?></td>
                    <td><?php echo number_format($wgp, 2); ?></td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>

          <div class="sheet-units-line" style="margin-top:16px;">
            <span>Total Unit(s): <strong><?php echo $totalUnits; ?></strong></span>
            <span>Total WGP: <strong><?php echo number_format($totalWgp, 2); ?></strong></span>
            <span>GPA: <strong><?php echo number_format($gpa, 2); ?></strong></span>
            <span>CGPA: <strong><?php echo number_format($cgpa, 2); ?></strong></span>
          </div>

          <button class="print-btn" type="button"
                  onclick="window.location.href='print-result.php?session=<?php echo urlencode($sessionName); ?>&semester=<?php echo urlencode($semester); ?>'">
            Print Result
          </button>
        </section>
      <?php endif; ?>
    </main>
  </div>

  <footer class="portal-footer">
    © 2026 RECTEM Student Portal
    <br>
    Developed by Adebowale Adeyinka Josiah
  </footer>

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
</body>
</html>