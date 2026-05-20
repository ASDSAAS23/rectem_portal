<?php
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

$markSql = "UPDATE notifications SET is_read = 1 WHERE (student_id = ? OR audience = 'all') AND is_read = 0";
$markStmt = $conn->prepare($markSql);
$markStmt->bind_param("i", $studentId);
$markStmt->execute();

$notifications = [];
$sql = "SELECT * FROM notifications WHERE student_id = ? OR audience = 'all' ORDER BY created_at DESC";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $studentId);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) $notifications[] = $row;
?>
<!DOCTYPE html>
<html lang="en"><head><meta charset="UTF-8" /><meta name="viewport" content="width=device-width, initial-scale=1.0" />
<title>Notifications</title><link rel="stylesheet" href="../style.css" /><link rel="manifest" href="../manifest.json" /></head>
<body class="portal-bg"><div class="bg-watermark"></div>
<div class="dashboard-shell">
<aside class="student-sidebar" id="studentSidebar">
  <div class="sidebar-brand"><img src="../rectem-logo.png" class="sidebar-brand-logo" alt="RECTEM Logo"><div class="sidebar-brand-text"><h3>RECTEM</h3><p>Student Portal</p></div></div>
  <nav class="sidebar-nav">
    <a href="dashboard.php" class="sidebar-link">Dashboard</a>
    <div class="sidebar-group"><button class="sidebar-dropdown-btn" type="button"><span>Registration</span><span class="dropdown-icon">▼</span></button><div class="sidebar-submenu"><a href="course-registration.php">Course Registration</a><a href="course-records.php">Course Registration Details</a><a href="print-course-form.php">Print Course Form</a></div></div>
    <div class="sidebar-group"><button class="sidebar-dropdown-btn" type="button"><span>Results</span><span class="dropdown-icon">▼</span></button><div class="sidebar-submenu"><a href="results.php">Result Preview</a><a href="result-checker.php">Result Checker</a></div></div>
    <a href="notifications.php" class="sidebar-link active-link">Notifications</a>
    <a href="profile.php" class="sidebar-link">Edit Profile Picture</a>
    <a href="../logout.php" class="sidebar-link logout-link">Logout</a>
  </nav>
</aside>
<div class="sidebar-overlay" id="sidebarOverlay"></div>

<main class="student-main">
<header class="student-topbar">
  <button class="topbar-menu-btn" id="menuToggleBtn" type="button">☰</button>
  <div class="topbar-welcome"><h2>Welcome <span><?php echo htmlspecialchars(explode(" ", $student["full_name"])[0]); ?></span></h2><p>Notifications</p></div>
  <div class="topbar-actions"><button class="notification-btn" type="button">🔔</button><img src="<?php echo htmlspecialchars($profileImage); ?>" class="topbar-profile-img" alt="Profile"></div>
</header>

<section class="page-title-card"><h3>🔔 Notifications</h3><p>Your portal updates, results, registration confirmations, and announcements.</p></section>

<section style="display:flex;flex-direction:column;gap:12px;">
<?php if (count($notifications) === 0): ?>
  <div class="quick-card" style="text-align:center;padding:40px 20px;">
    <div style="font-size:3rem;margin-bottom:12px;">📭</div>
    <h4 style="margin:0 0 6px;">All Caught Up!</h4>
    <p style="color:var(--muted);">You have no notifications at the moment. We'll notify you when something important happens.</p>
  </div>
<?php else: foreach ($notifications as $notification):
  // Determine icon and color based on notification type
  $typeIcon = '📢'; $typeBorder = 'var(--primary)'; $typeLabel = 'General';
  switch ($notification['type'] ?? 'general') {
    case 'result': $typeIcon = '📊'; $typeBorder = '#16a34a'; $typeLabel = 'Result'; break;
    case 'performance_comment': $typeIcon = '🤖'; $typeBorder = '#8b5cf6'; $typeLabel = 'AI Advisor'; break;
    case 'registration': $typeIcon = '✅'; $typeBorder = '#0ea5e9'; $typeLabel = 'Registration'; break;
    case 'consultation': $typeIcon = '💬'; $typeBorder = '#f59e0b'; $typeLabel = 'Consultation'; break;
    case 'profile': $typeIcon = '📸'; $typeBorder = '#ec4899'; $typeLabel = 'Profile'; break;
    default: $typeIcon = '📢'; $typeBorder = 'var(--primary)'; $typeLabel = 'Announcement'; break;
  }
  // Format relative time
  $timestamp = strtotime($notification['created_at']);
  $diff = time() - $timestamp;
  if ($diff < 60) $timeAgo = 'Just now';
  elseif ($diff < 3600) $timeAgo = floor($diff / 60) . ' min' . (floor($diff / 60) > 1 ? 's' : '') . ' ago';
  elseif ($diff < 86400) $timeAgo = floor($diff / 3600) . ' hour' . (floor($diff / 3600) > 1 ? 's' : '') . ' ago';
  elseif ($diff < 604800) $timeAgo = floor($diff / 86400) . ' day' . (floor($diff / 86400) > 1 ? 's' : '') . ' ago';
  else $timeAgo = date('M j, Y', $timestamp);
?>
  <div class="quick-card" style="border-left:4px solid <?php echo $typeBorder; ?>;display:flex;gap:14px;align-items:flex-start;padding:16px 18px;">
    <div style="font-size:1.5rem;min-width:32px;text-align:center;padding-top:2px;"><?php echo $typeIcon; ?></div>
    <div style="flex:1;min-width:0;">
      <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;margin-bottom:4px;">
        <span style="font-size:0.7rem;font-weight:700;text-transform:uppercase;letter-spacing:0.5px;color:<?php echo $typeBorder; ?>;background:<?php echo $typeBorder; ?>15;padding:2px 8px;border-radius:6px;"><?php echo $typeLabel; ?></span>
        <span style="font-size:0.78rem;color:#94a3b8;"><?php echo $timeAgo; ?></span>
      </div>
      <h4 style="margin:0 0 4px;font-size:0.95rem;font-weight:700;color:var(--text);"><?php echo htmlspecialchars($notification["title"]); ?></h4>
      <p style="margin:0;font-size:0.88rem;color:var(--muted);line-height:1.5;"><?php echo htmlspecialchars($notification["message"]); ?></p>
    </div>
  </div>
<?php endforeach; endif; ?>
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
