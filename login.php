<?php
session_start();

// רשימת המשתמשים המעודכנת (עם אימייל לצורך הניסוי)
$users = [
    "carlos" => ["pw" => "1234", "email" => "carlos@example.com"],
    "admin"  => ["pw" => "admin123", "email" => "admin@example.com"],
    "noa"    => ["pw" => "pass1", "email" => "noa@example.com"],
    "dan"    => ["pw" => "qwerty", "email" => "dan@example.com"]
];

$error = "";
$success_msg = "";

// לוגיקת Login
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['login_submit'])) {
    $username = $_POST["username"];
    $password = $_POST["password"];

    if (isset($users[$username]) && $users[$username]["pw"] === $password) {
        $_SESSION["username"] = $username;
        header("Location: home.php");
        exit;
    } else {
        $error = "Invalid username or password";
    }
}

// לוגיקת Forgot Password (החלק הפגיע)
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['forgot_submit'])) {
    $email_input = $_POST['email'];
    foreach ($users as $name => $data) {
        if ($data['email'] === $email_input) {
            // שמירת המידע ב-Session כדי שדף המייל יוכל להציג אותו
            $_SESSION['temp_mail_to'] = $name;
            $_SESSION['temp_mail_address'] = $email_input;
            
            // מעבר לדף ה"מייל" המזויף
            header("Location: mailbox.php");
            exit;
        }
    }
    if (!$user_found) $error = "Email not found";
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
            <div style="margin-top: 20px; padding-top: 20px; border-top: 1px solid var(--border);">
                <h3>Reset Password</h3>
                <form method="POST">
                    <input type="email" name="email" placeholder="enter email" required><br>
                    <button type="submit" name="forgot_submit">send link</button>
                </form>
            </div>
        <?php endif; ?>

        <?php if ($error) echo "<p style='color: #ef4444; margin-top: 10px;'>$error</p>"; ?>
        <?php if ($success_msg) echo "<p style='color: #22c55e; margin-top: 10px;'>$success_msg</p>"; ?>
    </div>

</body>
</html>