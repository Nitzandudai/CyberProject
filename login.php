<?php
//users and passwords list (instead of database)
$users = [
    "carlos" => "1234",
    "admin" => "admin123",
    "noa"   => "pass1",
    "dan"   => "qwerty",
    "yael"  => "abcd"
];

// checking if the form was submitted
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = $_POST["username"];
    $password = $_POST["password"];

    if (!array_key_exists($username, $users)) {
        $error = "Invalid username";
    } elseif ($users[$username] !== $password) {
        $error = "Invalid password";
    } else {
        //Successful login – save the name in session and go to the next page
        session_start();
        $_SESSION["username"] = $username;
        header("Location: home.php");
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="EN">
<head>
    <meta charset="UTF-8">
    <title>login</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/styles.css?v=2">

   <!-- <link rel="stylesheet" href="assets/styles.css">  -->
</head>
<body class="login-page">

    <div class="login-container">
        <h1>login</h1>

        <form method="POST">
            <input type="text" name="username" placeholder="username" required><br>
            <input type="password" name="password" placeholder="password" required><br>
            <button type="submit">login</button>
        </form>

        <?php if (isset($error)) echo "<p class='error'>$error</p>"; ?>
    </div>

</body>
</html>
