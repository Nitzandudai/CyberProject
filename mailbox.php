<?php session_start(); ?>
<!DOCTYPE html>
<html lang="EN">
<head>
    <meta charset="UTF-8">
    <title>FakeMail - Inbox</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; background: #f2f2f2; margin: 0; padding: 20px; }
        .mailbox-container { max-width: 800px; margin: 0 auto; background: white; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); overflow: hidden; }
        .header { background: #ea4335; color: white; padding: 15px; font-weight: bold; font-size: 1.2em; }
        .email-item { padding: 20px; border-bottom: 1px solid #eee; cursor: pointer; display: flex; justify-content: space-between; }
        .email-item:hover { background: #f9f9f9; }
        .sender { font-weight: 600; color: #444; }
        .subject { color: #666; flex-grow: 1; margin-left: 20px; }
        .time { color: #999; font-size: 0.8em; }
        .content-box { padding: 30px; line-height: 1.6; }
        .reset-btn { display: inline-block; padding: 10px 20px; background: #2563eb; color: white; text-decoration: none; border-radius: 5px; margin-top: 15px; }
    </style>
</head>
<body>

<div class="mailbox-container">
    <div class="header">FakeMail Inbox</div>
    
    <?php if (isset($_SESSION['temp_mail_to'])): ?>
        <div class="email-item">
            <span class="sender">Security System</span>
            <span class="subject">Password Reset Request for <?php echo $_SESSION['temp_mail_to']; ?></span>
            <span class="time">Just now</span>
        </div>
        
        <div class="content-box">
            <p>Hi <strong><?php echo $_SESSION['temp_mail_to']; ?></strong>,</p>
            <p>We received a request to reset your password for your account (<?php echo $_SESSION['temp_mail_address']; ?>).</p>
            <p>Click the button below to set a new password:</p>
            
            <a href="reset_password.php?user=<?php echo $_SESSION['temp_mail_to']; ?>" class="reset-btn">
                Reset My Password
            </a>
            
            <p style="color: #999; font-size: 0.8em; margin-top: 30px;">
                If you didn't request this, please ignore this email.
            </p>
        </div>
    <?php else: ?>
        <div style="padding: 40px; text-align: center; color: #999;">
            Your inbox is empty.
        </div>
    <?php endif; ?>
</div>

</body>
</html>