<?php
include("../includes/db.php");
include("../includes/auth_check.php");
include("../includes/ai_helper.php");
include("../includes/web_push.php");
requireAdmin();

$adminId = $_SESSION["user_id"];

$adminSql = "SELECT a.*, d.department_name
             FROM admins a
             LEFT JOIN departments d ON a.department_id = d.id
             WHERE a.id = ?
             LIMIT 1";
$adminStmt = $conn->prepare($adminSql);
$adminStmt->bind_param("i", $adminId);
$adminStmt->execute();
$adminResult = $adminStmt->get_result();

if (!$adminResult || $adminResult->num_rows === 0) {
    session_unset();
    session_destroy();
    header("Location: ../login.php");
    exit();
}

$admin = $adminResult->fetch_assoc();
$adminDepartmentName = $admin["department_name"] ?? "Unknown Department";
$adminInitial = strtoupper(substr($admin["full_name"], 0, 1));

$previewRows = $_SESSION["result_preview_rows"] ?? [];
$previewMeta = $_SESSION["result_preview_meta"] ?? null;
$aiPreview = $_SESSION["result_preview_ai"] ?? null;

$message = "";
$messageClass = "";

$groupedResults = [];

foreach ($previewRows as $row) {
    $studentKey = $row["matric_number"];

    if (!isset($groupedResults[$studentKey])) {
        $groupedResults[$studentKey] = [
            "student_name" => $row["student_name"],
            "matric_number" => $row["matric_number"],
            "courses" => [],
            "total_units" => 0,
            "total_wgp" => 0
        ];
    }

    $wgp = (float)$row["grade_point"] * (int)$row["course_unit"];

    $groupedResults[$studentKey]["courses"][] = [
        "course_code" => $row["course_code"],
        "course_title" => $row["course_title"],
        "course_unit" => $row["course_unit"],
        "score" => $row["score"],
        "grade" => $row["grade"],
        "grade_point" => $row["grade_point"],
        "wgp" => $wgp
    ];

    $groupedResults[$studentKey]["total_units"] += (int)$row["course_unit"];
    $groupedResults[$studentKey]["total_wgp"] += $wgp;
}

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["send_all_results"])) {
    if (empty($previewRows)) {
        $message = "No prepared preview available to send.";
        $messageClass = "error";
    } else {
        foreach ($previewRows as $row) {
            $insertSql = "INSERT INTO results
                (student_id, department_id, course_id, session, semester, course_code, course_title, course_unit, score, grade, grade_point, remark, uploaded_by)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ON CONFLICT (student_id, course_id, session, semester) DO UPDATE SET
                    score = EXCLUDED.score,
                    grade = EXCLUDED.grade,
                    grade_point = EXCLUDED.grade_point,
                    remark = EXCLUDED.remark,
                    uploaded_by = EXCLUDED.uploaded_by";
            $insertStmt = $conn->prepare($insertSql);
            $insertStmt->bind_param(
                "iiissssidsdsi",
                $row["student_id"],
                $admin["department_id"],
                $row["course_id"],
                $row["session"],
                $row["semester"],
                $row["course_code"],
                $row["course_title"],
                $row["course_unit"],
                $row["score"],
                $row["grade"],
                $row["grade_point"],
                $row["remark"],
                $adminId
            );
            $insertStmt->execute();
        }

       $studentIds = array_unique(array_column($previewRows, "student_id"));

foreach ($studentIds as $studentId) {
    /* semester results for performance comment */
    $semesterResults = [];
    $totalUnits = 0;
    $totalWgp = 0;

    $semSql = "SELECT course_unit, grade_point, score, course_code, course_title
               FROM results
               WHERE student_id = ? AND session = ? AND semester = ?";
    $semStmt = $conn->prepare($semSql);
    $semStmt->bind_param("iss", $studentId, $previewMeta["session"], $previewMeta["semester"]);
    $semStmt->execute();
    $semResult = $semStmt->get_result();

    while ($semRow = $semResult->fetch_assoc()) {
        $semesterResults[] = $semRow;
        $totalUnits += (int)$semRow["course_unit"];
        $totalWgp += ((float)$semRow["grade_point"] * (int)$semRow["course_unit"]);
    }

    $gpa = $totalUnits > 0 ? ($totalWgp / $totalUnits) : 0;

    /* cumulative CGPA */
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

    $performanceComment = generatePerformanceComment($semesterResults, $gpa, $cgpa);

    /* notification 1: result uploaded */
    $title1 = "📊 Your " . $previewMeta["semester"] . " Results Are Ready!";
    $body1 = "Your " . $previewMeta["level"] . " results for " . $previewMeta["session"] . " " . $previewMeta["semester"] . " have been uploaded by your department. Your semester GPA is " . number_format($gpa, 2) . ". Visit the Result Checker on your portal to view your full breakdown.";

    $notifSql1 = "INSERT INTO notifications (student_id, title, message, audience, type, sent_via_email, is_read)
                  VALUES (?, ?, ?, 'student', 'result', 0, 0)";
    $notifStmt1 = $conn->prepare($notifSql1);
    $notifStmt1->bind_param("iss", $studentId, $title1, $body1);
    $notifStmt1->execute();

    /* notification 2: AI performance comment */
    $title2 = "🤖 Your AI Academic Advisor";
    $gpaLabel = $gpa >= 3.5 ? "Outstanding" : ($gpa >= 3.0 ? "Very Good" : ($gpa >= 2.5 ? "Good" : ($gpa >= 2.0 ? "Fair" : "Needs Improvement")));
    $body2 = "Performance: " . $gpaLabel . " (GPA: " . number_format($gpa, 2) . ", CGPA: " . number_format($cgpa, 2) . "). " . $performanceComment["overview"] . " Advice: " . $performanceComment["advice"];

    $notifSql2 = "INSERT INTO notifications (student_id, title, message, audience, type, sent_via_email, is_read)
                  VALUES (?, ?, ?, 'student', 'performance_comment', 0, 0)";
    $notifStmt2 = $conn->prepare($notifSql2);
    $notifStmt2->bind_param("iss", $studentId, $title2, $body2);
    $notifStmt2->execute();

    // Send push notifications with target URLs
    $pushBody1 = "Your " . $previewMeta["level"] . " " . $previewMeta["semester"] . " results are ready! GPA: " . number_format($gpa, 2) . ". Check your portal now.";
    sendPushToStudent($conn, $studentId, $title1, $pushBody1, BASE_PATH . '/student/result-checker.php');
    $pushBody2 = $gpaLabel . " performance (GPA: " . number_format($gpa, 2) . "). " . substr($performanceComment["advice"], 0, 100);
    sendPushToStudent($conn, $studentId, $title2, $pushBody2, BASE_PATH . '/student/notifications.php');
}

        $message = "Results sent successfully to all matched students.";
        $messageClass = "success";

        unset($_SESSION["result_preview_rows"]);
        unset($_SESSION["result_preview_meta"]);
        unset($_SESSION["result_preview_ai"]);
        $groupedResults = [];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Admin Result Preview Sheet</title>
  <link rel="stylesheet" href="../style.css" />
</head>
<body class="portal-bg">
  <div class="bg-watermark"></div>

  <div class="dashboard-shell">
    <aside class="student-sidebar" id="studentSidebar">
      <div class="sidebar-brand">
        <img src="../rectem-logo.png" class="sidebar-brand-logo" alt="RECTEM Logo">
        <div class="sidebar-brand-text">
          <h3>RECTEM</h3>
          <p>Admin Portal</p>
        </div>
      </div>

      <nav class="sidebar-nav">
        <a href="dashboard.php" class="sidebar-link">Dashboard</a>
        <a href="view-students.php" class="sidebar-link">View Students</a>
        <a href="upload-results.php" class="sidebar-link">Upload Results</a>
        <a href="result-preview.php" class="sidebar-link active-link">Result Preview Sheet</a>
        <a href="notifications.php" class="sidebar-link">Send Notification</a>
        <a href="change-password.php" class="sidebar-link">Change Password</a>
        <a href="../logout.php" class="sidebar-link logout-link">Logout</a>
      </nav>
    </aside>

    <div class="sidebar-overlay" id="sidebarOverlay"></div>

    <main class="student-main">
      <header class="student-topbar no-print">
        <button class="topbar-menu-btn" id="menuToggleBtn" type="button">☰</button>

        <div class="topbar-welcome">
          <h2>Result Preview Sheet</h2>
          <p>Department: <?php echo htmlspecialchars($adminDepartmentName); ?></p>
        </div>

        <div class="topbar-actions">
          <div class="topbar-profile-img" style="display:grid;place-items:center;background:#e2e8f0;color:#334155;font-weight:800;">
            <?php echo $adminInitial; ?>
          </div>
        </div>
      </header>

      <?php if ($aiPreview): ?>
        <section class="page-title-card no-print" style="border-left:4px solid <?php echo ($aiPreview['severity'] === 'success') ? '#16a34a' : (($aiPreview['severity'] === 'warning') ? '#d97706' : '#dc2626'); ?>;">
          <h3>AI Upload Assistant</h3>
          <p><?php echo htmlspecialchars($aiPreview["summary"]); ?></p>

          <?php if (!empty($aiPreview["unmatched_matrics"])): ?>
            <p><strong>Unmatched Matric Numbers:</strong> <?php echo htmlspecialchars(implode(", ", array_slice($aiPreview["unmatched_matrics"], 0, 10))); ?></p>
          <?php endif; ?>

          <?php if (!empty($aiPreview["unknown_headers"])): ?>
            <p><strong>Ignored Unknown Headers:</strong> <?php echo htmlspecialchars(implode(", ", array_slice($aiPreview["unknown_headers"], 0, 10))); ?></p>
          <?php endif; ?>

          <?php if (!empty($aiPreview["invalid_scores"])): ?>
            <p><strong>Invalid Score Entries:</strong> <?php echo htmlspecialchars(implode(", ", array_slice($aiPreview["invalid_scores"], 0, 5))); ?></p>
          <?php endif; ?>

          <?php if (!empty($aiPreview["ignored_unregistered"])): ?>
            <p><strong>Ignored Unregistered Course Entries:</strong> <?php echo htmlspecialchars(implode(", ", array_slice($aiPreview["ignored_unregistered"], 0, 5))); ?></p>
          <?php endif; ?>
        </section>
      <?php endif; ?>

      <section class="registration-card no-print">
        <h3 class="admin-section-title">Preview Information</h3>

        <?php if ($previewMeta): ?>
          <div class="admin-status-grid">
            <div class="admin-status-box">
              <span class="admin-status-label">Department</span>
              <span class="admin-status-value"><?php echo htmlspecialchars($previewMeta["department"]); ?></span>
            </div>

            <div class="admin-status-box">
              <span class="admin-status-label">Level</span>
              <span class="admin-status-value"><?php echo htmlspecialchars($previewMeta["level"]); ?></span>
            </div>

            <div class="admin-status-box">
              <span class="admin-status-label">Session</span>
              <span class="admin-status-value"><?php echo htmlspecialchars($previewMeta["session"]); ?></span>
            </div>

            <div class="admin-status-box">
              <span class="admin-status-label">Semester</span>
              <span class="admin-status-value"><?php echo htmlspecialchars($previewMeta["semester"]); ?></span>
            </div>
          </div>
        <?php endif; ?>

        <form method="POST" style="margin-top:18px;">
          <div class="admin-upload-actions">
            <button class="admin-secondary-btn" type="button" onclick="window.print()">
              Download Preview
            </button>

            <button class="submit-registration-btn" type="submit" name="send_all_results">
              Send To All Students
            </button>
          </div>
        </form>

        <p class="course-message <?php echo htmlspecialchars($messageClass); ?>">
          <?php echo htmlspecialchars($message); ?>
        </p>
      </section>

      <section class="result-sheet-wrapper">
        <div class="wide-result-sheet-head">
          <h2>REDEEMER'S COLLEGE OF TECHNOLOGY AND MANAGEMENT</h2>
          <h3>DEPARTMENT RESULT PREVIEW SHEET</h3>
        </div>

        <div class="result-preview-wrap">
          <table class="wide-result-sheet-table">
            <thead>
              <tr>
                <th>S/N</th>
                <th>Matric Number</th>
                <th class="wide-result-student-name">Student Name</th>
                <th>Total Units</th>
                <th>Total WGP</th>
                <th>GPA</th>
                <?php
                $allCourseCodes = [];
                foreach ($groupedResults as $studentGroup) {
                    foreach ($studentGroup["courses"] as $course) {
                        if (!in_array($course["course_code"], $allCourseCodes, true)) {
                            $allCourseCodes[] = $course["course_code"];
                        }
                    }
                }
                sort($allCourseCodes);
                foreach ($allCourseCodes as $code):
                ?>
                  <th><?php echo htmlspecialchars($code); ?></th>
                <?php endforeach; ?>
              </tr>
            </thead>
            <tbody>
              <?php if (empty($groupedResults)): ?>
                <tr>
                  <td colspan="<?php echo 6 + count($allCourseCodes); ?>" class="empty-row">No prepared preview available yet.</td>
                </tr>
              <?php else: ?>
                <?php $sn = 1; ?>
                <?php foreach ($groupedResults as $studentGroup): ?>
                  <?php
                  $courseLookup = [];
                  foreach ($studentGroup["courses"] as $course) {
                      $courseLookup[$course["course_code"]] = $course;
                  }

                  $gpa = $studentGroup["total_units"] > 0
                      ? ($studentGroup["total_wgp"] / $studentGroup["total_units"])
                      : 0;
                  ?>
                  <tr>
                    <td><?php echo $sn++; ?></td>
                    <td><?php echo htmlspecialchars($studentGroup["matric_number"]); ?></td>
                    <td class="wide-result-student-name"><?php echo htmlspecialchars($studentGroup["student_name"]); ?></td>
                    <td class="wide-result-summary-col"><?php echo (int)$studentGroup["total_units"]; ?></td>
                    <td class="wide-result-summary-col"><?php echo number_format($studentGroup["total_wgp"], 2); ?></td>
                    <td class="wide-result-summary-col"><?php echo number_format($gpa, 2); ?></td>

                    <?php foreach ($allCourseCodes as $code): ?>
                      <td>
                        <?php echo isset($courseLookup[$code]) ? htmlspecialchars($courseLookup[$code]["score"]) : "-"; ?>
                      </td>
                    <?php endforeach; ?>
                  </tr>
                <?php endforeach; ?>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </section>

    </main>
  </div>

  <footer class="portal-footer no-print">
    © 2026 RECTEM Student Portal
    <br>
    Developed by Adebowale Adeyinka Josiah
  </footer>

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
</body>
</html>