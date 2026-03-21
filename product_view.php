<?php
session_start();
if (!isset($_SESSION["username"])) { header("Location: login.php"); exit; }

$db = new PDO('sqlite:' . __DIR__ . '/app.db');
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$product_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

$stmt = $db->prepare("SELECT * FROM products WHERE id = ?");
$stmt->execute([$product_id]);
$product = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$product) { die("Product not found."); }

$cartCount = array_sum($_SESSION["cart"] ?? []);

// לוגיקת הוספת ביקורת (נשארת זהה)
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["submit_review"])) {
    $user = $_SESSION["username"];
    $rating = (int)$_POST["rating"];
    $content = $_POST["content"]; 
    $ins = $db->prepare("INSERT INTO reviews (product_id, username, rating, content) VALUES (?, ?, ?, ?)");
    $ins->execute([$product_id, $user, $rating, $content]);
    header("Location: product_view.php?id=$product_id");
    exit;
}

$revStmt = $db->prepare("SELECT * FROM reviews WHERE product_id = ? ORDER BY date DESC");
$revStmt->execute([$product_id]);
$reviews = $revStmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <link rel="stylesheet" href="assets/styles.css?v=5">
    <title><?php echo htmlspecialchars($product['name']); ?></title>
</head>
<body class="home-page">

<header class="site-header">
  <div class="topbar">
    <form class="search-form" action="products.php" method="GET">
      <input type="text" name="q" placeholder="Search products" />
      <button type="submit">Search</button>
    </form>
    <nav class="toplinks">
      <a href="home.php">Home</a> | <a href="products.php">All Products</a> | <a href="cart.php">Cart (<?php echo $cartCount; ?>)</a>
    </nav>
  </div>
</header>

<main class="container">
    <div style="display: flex; gap: 40px; margin-top: 40px; background: white; padding: 30px; border-radius: 18px; border: 1px solid #e6e8eb; align-items: center;">
        <div style="flex: 1; text-align: center;">
            <img src="<?php echo htmlspecialchars($product['image']); ?>" style="max-width: 100%; max-height: 350px; border-radius: 12px;">
        </div>
        <div style="flex: 1;">
            <h1 style="font-size: 2.5rem; margin-bottom: 10px;"><?php echo htmlspecialchars($product['name']); ?></h1>
            <p style="font-size: 2rem; font-weight: 800; color: #2563eb; margin-bottom: 25px;"><?php echo $product['price']; ?> ₪</p>
            
            <form method="POST" action="products.php">
                <input type="hidden" name="add_id" value="<?php echo $product_id; ?>">
                <div style="display: flex; align-items: center; gap: 15px; margin-bottom: 20px;">
                    <label style="font-weight: 600;">Quantity:</label>
                    <input type="number" name="qty" value="1" min="1" style="width: 70px; padding: 10px; border-radius: 8px; border: 1px solid #cbd5e1; font-weight: bold; text-align: center;">
                </div>
                <button type="submit" class="add-btn" style="width: 100%; padding: 15px; font-size: 1.2rem;">Add to Cart</button>
            </form>
        </div>
    </div>

    <?php if ($product['category'] === 'electronics'): ?>
        <hr style="margin: 50px 0; border: 0; border-top: 2px solid #f1f5f9;">
        
        <h2 style="margin-bottom: 25px;">Customer Reviews</h2>
        
        <div style="background: #f8fafc; padding: 25px; border-radius: 18px; border: 1px solid #e2e8f0; margin-bottom: 40px;">
            <h3 style="margin-top: 0;">Write a Review</h3>
            <form method="POST">
                <div style="margin-bottom: 15px;">
                    <label style="display: block; margin-bottom: 8px; font-weight: 600;">Rating:</label>
                    <select name="rating" style="padding: 10px; border-radius: 8px; border: 1px solid #cbd5e1;">
                        <option value="5">⭐⭐⭐⭐⭐ (5)</option>
                        <option value="4">⭐⭐⭐⭐ (4)</option>
                        <option value="3">⭐⭐⭐ (3)</option>
                        <option value="2">⭐⭐ (2)</option>
                        <option value="1">⭐ (1)</option>
                    </select>
                </div>
                <textarea name="content" rows="4" style="width: 100%; padding: 15px; border-radius: 12px; border: 1px solid #cbd5e1; font-family: inherit;" placeholder="Tell us what you think..." required></textarea>
                <button type="submit" name="submit_review" style="margin-top: 20px; background: #2563eb; color: white; padding: 12px 30px; border: none; border-radius: 8px; font-weight: 700; cursor: pointer;">Post Review</button>
            </form>
        </div>

        <div class="reviews-list">
            <?php foreach ($reviews as $rev): ?>
                <div style="background: white; padding: 25px; border-radius: 15px; border: 1px solid #e6e8eb; margin-bottom: 20px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05);">
                    <div style="color: #f59e0b; margin-bottom: 8px; font-size: 1.1rem;"><?php echo str_repeat("⭐", $rev['rating']); ?></div>
                    <div style="margin-bottom: 12px;">
                        <span style="font-weight: 700; font-size: 1.1rem;"><?php echo htmlspecialchars($rev['username']); ?></span> 
                        <span style="color: #94a3b8; font-size: 0.85rem; margin-left: 12px;"><?php echo $rev['date']; ?></span>
                    </div>
                    <div style="line-height: 1.7; color: #334155;">
                        <?php echo $rev['content']; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</main>
</body>
</html>