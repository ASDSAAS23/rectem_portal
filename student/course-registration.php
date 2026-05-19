<?php
session_start();
include("../includes/db.php");
include("../includes/auth_check.php");
include("../includes/web_push.php");
requireStudent();

$studentId = $_SESSION["user_id"];
$message = "";
$messageClass = "";

$studentSql = "SELECT s.*, d.department_name
               FROM students s INNER JOIN departments d ON s.department_id = d.id
               WHERE s.id = ? LIMIT 1";
$studentStmt = $conn->prepare($studentSql);
$studentStmt->bind_param("i", $studentId);
$studentStmt->execute();
$studentResult = $studentStmt->get_result();
if (!$studentResult || $studentResult->num_rows === 0) {
    session_unset(); session_destroy(); header("Location: ../login.php"); exit();
}
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
if ($countResult && $countResult->num_rows > 0) {
    $notificationCount = (int)$countResult->fetch_assoc()["unread_count"];
}

$courseRegistrationStatus = "closed";
$statusSql = "SELECT setting_value FROM portal_settings WHERE setting_key = 'course_registration_status' LIMIT 1";
$statusResult = $conn->query($statusSql);
if ($statusResult && $statusResult->num_rows > 0) {
    $courseRegistrationStatus = $statusResult->fetch_assoc()["setting_value"];
}

$maxUnits = 32;
$levelKey = "max_units_" . strtolower($student["level"]);
$maxRes = $conn->query("SELECT setting_value FROM portal_settings WHERE setting_key = '" . $conn->real_escape_string($levelKey) . "' LIMIT 1");
if ($maxRes && $maxRes->num_rows > 0) {
    $maxUnits = (int)$maxRes->fetch_assoc()["setting_value"];
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    if ($courseRegistrationStatus !== "open") {
        $message = "Course registration is currently closed.";
        $messageClass = "error";
    } else {
        $semester = trim($_POST["semester"] ?? "");
        $selectedCourses = $_POST["courses"] ?? [];
        $sessionName = $student["current_session"] ?: "2024/2025";

        if ($semester === "") {
            $message = "Please select a semester first.";
            $messageClass = "error";
        } elseif (empty($selectedCourses)) {
            $message = "Please select at least one course before submitting.";
            $messageClass = "error";
        } else {
            $totalUnits = 0;
            $validCourseIds = [];

            foreach ($selectedCourses as $courseId) {
                $courseId = (int)$courseId;
                $courseSql = "SELECT * FROM courses WHERE id = ? AND department_id = ? AND level = ? AND semester = ?";
                $courseStmt = $conn->prepare($courseSql);
                $courseStmt->bind_param("iiss", $courseId, $student["department_id"], $student["level"], $semester);
                $courseStmt->execute();
                $courseResult = $courseStmt->get_result();
                if ($courseResult && $courseResult->num_rows === 1) {
                    $course = $courseResult->fetch_assoc();
                    $totalUnits += (int)$course["unit"];
                    $validCourseIds[] = (int)$course["id"];
                }
            }

            if (empty($validCourseIds)) {
                $message = "No valid courses selected.";
                $messageClass = "error";
            } elseif ($totalUnits > $maxUnits) {
                $message = "You have exceeded the maximum allowed units. Remove some courses.";
                $messageClass = "error";
            } else {
                $deleteSql = "DELETE FROM course_registrations WHERE student_id = ? AND session = ? AND semester = ?";
                $deleteStmt = $conn->prepare($deleteSql);
                $deleteStmt->bind_param("iss", $studentId, $sessionName, $semester);
                $deleteStmt->execute();

                foreach ($validCourseIds as $courseId) {
                    $insertSql = "INSERT INTO course_registrations (student_id, course_id, session, semester)
                                  VALUES (?, ?, ?, ?)";
                    $insertStmt = $conn->prepare($insertSql);
                    $insertStmt->bind_param("iiss", $studentId, $courseId, $sessionName, $semester);
                    $insertStmt->execute();
                }

                $updateStudentSql = "UPDATE students SET semester = ? WHERE id = ?";
                $updateStudentStmt = $conn->prepare($updateStudentSql);
                $updateStudentStmt->bind_param("si", $semester, $studentId);
                $updateStudentStmt->execute();

                $courseCount = count($validCourseIds);
                $notifTitle = "✅ Course Registration Successful";
                $notifMessage = "You have successfully registered " . $courseCount . " course" . ($courseCount > 1 ? "s" : "") . " (" . $totalUnits . " units) for " . $semester . ", " . $sessionName . ". You can print your course form from the portal.";
                $notifSql = "INSERT INTO notifications (student_id, title, message, audience, type, sent_via_email, is_read)
                             VALUES (?, ?, ?, 'student', 'registration', 0, 0)";
                $notifStmt = $conn->prepare($notifSql);
                $notifStmt->bind_param("iss", $studentId, $notifTitle, $notifMessage);
                $notifStmt->execute();

                // Send push notification to the student's devices with target URL
                sendPushToStudent($conn, $studentId, $notifTitle, $notifMessage, BASE_PATH . '/student/course-records.php');

                $message = "Course registration submitted successfully.";
                $messageClass = "success";
            }
        }
    }
}

