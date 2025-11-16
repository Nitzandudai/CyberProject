<?php
session_start();

// אם לא התחברו – נחזיר אותם לדף ההתחברות
if (!isset($_SESSION["username"])) {
    header("Location: login.php");
    exit;
}

$username = $_SESSION["username"];
?>

<!DOCTYPE html>
<html lang="he">
<head>
    <meta charset="UTF-8">
    <title>דף הבית</title>
</head>
<body style="font-family: Arial; text-align: center; margin-top: 80px;">
    <h1>ברוך הבא, <?php echo htmlspecialchars($username); ?>!</h1>
    <p>נכנסת בהצלחה למערכת 🚀</p>
    <form method="POST" action="logout.php">
        <button>התנתק</button>
    </form>
</body>
</html>
