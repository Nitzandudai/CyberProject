<?php
session_start();

// בדיקת הרשאות אדמין (חיוני כדי שהמתקפה תהיה הגיונית)[cite: 12]
if (!isset($_SESSION["is_admin"]) || $_SESSION["is_admin"] != 1) {
    die("Unauthorized access.");
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $db = new PDO('sqlite:' . __DIR__ . '/app.db');
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $review_id = (int)$_POST['review_id'];
    $reply_content = $_POST['reply_content'];

    // עדכון התגובה בטבלה עבור הביקורת הספציפית[cite: 1]
    $stmt = $db->prepare("UPDATE reviews SET admin_reply = ? WHERE id = ?");
    $stmt->execute([$reply_content, $review_id]);

    // חזרה לדף המוצר (נצטרך לדעת את ה-ID של המוצר, או פשוט לחזור לדף הקודם)
    header("Location: " . $_SERVER['HTTP_REFERER']);
    exit;
}