$firstSemesterCourses = [];
$secondSemesterCourses = [];
$courseSql = "SELECT * FROM courses WHERE department_id = ? AND level = ? ORDER BY semester, course_code";
$courseStmt = $conn->prepare($courseSql);
$courseStmt->bind_param("is", $student["department_id"], $student["level"]);
$courseStmt->execute();
$courseResult = $courseStmt->get_result();
while ($row = $courseResult->fetch_assoc()) {
    if ($row["semester"] === "First Semester") $firstSemesterCourses[] = $row;
    if ($row["semester"] === "Second Semester") $secondSemesterCourses[] = $row;
}

$registeredMap = [];
$regSql = "SELECT course_id, semester FROM course_registrations WHERE student_id = ? AND session = ?";
$regStmt = $conn->prepare($regSql);
$sessionNow = $student["current_session"] ?: "2024/2025";
$regStmt->bind_param("is", $studentId, $sessionNow);
$regStmt->execute();
$regResult = $regStmt->get_result();
while ($reg = $regResult->fetch_assoc()) {
    $registeredMap[$reg["semester"]][] = (int)$reg["course_id"];
}
?>
<!DOCTYPE html>
<html lang="en">
<head><meta charset="UTF-8" /><meta name="viewport" content="width=device-width, initial-scale=1.0" />
<title>Course Registration</title><link rel="stylesheet" href="../style.css" /><link rel="manifest" href="../manifest.json" /></head>
<body class="portal-bg"><div class="bg-watermark"></div>
<div class="dashboard-shell">
<aside class="student-sidebar" id="studentSidebar">
<div class="sidebar-brand"><img src="../rectem-logo.png" class="sidebar-brand-logo" alt="RECTEM Logo"><div class="sidebar-brand-text"><h3>RECTEM</h3><p>Student Portal</p></div></div>
<nav class="sidebar-nav">
<a href="dashboard.php" class="sidebar-link">Dashboard</a>
<div class="sidebar-group open"><button class="sidebar-dropdown-btn" type="button"><span>Registration</span><span class="dropdown-icon">▼</span></button>
<div class="sidebar-submenu">
<a href="course-registration.php" class="active-submenu-link">Course Registration</a>
<a href="course-records.php">Course Registration Details</a>
<a href="print-course-form.php">Print Course Form</a>
</div></div>
<a href="notifications.php" class="sidebar-link">Notifications</a>
<a href="profile.php" class="sidebar-link">Edit Profile Picture</a>
<a href="../logout.php" class="sidebar-link logout-link">Logout</a>
</nav></aside>
<div class="sidebar-overlay" id="sidebarOverlay"></div>

<main class="student-main">
<header class="student-topbar">
<button class="topbar-menu-btn" id="menuToggleBtn" type="button">☰</button>
<div class="topbar-welcome"><h2>Welcome <span><?php echo htmlspecialchars(explode(" ", $student["full_name"])[0]); ?></span></h2><p>Course Registration</p></div>
<div class="topbar-actions">
<button class="notification-btn" type="button" title="Notifications" onclick="window.location.href='notifications.php'">🔔<?php if ($notificationCount > 0): ?><span class="notification-badge"><?php echo $notificationCount; ?></span><?php endif; ?></button>
<img src="<?php echo htmlspecialchars($profileImage); ?>" class="topbar-profile-img" alt="Profile">
</div></header>

<section class="page-title-card">
<h3>Course Registration</h3>
<p>Department: <?php echo htmlspecialchars($student["department_name"]); ?> | Level: <?php echo htmlspecialchars($student["level"]); ?> | Session: <?php echo htmlspecialchars($student["current_session"]); ?></p>
</section>

