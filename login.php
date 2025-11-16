<?php
// רשימת משתמשים וסיסמאות (במקום מסד נתונים)
$users = [
    "carlos" => "1234",
    "admin" => "admin123",
    "noa" => "pass1",
    "dan" => "qwerty",
    "yael" => "abcd"
];

// בדיקה אם נשלח טופס
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = $_POST["username"];
    $password = $_POST["password"];

    if (!array_key_exists($username, $users)) {
        $error = "Invalid username";
    } elseif ($users[$username] !== $password) {
        $error = "Invalid password";
    } else {
        // התחברות מוצלחת – נשמור את השם ב-session ונעבור לדף הבא
        session_start();
        $_SESSION["username"] = $username;
        header("Location: home.php");
        exit;
    }
}
?>

<!DOCTYPE html>
<html lang="he">
<head>
    <meta charset="UTF-8">
    <title>התחברות</title>
    <style>
        body { font-family: Arial; text-align: center; margin-top: 80px; }
        input, button { padding: 8px; margin: 5px; }
        p { color: red; }
    </style>
</head>
<body>
    <h1>דף התחברות</h1>

    <form method="POST">
        <input type="text" name="username" placeholder="שם משתמש" required><br>
        <input type="password" name="password" placeholder="סיסמה" required><br>
        <button type="submit">התחבר</button>
    </form>

    <?php if (isset($error)) echo "<p>$error</p>"; ?>
</body>
</html>
