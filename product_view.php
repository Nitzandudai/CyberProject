<?php
session_start();
if (!isset($_SESSION["username"])) { header("Location: login.php"); exit; }

$db = new PDO('sqlite:' . __DIR__ . '/app.db');
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$product_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// שליפת פרטי המוצר
$stmt = $db->prepare("SELECT * FROM products WHERE id = ?");
$stmt->execute([$product_id]);
$product = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$product) { die("Product not found."); }

// לוגיקת הוספת ביקורת
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["submit_review"])) {
    $user = $_SESSION["username"];
    $rating = (int)$_POST["rating"];
    $content = $_POST["content"]; // לא מבצעים Sanitization בכוונה!

    $ins = $db->prepare("INSERT INTO reviews (product_id, username, rating, content) VALUES (?, ?, ?, ?)");
    $ins->execute([$product_id, $user, $rating, $content]);
    header("Location: product_view.php?id=$product_id");
    exit;
}

// שליפת ביקורות
$revStmt = $db->prepare("SELECT * FROM reviews WHERE product_id = ? ORDER BY date DESC");
$revStmt->execute([$product_id]);
$reviews = $revStmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <link rel="stylesheet" href="assets/styles.css">
    <title><?php echo htmlspecialchars($product['name']); ?></title>
    <style>
        .review-card { border: 1px solid #ddd; padding: 15px; margin-top: 10px; border-radius: 8px; background: #fff; }
        .rating-stars { color: #f59e0b; font-weight: bold; }
        .review-form { background: #f8fafc; padding: 20px; border-radius: 12px; margin-bottom: 30px; }
        textarea { width: 100%; padding: 10px; border-radius: 8px; border: 1px solid #ccc; margin-top: 10px; }
    </style>
</head>
<body class="home-page">
    <header class="site-header"><div class="topbar"><nav class="toplinks"><a href="home.php">Home</a> | <a href="products.php">Back to Shop</a></nav></div></header>

    <main class="container">
        <div style="display: flex; gap: 30px; margin-top: 20px; align-items: start;">
            <img src="<?php echo htmlspecialchars($product['image']); ?>" style="max-width: 300px; border-radius: 12px;">
            <div>
                <h1><?php echo htmlspecialchars($product['name']); ?></h1>
                <p style="font-size: 24px; font-weight: bold;"><?php echo $product['price']; ?> ₪</p>
            </div>
        </div>

        <hr>

        <h3>Customer Reviews</h3>
        
        <div class="review-form">
            <h4>Write a Review</h4>
            <form method="POST">
                <label>Rating:</label>
                <select name="rating" required>
                    <option value="5">⭐⭐⭐⭐⭐ (5)</option>
                    <option value="4">⭐⭐⭐⭐ (4)</option>
                    <option value="3">⭐⭐⭐ (3)</option>
                    <option value="2">⭐⭐ (2)</option>
                    <option value="1">⭐ (1)</option>
                </select>
                <textarea name="content" rows="4" placeholder="Share your thoughts about this product..." required></textarea>
                <button type="submit" name="submit_review" style="margin-top: 10px; background: #2563eb; color: white; padding: 10px 20px; border: none; border-radius: 8px; cursor: pointer;">Post Review</button>
            </form>
        </div>

        <div class="reviews-list">
            <?php foreach ($reviews as $rev): ?>
                <div class="review-card">
                    <div class="rating-stars"><?php echo str_repeat("⭐", $rev['rating']); ?></div>
                    <strong><?php echo htmlspecialchars($rev['username']); ?></strong> 
                    <small class="muted"><?php echo $rev['date']; ?></small>
                    <div style="margin-top: 10px;">
                        <?php echo $rev['content']; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </main>
</body>
</html>