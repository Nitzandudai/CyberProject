<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <link rel="stylesheet" href="ui_theme.css">
    <title>Anan Super Market - Coupon Applied</title>
    <style>
        body {
            background-color: #f2efe6;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            margin: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            height: 100vh;
        }

        .success-container {
            background: #f2efe6;
            padding: 40px 60px;
            border-radius: 12px;
            box-shadow: 0 8px 30px rgba(0,0,0,0.05);
            text-align: center;
            max-width: 600px;
            border: 1px solid #dee2e6;
        }

        .logo-img {
            max-width: 280px; 
            height: auto;
            margin-bottom: 10px;
        }

        h1 { color: #1a1a1a; font-size: 1.8rem; margin-top: 0; }
        p { color: #4a5568; line-height: 1.6; }

        .btn-continue {
            display: inline-block;
            margin-top: 30px;
            padding: 14px 40px;
            background-color: #2563eb;
            color: white;
            text-decoration: none;
            border-radius: 6px;
            font-weight: 600;
            cursor: pointer;
            border: none;
            transition: transform 0.2s;
        }

        .btn-continue:hover {
            transform: scale(1.02);
            background-color: #1d4ed8;
        }
    </style>
</head>
<body>

    <div class="success-container">
        <img src="logo.jpg" alt="Anan Super Market" class="logo-img">
                
        <h1>Coupon Applied!</h1>
        <p>Your exclusive discount code is now registered to your account:</p>
        <div style="font-weight: 900; font-size: 1.8rem; color: #1e40af; background: #f8fafc; padding: 10px 25px; border: 2px dashed #2563eb; display: inline-block; margin: 10px 0; border-radius: 4px; font-family: 'Courier New', monospace; letter-spacing: 2px;">
            ANAN-VIP-2026
        </div>
        
        <div style="background: #f1f5f9; padding: 15px; border-radius: 8px; margin: 25px 0; border-left: 4px solid #2563eb;">
            <p style="margin: 0; font-size: 0.9rem; color: #334155;">
                <strong>System Notice:</strong> Please allow 24-48 hours for the discount to be reflected in your cart totals.
            </p>
        </div>

        <button onclick="closeOrRedirect()" class="btn-continue">
            Complete & Log Out
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