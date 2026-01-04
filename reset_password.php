<?php
session_start();

// connect to the database
try {
    $db = new PDO('sqlite:' . __DIR__ . '/app.db'); 
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}

//getting the user to reset from the URL (IDOR)
$user_to_reset = $_GET['user'] ?? null; //if the user doesnt exist return null

if ($_SERVER["REQUEST_METHOD"] == "POST" && $user_to_reset) {
    $new_password = $_POST['new_password'];

    // update the password in the database
    $sql = "UPDATE users SET password = :new_pw WHERE username = :user_name";
    $stmt = $db->prepare($sql);
    
    try {
        $stmt->execute([':new_pw' => $new_password, ':user_name' => $user_to_reset]);
        //check if any row was actually updated
        if ($stmt->rowCount() > 0) {
            $message = "The password for <strong>" . htmlspecialchars($user_to_reset) . "</strong> has been updated!";
        } else {
            // if no rows were updated, the user might not exist
            $message = "Error: User <strong>" . htmlspecialchars($user_to_reset) . "</strong> not found in the database.";
        }
    } catch (PDOException $e) {
        $message = "Database error: " . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="EN">
<head>
    <meta charset="UTF-8">
    <title>Reset Password</title>
    <link rel="stylesheet" href="assets/styles.css">
</head>
<body class="login-page">
    <div class="login-container">
        <?php if (isset($message)): ?>
            <div style="background: #d1fae5; padding: 15px; border-radius: 8px; color: #065f46;">
                <p><?php echo $message; ?></p>
                <a href="login.php" style="color: #065f46; font-weight: bold;">Return to Login</a>
            </div>
        <?php elseif ($user_to_reset): ?>
            <h1>Reset Password</h1>
            <p>Setting new password for: <strong><?php echo htmlspecialchars($user_to_reset); ?></strong></p>
            
            <form method="POST">
                <input type="password" name="new_password" placeholder="Enter new password" required><br>
                <button type="submit" style="background: #ef4444; color: white;">Update Password</button>
            </form>
        <?php else: ?>
            <h1 style="color:red;">Error: No user specified!</h1>
        <?php endif; ?>
    </div>
</body>
</html>