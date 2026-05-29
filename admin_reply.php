<?php
session_start();

// Admin gate (the only thing that protects the endpoint - and CSRF defeats it).
if (!isset($_SESSION["is_admin"]) || $_SESSION["is_admin"] != 1) {
    die("Unauthorized access.");
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $db = new PDO('sqlite:' . __DIR__ . '/app.db');
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $review_id = (int)$_POST['review_id'];
    $reply_content = $_POST['reply_content'];

    // Persist the reply on the specific review.
    $stmt = $db->prepare("UPDATE reviews SET admin_reply = ? WHERE id = ?");
    $stmt->execute([$reply_content, $review_id]);

    // Bounce back to whatever page sent us here.
    header("Location: " . $_SERVER['HTTP_REFERER']);
    exit;
}