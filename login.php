<?php
/* 1. SESSION FIXATION VULNERABILITY (KEEPING IT AS IS):
   The attacker can still set the session ID via URL.
*/
if (isset($_GET['PHPSESSID'])) {
    $sid = preg_replace('/[^a-zA-Z0-9]/', '', $_GET['PHPSESSID']);
    if (!empty($sid)) {
        session_id($sid);
    }
}
session_start();

// 1. Database Connection
try {
    $db = new PDO('sqlite:' . __DIR__ . '/app.db');
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}

$error = "";
$success_msg = "";

// 2. NEW: Registration Logic
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['register_submit'])) {
    $reg_username = $_POST["reg_username"];
    $reg_email = $_POST["reg_email"];
    $reg_password = $_POST["reg_password"];

    // Check if username already exists
    $check_stmt = $db->prepare("SELECT COUNT(*) FROM users WHERE username = :username");
    $check_stmt->execute([':username' => $reg_username]);
    
    if ($check_stmt->fetchColumn() > 0) {
        // The error message you requested
        $error = "Username already exists in the system";
    } else {
        // Insert new user into the database
        $insert_sql = "INSERT INTO users (username, email, password) VALUES (:u, :e, :p)";
        $insert_stmt = $db->prepare($insert_sql);
        $result = $insert_stmt->execute([
            ':u' => $reg_username,
            ':e' => $reg_email,
            ':p' => $reg_password
        ]);

        if ($result) {
            $success_msg = "Registration complete! You can now login.";
        }
    }
}

// 3. Login logic (STILL VULNERABLE TO SQL INJECTION)
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['login_submit'])) {
    $username = $_POST["username"];
    $password = $_POST["password"];

    // This is the SQL Injection vulnerability you wanted to keep
    $sql = "SELECT * FROM users WHERE username = '$username' AND password = '$password'";
    
    $user = $db->query($sql)->fetch(PDO::FETCH_ASSOC);

    if ($user) {
        $_SESSION["username"] = $user['username'];
        
        // בדיקה אם המשתמש הוא אדמין - אפשר לפי שם משתמש או לפי עמודה ב-DB
        if ($user['username'] === 'admin') { 
            $_SESSION["is_admin"] = 1; 
        } else {
            $_SESSION["is_admin"] = 0;
        }
        
        header("Location: home.php");
        exit;
    }
}

// 4. Forgot password logic
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['forgot_submit'])) {
    $email_input = $_POST['email'];
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
    <title>Anan Super Market - Auth</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/styles.css">
</head>
<body class="login-page">

    <div class="login-container">
        <h1><?php echo (isset($_GET['view']) && $_GET['view'] == 'register') ? 'Register' : 'Login'; ?></h1>

        <?php if ($success_msg): ?>
            <p style="color: #22c55e; margin-bottom: 15px; font-weight: 600;"><?php echo $success_msg; ?></p>
        <?php endif; ?>

        <?php if (isset($_GET['view']) && $_GET['view'] == 'register'): ?>
            <form method="POST">
                <input type="text" name="reg_username" placeholder="choose username" required><br>
                <input type="email" name="reg_email" placeholder="email address" required><br>
                <input type="password" name="reg_password" placeholder="password" required><br>
                <button type="submit" name="register_submit">Create Account</button>
            </form>
            <div style="margin-top: 15px;">
                <a href="login.php" style="font-size: 0.85em; color: #475569;">Back to Login</a>
            </div>

        <?php else: ?>
            <form method="POST">
                <input type="text" name="username" placeholder="username" required><br>
                <input type="password" name="password" placeholder="password" required><br>
                <button type="submit" name="login_submit">Login</button>
            </form>

            <div style="margin-top: 15px; display: flex; justify-content: space-between; gap: 10px;">
                <a href="?view=register" style="font-size: 0.85em; color: #475569;">New? Register here</a>
                <a href="?view=forgot" style="font-size: 0.85em; color: #475569;">Forgot password?</a>
            </div>

            <?php if (isset($_GET['view']) && $_GET['view'] == 'forgot'): ?>
                <div style="margin-top: 20px; padding-top: 20px; border-top: 1px solid #e2e8f0;">
                    <h3 style="font-size: 1rem; margin-bottom: 10px;">Reset Password</h3>
                    <form method="POST">
                        <input type="email" name="email" placeholder="enter email" required><br>
                        <button type="submit" name="forgot_submit">Send Link</button>
                    </form>
                </div>
            <?php endif; ?>
        <?php endif; ?>

        <?php if ($error) echo "<p style='color: #ef4444; margin-top: 12px; font-weight: 600;'>$error</p>"; ?>
    </div>
</body>
</html>