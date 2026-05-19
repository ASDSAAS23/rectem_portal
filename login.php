<?php
session_start();
include("includes/db.php");
include("includes/ai_helper.php");

$message = "";
$messageClass = "";

// Capture redirect destination (from push notification click)
$redirect = "";
if (isset($_GET['redirect'])) {
    $redirect = $_GET['redirect'];
} elseif (isset($_POST['redirect'])) {
    $redirect = $_POST['redirect'];
}
// Sanitize: only allow internal redirects within same host
if ($redirect !== '') {
    $parsedHost = parse_url($redirect, PHP_URL_HOST);
    // If it has a host, it must match the current server host. If no host, it's a relative path (allowed)
    if ($parsedHost && $parsedHost !== $_SERVER['HTTP_HOST']) {
        $redirect = '';
    }
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $userId = trim($_POST["user_id"] ?? "");
    $password = trim($_POST["password"] ?? "");

    if ($userId === "" || $password === "") {
        $message = "Please enter both Matric Number / Staff ID and Password.";
        $messageClass = "error";
    } else {
        if (isStudentMatricPattern($userId)) {
            $studentSql = "SELECT * FROM students WHERE matric_number = ? LIMIT 1";
            $studentStmt = $conn->prepare($studentSql);
            $studentStmt->bind_param("s", $userId);
            $studentStmt->execute();
            $studentResult = $studentStmt->get_result();

            if (!$studentResult || $studentResult->num_rows === 0) {
                $message = "Matric number not found.";
                $messageClass = "error";
            } else {
                $student = $studentResult->fetch_assoc();
                $passwordValid = false;

                if ($password === $student["password"]) {
                    $passwordValid = true;
                } elseif (password_verify($password, $student["password"])) {
                    $passwordValid = true;
                }

                if (!$passwordValid) {
                    $message = "Incorrect password.";
                    $messageClass = "error";
                } else {
                    $_SESSION["user_id"] = $student["id"];
                    $_SESSION["user_role"] = "student";
                    $_SESSION["full_name"] = $student["full_name"];
                    $_SESSION["matric_number"] = $student["matric_number"];
                    $_SESSION["department_id"] = $student["department_id"];
                    $_SESSION["email"] = $student["email"];
                    $_SESSION["profile_picture"] = $student["profile_picture"];

                    $updateLoginSql = "UPDATE students SET last_login = NOW() WHERE id = ?";
                    $updateLoginStmt = $conn->prepare($updateLoginSql);
                    $updateLoginStmt->bind_param("i", $student["id"]);
                    $updateLoginStmt->execute();

                    // Redirect to target page or default dashboard
                    if ($redirect !== '' && str_contains($redirect, '/student/')) {
                        header("Location: " . $redirect);
                    } else {
                        header("Location: student/dashboard.php");
                    }
                    exit();
                }
            }
        } else {
            $adminSql = "SELECT * FROM admins WHERE staff_id = ? LIMIT 1";
            $adminStmt = $conn->prepare($adminSql);
            $adminStmt->bind_param("s", $userId);
            $adminStmt->execute();
            $adminResult = $adminStmt->get_result();

            if (!$adminResult || $adminResult->num_rows === 0) {
                $message = "Staff ID not found.";
                $messageClass = "error";
            } else {
                $admin = $adminResult->fetch_assoc();
                $passwordValid = false;

                if ($password === $admin["password"]) {
                    $passwordValid = true;
                } elseif (password_verify($password, $admin["password"])) {
                    $passwordValid = true;
                }

                if (!$passwordValid) {
                    $message = "Incorrect password.";
                    $messageClass = "error";
                } else {
                    $_SESSION["user_id"] = $admin["id"];
                    $_SESSION["user_role"] = "admin";
                    $_SESSION["full_name"] = $admin["full_name"];
                    $_SESSION["staff_id"] = $admin["staff_id"];
                    $_SESSION["department_id"] = $admin["department_id"];
                    $_SESSION["email"] = $admin["email"];

                    // Redirect to target page or default admin dashboard
                    if ($redirect !== '' && str_contains($redirect, '/admin/')) {
                        header("Location: " . $redirect);
                    } else {
                        header("Location: admin/dashboard.php");
                    }
                    exit();
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
  <title>RECTEM Portal Login</title>
  <link rel="stylesheet" href="style.css" />
</head>
<body class="portal-bg">
  <div class="bg-watermark"></div>

  <main class="login-page">
    <div class="brand-top">
      <img src="rectem-logo.png" alt="RECTEM Logo" class="brand-logo" />
      <h1 class="brand-title">RECTEM PORTAL</h1>
      <p class="brand-subtitle">Student and Department Admin Login</p>
    </div>

    <section class="login-card">
      <div class="card-header">
        <h2>Portal Login</h2>
        <p>Sign in with your Matric Number or Staff ID</p>
      </div>

      <form class="login-form" method="POST" action="">
        <input type="hidden" name="redirect" value="<?php echo htmlspecialchars($redirect); ?>">
        <p class="login-message <?php echo htmlspecialchars($messageClass); ?>">
          <?php echo htmlspecialchars($message); ?>
        </p>

        <div class="form-group">
          <label for="user_id">Matric Number / Staff ID</label>
          <input
            type="text"
            id="user_id"
            name="user_id"
            placeholder="Enter Matric Number or Staff ID"
            required
            value="<?php echo isset($_POST['user_id']) ? htmlspecialchars($_POST['user_id']) : ''; ?>"
          />
        </div>

        <div class="form-group">
          <label for="password">Password</label>
          <input
            type="password"
            id="password"
            name="password"
            placeholder="Enter Password"
            required
          />
        </div>

        <button type="submit" class="login-btn">Login</button>

        <p class="create-account-text">Don't have a student account?</p>

        <a href="create-account.php" class="create-account-btn">
          Create Account
        </a>
      </form>
    </section>
  </main>

  <footer class="portal-footer">
    © 2026 RECTEM Student Portal
    <br>
    Developed by Adebowale Adeyinka Josiah
  </footer>
</body>
</html>