<section class="registration-card">
<form method="POST" action="" id="courseRegistrationForm">
<div class="registration-top-row">
<div class="semester-select-wrap">
<label for="semesterSelect">Select Semester</label>
<select id="semesterSelect" name="semester">
<option value="">-- Select Semester --</option>
<option value="First Semester">First Semester</option>
<option value="Second Semester">Second Semester</option>
</select>
</div></div>

<p class="course-message <?php echo ($courseRegistrationStatus === 'open') ? 'success' : 'error'; ?>">
Course Registration Status: <?php echo strtoupper(htmlspecialchars($courseRegistrationStatus)); ?>
</p>

<p id="courseMessage" class="course-message <?php echo htmlspecialchars($messageClass); ?>">
<?php echo htmlspecialchars($message); ?>
</p>

<div class="table-wrap"><table class="course-table">
<thead><tr><th>S/N</th><th>Course Title</th><th>Course Code</th><th>Unit</th><th>Select</th></tr></thead>
<tbody id="courseTableBody"><tr><td colspan="5" class="empty-row">Select a semester to load courses.</td></tr></tbody>
</table></div>

<div class="unit-summary-grid">
<div class="unit-box"><span class="unit-label">Total Selected Units</span><span class="unit-value" id="totalUnits">0</span></div>
<div class="unit-box"><span class="unit-label">Maximum Allowed Units</span><span class="unit-value" id="maxUnits"><?php echo $maxUnits; ?></span></div>
</div>

<button class="submit-registration-btn" id="submitRegistrationBtn" type="submit" <?php echo ($courseRegistrationStatus !== "open") ? "disabled" : ""; ?>>Submit Course Registration</button>
</form>
</section>
</main></div>
<footer class="portal-footer">© 2026 RECTEM Student Portal<br>Developed by Adebowale Adeyinka Josiah</footer>

<script>
const firstSemesterCourses = <?php echo json_encode($firstSemesterCourses); ?>;
const secondSemesterCourses = <?php echo json_encode($secondSemesterCourses); ?>;
const registeredMap = <?php echo json_encode($registeredMap); ?>;
const maxUnits = <?php echo (int)$maxUnits; ?>;
const semesterSelect = document.getElementById("semesterSelect");
const courseTableBody = document.getElementById("courseTableBody");
const totalUnitsEl = document.getElementById("totalUnits");
const submitBtn = document.getElementById("submitRegistrationBtn");
const courseMessage = document.getElementById("courseMessage");

function renderCourses(semester) {
  let courses = [];
  if (semester === "First Semester") courses = firstSemesterCourses;
  else if (semester === "Second Semester") courses = secondSemesterCourses;

  totalUnitsEl.textContent = "0";
  if (!courses.length) {
    courseTableBody.innerHTML = `<tr><td colspan="5" class="empty-row">No courses available for this semester.</td></tr>`;
    return;
  }
  const checkedCourses = registeredMap[semester] || [];
  courseTableBody.innerHTML = courses.map((course, index) => {
    const isChecked = checkedCourses.includes(Number(course.id)) ? "checked" : "";
    return `<tr>
      <td>${index + 1}</td>
      <td>${course.course_title}</td>
      <td>${course.course_code}</td>
      <td>${course.unit}</td>
      <td><input type="checkbox" class="course-check" name="courses[]" value="${course.id}" data-unit="${course.unit}" ${isChecked} /></td>
    </tr>`;
  }).join("");
  setupCourseCheckboxes();
  calculateUnits();
}
function setupCourseCheckboxes() {
  document.querySelectorAll(".course-check").forEach((checkbox) => checkbox.addEventListener("change", calculateUnits));
}
function calculateUnits() {
  const checkedCourses = document.querySelectorAll(".course-check:checked");
  let totalUnits = 0;
  checkedCourses.forEach((checkbox) => totalUnits += Number(checkbox.dataset.unit));
  totalUnitsEl.textContent = totalUnits;
  if (totalUnits > maxUnits) {
    courseMessage.textContent = "You have exceeded the maximum allowed units. Remove some courses.";
    courseMessage.className = "course-message error";
    submitBtn.disabled = true;
  } else if (totalUnits > 0) {
    courseMessage.textContent = "Course selection is within the allowed unit limit.";
    courseMessage.className = "course-message success";
    if ("<?php echo $courseRegistrationStatus; ?>" === "open") submitBtn.disabled = false;
  } else {
    courseMessage.textContent = "";
    courseMessage.className = "course-message";
    if ("<?php echo $courseRegistrationStatus; ?>" === "open") submitBtn.disabled = false;
  }
}
semesterSelect.addEventListener("change", function () { renderCourses(this.value); });
</script>

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
