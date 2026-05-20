<?php
include("../includes/db.php");
include("../includes/auth_check.php");
requireStudent();

$studentId = $_SESSION["user_id"];
$message = "";
$messageClass = "";

$studentSql = "SELECT s.*, d.department_name FROM students s
               INNER JOIN departments d ON s.department_id = d.id
               WHERE s.id = ? LIMIT 1";
$studentStmt = $conn->prepare($studentSql);
$studentStmt->bind_param("i", $studentId);
$studentStmt->execute();
$studentResult = $studentStmt->get_result();
$student = $studentResult->fetch_assoc();

$profileImage = "../profile.jpg";
if (!empty($student["profile_picture"]) && file_exists("../uploads/profile_pictures/" . $student["profile_picture"])) {
    $profileImage = "../uploads/profile_pictures/" . $student["profile_picture"];
}

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_FILES["profile_picture"])) {
    if ($_FILES["profile_picture"]["error"] === 0) {
        $fileName = $_FILES["profile_picture"]["name"];
        $tmpName = $_FILES["profile_picture"]["tmp_name"];
        $fileSize = $_FILES["profile_picture"]["size"];
        $extension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
        $allowed = ["jpg", "jpeg", "png", "webp"];

        if (!in_array($extension, $allowed)) {
            $message = "Only JPG, JPEG, PNG, and WEBP files are allowed.";
            $messageClass = "error";
        } elseif ($fileSize > 2 * 1024 * 1024) {
            $message = "Image size must not be more than 2MB.";
            $messageClass = "error";
        } else {
            $newFileName = "student_" . $studentId . "_" . time() . "." . $extension;
            $destination = "../uploads/profile_pictures/" . $newFileName;

            if (move_uploaded_file($tmpName, $destination)) {
                $updateSql = "UPDATE students SET profile_picture = ? WHERE id = ?";
                $updateStmt = $conn->prepare($updateSql);
                $updateStmt->bind_param("si", $newFileName, $studentId);
                $updateStmt->execute();

                $_SESSION["profile_picture"] = $newFileName;
                $message = "Profile picture uploaded successfully.";
                $messageClass = "success";
                $profileImage = $destination;

                $notifTitle = "📸 Profile Picture Updated";
                $notifMessage = "Your profile picture has been updated successfully. Your new photo will now appear across the portal and on your course registration form.";
                $notifSql = "INSERT INTO notifications (student_id, title, message, audience, type, sent_via_email, is_read)
                             VALUES (?, ?, ?, 'student', 'profile', 0, 0)";
                $notifStmt = $conn->prepare($notifSql);
                $notifStmt->bind_param("iss", $studentId, $notifTitle, $notifMessage);
                $notifStmt->execute();
                
                // Send push notification
                include_once("../includes/web_push.php");
                sendPushToStudent($conn, $studentId, $notifTitle, $notifMessage, BASE_PATH . '/student/profile.php');
            } else {
                $message = "Failed to upload image.";
                $messageClass = "error";
            }
        }
    } else {
        $message = "Please choose an image first.";
        $messageClass = "error";
    }
}
?>
<!DOCTYPE html>
<html><head><title>Student Profile</title><link rel="stylesheet" href="../style.css"><link rel="manifest" href="../manifest.json"></head>
<body class="portal-bg">
  <div class="page-title-card" style="max-width:700px;margin:40px auto;">
    <h3>Profile Picture</h3>
    <p>Upload or change your passport photograph.</p>

    <div style="text-align:center;margin:20px 0;">
      <img src="<?php echo htmlspecialchars($profileImage); ?>" alt="Profile"
           style="width:130px;height:140px;object-fit:cover;border-radius:14px;border:2px solid #dbe5ef;">
    </div>

    <p class="course-message <?php echo htmlspecialchars($messageClass); ?>">
      <?php echo htmlspecialchars($message); ?>
    </p>

    <form method="POST" enctype="multipart/form-data">
      <input type="file" name="profile_picture" accept=".jpg,.jpeg,.png,.webp" class="admin-file-input">
      <button type="submit" class="submit-registration-btn">Upload Picture</button>
    </form>

    <a href="dashboard.php" class="create-account-btn" style="margin-top:14px;">Back to Dashboard</a>
  </div>
  <script src="../assets/js/notifications.js"></script>
</body></html>
