<?php
// השרת מקבל את שם המשתמש ישירות מה-URL (מפרמטר GET)
// חולשה חמורה: אין שום טוקן (Token) שמוודא שהבקשה לגיטימית!
$user_to_reset = $_GET['user'] ?? null;

if ($_SERVER["REQUEST_METHOD"] == "POST" && $user_to_reset) {
    $new_password = $_POST['new_password'];
    
    // כאן השרת היה מעדכן את מסד הנתונים. 
    // לצורך הפרויקט, פשוט נציג הודעת הצלחה שמראה שהשתלטנו על החשבון.
    $message = "הסיסמה של המשתמש <strong>" . htmlspecialchars($user_to_reset) . "</strong> עודכנה בהצלחה ל: " . htmlspecialchars($new_password);
}
?>

<!DOCTYPE html>
<html lang="he" dir="rtl">
<head>
    <meta charset="UTF-8">
    <title>איפוס סיסמה</title>
    <link rel="stylesheet" href="assets/styles.css">
</head>
<body class="login-page">
    <div class="login-container">
        <?php if (isset($message)): ?>
            <div style="background: #d1fae5; padding: 15px; border-radius: 8px;">
                <p><?php echo $message; ?></p>
                <a href="login.php">חזור לדף ההתחברות</a>
            </div>
        <?php elseif ($user_to_reset): ?>
            <h1>איפוס סיסמה עבור: <?php echo htmlspecialchars($user_to_reset); ?></h1>
            <p>בחר סיסמה חדשה עבור החשבון.</p>
            
            <form method="POST">
                <input type="password" name="new_password" placeholder="סיסמה חדשה" required>
                <button type="submit" style="background: #ef4444; color: white;">עדכן סיסמה</button>
            </form>
        <?php else: ?>
            <h1 style="color:red;">שגיאה: לא נבחר משתמש לאיפוס</h1>
        <?php endif; ?>
    </div>
</body>
</html>