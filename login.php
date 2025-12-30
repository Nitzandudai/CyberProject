<?php
/* 1. SESSION FIXATION VULNERABILITY:
   We sanitize the session ID and set it if provided via GET.
*/
if (isset($_GET['PHPSESSID'])) {
    // Keep only alphanumeric characters to prevent "illegal characters" error
    $sid = preg_replace('/[^a-zA-Z0-9]/', '', $_GET['PHPSESSID']);
    if (!empty($sid)) {
        session_id($sid);
    }
}
session_start();

// 1. חיבור למסד הנתונים
try {
    $db = new PDO('sqlite:' . __DIR__ . '/app.db');
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}

$error = "";
$success_msg = "";

// לוגיקת Login
// --- גרסה פגיעה ל-SQL Injection ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['login_submit'])) {
    $username = $_POST["username"];
    $password = $_POST["password"];

    // שים לב: אנחנו משרשרים את המשתנים ישירות לתוך המחרוזת!
    $sql = "SELECT * FROM users WHERE username = '$username' AND password = '$password'";
    
    // הרצה ישירה בלי prepare אמיתי של פרמטרים
    $user = $db->query($sql)->fetch(PDO::FETCH_ASSOC);

    if ($user) {
        $_SESSION["username"] = $user['username'];
        header("Location: home.php");
        exit;
    } else {
        $error = "Invalid username or password";
    }
}

// לוגיקת Forgot Password
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['forgot_submit'])) {
    $email_input = $_POST['email'];

    // בדיקה ב-DB אם האימייל קיים
    $stmt = $db->prepare("SELECT username FROM users WHERE email = :email");
    $stmt->execute([':email' => $email_input]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user) {
        $_SESSION['temp_mail_to'] = $user['username'];
        $_SESSION['temp_mail_address'] = $email_input;
        header("Location: mailbox.php");
        exit;
    } else {
        $error = "Email not found";
    }
}
?>
<!DOCTYPE html>
<html lang="EN">
<head>
    <meta charset="UTF-8">
    <title>login</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/styles.css">
</head>
<body class="login-page">

    <div class="login-container">
        <h1>login</h1>

        <form method="POST">
            <input type="text" name="username" placeholder="username" required><br>
            <input type="password" name="password" placeholder="password" required><br>
            <button type="submit" name="login_submit">login</button>
        </form>

        <div style="margin-top: 15px;">
            <a href="?view=forgot" style="font-size: 0.9em; font-weight: 400; color: black;">Forgot password?</a>
        </div>

        <?php if (isset($_GET['view']) && $_GET['view'] == 'forgot'): ?>
            <div style="margin-top: 20px; padding-top: 20px; border-top: 1px solid #ddd;">
                <h3>Reset Password</h3>
                <form method="POST">
                    <input type="email" name="email" placeholder="enter email" required><br>
                    <button type="submit" name="forgot_submit">send link</button>
                </form>
            </div>
        <?php endif; ?>

        <?php if ($error) echo "<p style='color: #ef4444; margin-top: 10px;'>$error</p>"; ?>
    </div>
</body>
</html>