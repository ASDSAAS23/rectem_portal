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
$datePrinted = date("d/m/Y");
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Print Result</title>
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
            <a href="result-checker.php?session=<?php echo urlencode($sessionName); ?>&semester=<?php echo urlencode($semester); ?>">Result Checker</a>
            <a href="print-result.php?session=<?php echo urlencode($sessionName); ?>&semester=<?php echo urlencode($semester); ?>" class="active-submenu-link">Print Result</a>
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
          <p>Print Result</p>
        </div>
        <div class="topbar-actions">
          <img src="<?php echo htmlspecialchars($profileImage); ?>" class="topbar-profile-img" alt="Profile">
        </div>
      </header>

      <?php if (count($results) === 0): ?>
        <section class="page-title-card">
          <h3>No Result Found</h3>
          <p>No printable result was found for this student.</p>
        </section>
      <?php else: ?>
        <section class="rectem-print-sheet">
          <div class="rectem-sheet-header">
            <img src="../rectem-logo.png" alt="RECTEM Logo" class="sheet-school-logo">
            <div class="sheet-school-text">
              <h2>REDEEMER'S COLLEGE OF TECHNOLOGY AND MANAGEMENT</h2>
            </div>
          </div>

          <div class="sheet-title-bar">STUDENT RESULT SLIP</div>

          <div class="sheet-info-top">
            <div class="sheet-info-left">
              <p><span>MATRIC NUMBER:</span> <strong><?php echo htmlspecialchars($student["matric_number"]); ?></strong></p>
              <p><span>NAME:</span> <strong><?php echo htmlspecialchars($student["full_name"]); ?></strong></p>
              <p><span>LEVEL:</span> <strong><?php echo htmlspecialchars($student["level"]); ?></strong></p>
              <p><span>SEMESTER:</span> <strong><?php echo htmlspecialchars($semester); ?></strong></p>
              <p><span>ACADEMIC SESSION:</span> <strong><?php echo htmlspecialchars($sessionName); ?></strong></p>
              <p><span>SCHOOL:</span> <strong><?php echo htmlspecialchars($student["department_name"]); ?></strong></p>
              <p><span>PROGRAMME:</span> <strong><?php echo htmlspecialchars($student["department_name"]); ?></strong></p>
            </div>

            <div class="sheet-info-right">
              <img src="<?php echo htmlspecialchars($profileImage); ?>" alt="Passport">
            </div>
          </div>

          <div class="table-wrap">
            <table class="sheet-table">
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

          <div class="sheet-units-line">
            <span>Total Unit(s): <strong><?php echo $totalUnits; ?></strong></span>
            <span>Total WGP: <strong><?php echo number_format($totalWgp, 2); ?></strong></span>
            <span>GPA: <strong><?php echo number_format($gpa, 2); ?></strong></span>
            <span>CGPA: <strong><?php echo number_format($cgpa, 2); ?></strong></span>
          </div>
          <div class="sheet-signatures">
            <div>
              <div class="signature-line"></div>
              <p>Academic Officer</p>
            </div>
            <div>
              <div class="signature-line"></div>
              <p>School Officer</p>
            </div>
          </div>

          <div class="sheet-dates">
            <p><span>DATE PRINTED:</span> <strong><?php echo $datePrinted; ?></strong></p>
          </div>

          <button class="print-btn" type="button" onclick="window.print()">Print Result</button>
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