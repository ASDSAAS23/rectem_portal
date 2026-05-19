<?php
session_start();
include("../includes/db.php");
include("../includes/auth_check.php");
include("../includes/ai_helper.php");
requireAdmin();

$adminId = $_SESSION["user_id"];
$message = "";
$messageClass = "";

/* =========================
   GET ADMIN INFO
========================= */
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
$adminDepartmentId = $admin["department_id"];
$adminDepartmentName = $admin["department_name"] ?? "Unknown Department";
$adminInitial = strtoupper(substr($admin["full_name"], 0, 1));

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["generate_preview"])) {
    $level = trim($_POST["level"] ?? "");
    $sessionName = trim($_POST["session"] ?? "");
    $semester = trim($_POST["semester"] ?? "");

    if ($level === "" || $sessionName === "" || $semester === "") {
        $message = "Please select level, session, and semester.";
        $messageClass = "error";
    } elseif (!isset($_FILES["result_file"]) || $_FILES["result_file"]["error"] !== 0) {
        $message = "Please upload a CSV file.";
        $messageClass = "error";
    } else {
        $tmpName = $_FILES["result_file"]["tmp_name"];
        $fileName = $_FILES["result_file"]["name"];
        $extension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

        if ($extension !== "csv") {
            $message = "Only CSV upload is supported for now.";
            $messageClass = "error";
        } else {
            $handle = fopen($tmpName, "r");

            if ($handle === false) {
                $message = "Unable to read uploaded file.";
                $messageClass = "error";
            } else {
                $headers = fgetcsv($handle);

                if (!$headers) {
                    $message = "CSV file is empty.";
                    $messageClass = "error";
                } else {
                    $matricIndex = -1;

                    foreach ($headers as $index => $header) {
                        if (isMatricHeader($header)) {
                            $matricIndex = $index;
                            break;
                        }
                    }

                    if ($matricIndex === -1) {
                        $message = "CSV must contain a matric number column. Example names: MATRIC_NUMBER, Matric No, MatricNumber.";
                        $messageClass = "error";
                    } else {
                        $courseSql = "SELECT * FROM courses
                                      WHERE department_id = ? AND level = ? AND semester = ?";
                        $courseStmt = $conn->prepare($courseSql);
                        $courseStmt->bind_param("iss", $adminDepartmentId, $level, $semester);
                        $courseStmt->execute();
                        $courseResult = $courseStmt->get_result();

                        $courseMap = [];
                        while ($course = $courseResult->fetch_assoc()) {
                            $courseMap[strtoupper(trim($course["course_code"]))] = $course;
                        }

                        if (empty($courseMap)) {
                            $message = "No courses found for this department, level, and semester.";
                            $messageClass = "error";
                        } else {
                            $previewRows = [];
                            $matchedStudentsSet = [];
                            $unmatchedMatrics = [];
                            $unknownHeaders = [];
                            $invalidScores = [];
                            $duplicateMatrics = [];
                            $ignoredUnregistered = [];
                            $seenMatricRows = [];
                            $totalRows = 0;

                            $ignoredHeaderNames = [
                                "name","studentname","fullname","sex","gender","remark","remarks",
                                "classofdip","gpa","cgpa","wgp1st","wgp2nd","ctuc","cwgp","tcu1st","tcu2nd","sn","sno","index"
                            ];

                            foreach ($headers as $hIndex => $rawHeader) {
                                if ($hIndex === $matricIndex) {
                                    continue;
                                }

                                $normalized = cleanHeaderName($rawHeader);
                                $headerCode = strtoupper(trim($rawHeader));

                                if ($normalized === "" || in_array($normalized, $ignoredHeaderNames, true)) {
                                    continue;
                                }

                                if (!isset($courseMap[$headerCode])) {
                                    $unknownHeaders[] = $rawHeader;
                                }
                            }

                            while (($row = fgetcsv($handle)) !== false) {
                                $totalRows++;

                                $matricNumber = isset($row[$matricIndex]) ? trim($row[$matricIndex]) : "";

                                if ($matricNumber === "") {
                                    continue;
                                }

                                if (in_array($matricNumber, $seenMatricRows, true)) {
                                    $duplicateMatrics[] = $matricNumber;
                                } else {
                                    $seenMatricRows[] = $matricNumber;
                                }

                                $studentSql = "SELECT s.*, d.department_name
                                               FROM students s
                                               INNER JOIN departments d ON s.department_id = d.id
                                               WHERE s.matric_number = ? AND s.department_id = ? AND s.level = ?
                                               LIMIT 1";
                                $studentStmt = $conn->prepare($studentSql);
                                $studentStmt->bind_param("sis", $matricNumber, $adminDepartmentId, $level);
                                $studentStmt->execute();
                                $studentResult = $studentStmt->get_result();

                                if (!$studentResult || $studentResult->num_rows === 0) {
                                    $unmatchedMatrics[] = $matricNumber;
                                    continue;
                                }

                                $student = $studentResult->fetch_assoc();
                                $matchedStudentsSet[] = $student["matric_number"];

                                foreach ($headers as $colIndex => $rawHeader) {
                                    if ($colIndex === $matricIndex) {
                                        continue;
                                    }

                                    $courseCode = strtoupper(trim($rawHeader));

                                    if (!isset($courseMap[$courseCode])) {
                                        continue;
                                    }

                                    $rawScore = isset($row[$colIndex]) ? trim($row[$colIndex]) : "";

                                    if ($rawScore === "") {
                                        continue;
                                    }

                                    if (!is_numeric($rawScore)) {
                                        $invalidScores[] = $matricNumber . " - " . $courseCode . " (" . $rawScore . ")";
                                        continue;
                                    }

                                    $score = (float)$rawScore;

                                    if ($score < 0 || $score > 100) {
                                        $invalidScores[] = $matricNumber . " - " . $courseCode . " (" . $rawScore . ")";
                                        continue;
                                    }

                                    $courseInfo = $courseMap[$courseCode];

                                    $regCheckSql = "SELECT id
                                                    FROM course_registrations
                                                    WHERE student_id = ? AND course_id = ? AND session = ? AND semester = ?
                                                    LIMIT 1";
                                    $regCheckStmt = $conn->prepare($regCheckSql);
                                    $regCheckStmt->bind_param(
                                        "iiss",
                                        $student["id"],
                                        $courseInfo["id"],
                                        $sessionName,
                                        $semester
                                    );
                                    $regCheckStmt->execute();
                                    $regCheckResult = $regCheckStmt->get_result();

                                    if (!$regCheckResult || $regCheckResult->num_rows === 0) {
                                        $ignoredUnregistered[] = $matricNumber . " - " . $courseCode;
                                        continue;
                                    }

                                    $gradeData = calculateGradeDetails($score);

                                    $previewRows[] = [
                                        "student_id" => $student["id"],
                                        "student_name" => $student["full_name"],
                                        "matric_number" => $student["matric_number"],
                                        "course_id" => $courseInfo["id"],
                                        "course_code" => $courseInfo["course_code"],
                                        "course_title" => $courseInfo["course_title"],
                                        "course_unit" => $courseInfo["unit"],
                                        "score" => $score,
                                        "grade" => $gradeData["grade"],
                                        "grade_point" => $gradeData["grade_point"],
                                        "remark" => $gradeData["remark"],
                                        "session" => $sessionName,
                                        "semester" => $semester,
                                        "level" => $level
                                    ];
                                }
                            }

                            fclose($handle);

                            $aiStats = [
                                "preview_count" => count($previewRows),
                                "matched_students" => array_values(array_unique($matchedStudentsSet)),
                                "unmatched_matrics" => array_values(array_unique($unmatchedMatrics)),
                                "unknown_headers" => array_values(array_unique($unknownHeaders)),
                                "invalid_scores" => array_values(array_unique($invalidScores)),
                                "duplicate_matrics" => array_values(array_unique($duplicateMatrics)),
                                "ignored_unregistered" => array_values(array_unique($ignoredUnregistered))
                            ];

                            $_SESSION["result_preview_rows"] = $previewRows;
                            $_SESSION["result_preview_meta"] = [
                                "department" => $adminDepartmentName,
                                "level" => $level,
                                "session" => $sessionName,
                                "semester" => $semester,
                                "total_rows" => $totalRows,
                                "matched_students" => count(array_unique($matchedStudentsSet))
                            ];
                            $_SESSION["result_preview_ai"] = generateUploadAssistantSummary($aiStats);

                            if (count($previewRows) > 0) {
                                header("Location: result-preview.php");
                                exit();
                            } else {
                                $message = "No valid uploaded results matched registered courses for students in this department.";
                                $messageClass = "error";
                            }
                        }
                    }
                }

                if (is_resource($handle)) {
                    fclose($handle);
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Admin Upload Results</title>
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
        <a href="upload-results.php" class="sidebar-link active-link">Upload Results</a>
        <a href="result-preview.php" class="sidebar-link">Result Preview Sheet</a>
        <a href="notifications.php" class="sidebar-link">Send Notification</a>
        <a href="change-password.php" class="sidebar-link">Change Password</a>
        <a href="../logout.php" class="sidebar-link logout-link">Logout</a>
      </nav>
    </aside>

    <div class="sidebar-overlay" id="sidebarOverlay"></div>

    <main class="student-main">
      <header class="student-topbar">
        <button class="topbar-menu-btn" id="menuToggleBtn" type="button">☰</button>

        <div class="topbar-welcome">
          <h2>Upload Results</h2>
          <p>Department: <?php echo htmlspecialchars($adminDepartmentName); ?></p>
        </div>

        <div class="topbar-actions">
          <div class="topbar-profile-img" style="display:grid;place-items:center;background:#e2e8f0;color:#334155;font-weight:800;">
            <?php echo $adminInitial; ?>
          </div>
        </div>
      </header>

      <section class="page-title-card admin-hero-card">
        <div class="admin-hero-text">
          <h3>Upload Result Sheet</h3>
          <p>The intelligent upload assistant will analyse the file and show likely issues before results are sent.</p>
        </div>
      </section>

      <section class="registration-card">
        <h3 class="admin-section-title">Upload Setup</h3>
        <p class="admin-section-subtitle">Select level, session, semester, then upload the prepared marks sheet.</p>

        <form method="POST" enctype="multipart/form-data">
          <div class="registration-top-row">
            <div class="semester-select-wrap">
              <label for="uploadResultLevel">Level</label>
              <select id="uploadResultLevel" name="level">
                <option value="">-- Select Level --</option>
                <option value="ND1">ND1</option>
                <option value="ND2">ND2</option>
                <option value="HND1">HND1</option>
                <option value="HND2">HND2</option>
              </select>
            </div>

            <div class="semester-select-wrap">
              <label for="uploadResultSession">Academic Session</label>
              <select id="uploadResultSession" name="session">
                <option value="2024/2025">2024/2025</option>
                <option value="2025/2026">2025/2026</option>
              </select>
            </div>
          </div>

          <div class="registration-top-row">
            <div class="semester-select-wrap">
              <label for="uploadResultSemester">Semester</label>
              <select id="uploadResultSemester" name="semester">
                <option value="First Semester">First Semester</option>
                <option value="Second Semester">Second Semester</option>
              </select>
            </div>

            <div class="semester-select-wrap">
              <label for="resultWideFile">Upload File</label>
              <input type="file" id="resultWideFile" name="result_file" accept=".csv" class="admin-file-input">
            </div>
          </div>

          <div class="page-title-card" style="margin-top:16px;">
            <h3 style="margin:0 0 8px;">Accepted Upload Format</h3>
            <p style="margin:0;">One column must contain the matric number. It can be written in flexible ways such as MATRIC_NUMBER, Matric No, Matric Number, or matric_no.</p>
            <p style="margin:12px 0 0;">The remaining valid course code columns should look like COM121, COM122, GNS102, and so on.</p>
          </div>

          <p class="course-message <?php echo htmlspecialchars($messageClass); ?>">
            <?php echo htmlspecialchars($message); ?>
          </p>

          <div class="admin-upload-actions">
            <button class="submit-registration-btn" type="submit" name="generate_preview">
              Generate Preview
            </button>
          </div>
        </form>
      </section>

    </main>
  </div>

  <footer class="portal-footer">
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