<?php
include("includes/db.php");
include("includes/matric_helper.php");

$message = "";
$messageClass = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $matricNumber = trim($_POST["matric_number"] ?? "");
    $email = trim($_POST["email"] ?? "");
    $password = trim($_POST["password"] ?? "");
    $confirmPassword = trim($_POST["confirm_password"] ?? "");

    if ($matricNumber === "" || $email === "" || $password === "" || $confirmPassword === "") {
        $message = "Please fill in all fields.";
        $messageClass = "error";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $message = "Please enter a valid email address.";
        $messageClass = "error";
    } elseif ($password !== $confirmPassword) {
        $message = "Passwords do not match.";
        $messageClass = "error";
    } elseif (strlen($password) < 4) {
        $message = "Password must be at least 4 characters.";
        $messageClass = "error";
    } else {
        $recordSql = "SELECT sr.*, d.department_name FROM student_records sr
                      INNER JOIN departments d ON sr.department_id = d.id
                      WHERE sr.matric_number = ? LIMIT 1";
        $recordStmt = $conn->prepare($recordSql);
        $recordStmt->bind_param("s", $matricNumber);
        $recordStmt->execute();
        $recordResult = $recordStmt->get_result();

        if (!$recordResult || $recordResult->num_rows === 0) {
            $message = "Matric number not found in official student records.";
            $messageClass = "error";
        } else {
            $studentRecord = $recordResult->fetch_assoc();
            $detectedDepartment = detectDepartmentFromMatric($matricNumber);

            if ($detectedDepartment === null) {
                $message = "Invalid matric number format or unknown department code.";
                $messageClass = "error";
            } elseif (normalizeDepartmentName($detectedDepartment) !== normalizeDepartmentName($studentRecord["department_name"])) {
                $message = "Matric number department code does not match the official student record.";
                $messageClass = "error";
            } else {
                $checkStudentSql = "SELECT id FROM students WHERE matric_number = ? LIMIT 1";
                $checkStudentStmt = $conn->prepare($checkStudentSql);
                $checkStudentStmt->bind_param("s", $matricNumber);
                $checkStudentStmt->execute();
                $checkStudentResult = $checkStudentStmt->get_result();

                if ($checkStudentResult && $checkStudentResult->num_rows > 0) {
                    $message = "An account already exists for this matric number.";
                    $messageClass = "error";
                } else {
                    $checkEmailSql = "SELECT id FROM students WHERE email = ? LIMIT 1";
                    $checkEmailStmt = $conn->prepare($checkEmailSql);
                    $checkEmailStmt->bind_param("s", $email);
                    $checkEmailStmt->execute();
                    $checkEmailResult = $checkEmailStmt->get_result();

                    if ($checkEmailResult && $checkEmailResult->num_rows > 0) {
                        $message = "This email address is already in use.";
                        $messageClass = "error";
                    } else {
                        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
                        $insertSql = "INSERT INTO students
                            (matric_number, full_name, email, password, department_id, level, semester, current_session, profile_picture, status)
                            VALUES (?, ?, ?, ?, ?, ?, 'First Semester', '2024/2025', NULL, 'active')";
                        $insertStmt = $conn->prepare($insertSql);
                        $insertStmt->bind_param("ssssis",
                            $matricNumber,
                            $studentRecord["full_name"],
                            $email,
                            $hashedPassword,
                            $studentRecord["department_id"],
                            $studentRecord["level"]
                        );

                        if ($insertStmt->execute()) {
                            $message = "Account created successfully. You can now log in.";
                            $messageClass = "success";
                        } else {
                            $message = "Something went wrong while creating the account.";
                            $messageClass = "error";
                        }
                    }
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
  <title>Create Student Account</title>
  <link rel="stylesheet" href="style.css" />
</head>
<body class="portal-bg">
  <div class="bg-watermark"></div>
  <main class="login-page">
    <div class="brand-top">
      <img src="rectem-logo.png" alt="RECTEM Logo" class="brand-logo" />
      <h1 class="brand-title">RECTEM PORTAL</h1>
      <p class="brand-subtitle">Create Student Account</p>
    </div>

    <section class="login-card">
      <div class="card-header">
        <h2>Create Student Account</h2>
        <p>Use your valid matric number to create a portal account</p>
      </div>

      <form class="login-form" method="POST" action="">
        <p class="login-message <?php echo htmlspecialchars($messageClass); ?>">
          <?php echo htmlspecialchars($message); ?>
        </p>

        <div class="form-group">
          <label for="matric_number">Matric Number</label>
          <input type="text" id="matric_number" name="matric_number" placeholder="Enter Matric Number" required value="<?php echo isset($_POST['matric_number']) ? htmlspecialchars($_POST['matric_number']) : ''; ?>" />
        </div>

        <div class="form-group">
          <label for="email">Student Email</label>
          <input type="email" id="email" name="email" placeholder="Enter Email Address" required value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>" />
        </div>

        <div class="form-group">
          <label for="password">Password</label>
          <input type="password" id="password" name="password" placeholder="Create Password" required />
        </div>

        <div class="form-group">
          <label for="confirm_password">Confirm Password</label>
          <input type="password" id="confirm_password" name="confirm_password" placeholder="Confirm Password" required />
        </div>

        <button type="submit" class="login-btn">Create Account</button>

        <p class="create-account-text">Already have an account?</p>
        <a href="login.php" class="create-account-btn">Back to Login</a>
      </form>
    </section>
  </main>

  <footer class="portal-footer">
    © 2026 RECTEM Student Portal
    <br>Developed by Adebowale Adeyinka Josiah
  </footer>
</body>
</html>
