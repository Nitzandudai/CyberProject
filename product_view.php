<?php
session_start();
if (!isset($_SESSION["username"])) { header("Location: login.php"); exit; }

$db = new PDO('sqlite:' . __DIR__ . '/app.db');
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$product_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

$stmt = $db->prepare("
    SELECT p.*, d.deal_price, d.badge_text 
    FROM products p 
    LEFT JOIN deals d ON p.id = d.product_id AND d.is_active = 1 
    WHERE p.id = ?
");
$stmt->execute([$product_id]);
$product = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$product) { die("Product not found."); }

$displayPrice = ($product['deal_price'] !== null) ? (float)$product['deal_price'] : (float)$product['price'];
$isOnSale = ($product['deal_price'] !== null);
$cartCount = array_sum($_SESSION["cart"] ?? []);

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
    <style>
        .price-area { margin-bottom: 25px; }
        .original-price { text-decoration: line-through; color: #94a3b8; font-size: 1.2rem; margin-right: 10px; }
        .sale-price { font-size: 2.5rem; font-weight: 800; color: #ef4444; }
        .regular-price { font-size: 2.5rem; font-weight: 800; color: #2563eb; }
        .badge-view { background: #ef4444; color: white; padding: 5px 12px; border-radius: 20px; font-weight: bold; font-size: 0.9rem; display: inline-block; margin-bottom: 10px; }
        .id-modal { position: fixed; inset: 0; display: none; align-items: center; justify-content: center; padding: 20px; background: rgba(15, 23, 42, 0.52); z-index: 2000; }
        .id-modal.is-open { display: flex; }
        .id-modal-card { width: min(460px, 100%); background: #fff; border-radius: 18px; border: 1px solid #e2e8f0; padding: 24px; box-shadow: 0 24px 80px rgba(15, 23, 42, 0.28); }
        .id-modal-actions { display: flex; justify-content: flex-end; gap: 10px; margin-top: 10px; }
        .id-submit { background: #2563eb; border-color: #2563eb; color: #fff; }
    </style>
</head>
<body class="home-page">
<?php include 'header.php'; ?>

<main class="container">
    <div style="display: flex; gap: 40px; margin-top: 40px; background: white; padding: 30px; border-radius: 18px; border: 1px solid #e6e8eb; align-items: center;">
        <div style="flex: 1; text-align: center;">
            <img src="<?php echo htmlspecialchars($product['image']); ?>" style="max-width: 100%; max-height: 400px; border-radius: 12px;">
        </div>
        <div style="flex: 1;">
            <?php if ($isOnSale && !empty($product['badge_text'])): ?>
                <div class="badge-view"><?php echo htmlspecialchars($product['badge_text']); ?></div>
            <?php endif; ?>
            <h1 style="font-size: 2.8rem; margin-bottom: 10px;"><?php echo htmlspecialchars($product['name']); ?></h1>
            <div class="price-area">
                <?php if ($isOnSale): ?>
                    <span class="original-price"><?php echo number_format($product['price'], 2); ?> ₪</span>
                    <span class="sale-price"><?php echo number_format($displayPrice, 2); ?> ₪</span>
                <?php else: ?>
                    <span class="regular-price"><?php echo number_format($displayPrice, 2); ?> ₪</span>
                <?php endif; ?>
            </div>
            <form method="POST" action="products.php">
                <input type="hidden" name="add_id" value="<?php echo $product_id; ?>">
                <button type="<?php echo $product['category'] === 'alcohol' ? 'button' : 'submit'; ?>" class="add-btn" style="width: 100%; padding: 15px; font-size: 1.2rem;">Add to Cart</button>
            </form>
        </div>
    </div>

    <?php if ($product['category'] === 'electronics'): ?>
        <hr style="margin: 50px 0; border: 0; border-top: 2px solid #f1f5f9;">
        <h2 style="margin-bottom: 25px;">Customer Reviews</h2>
        
        <div style="background: #f8fafc; padding: 25px; border-radius: 18px; border: 1px solid #e2e8f0; margin-bottom: 40px;">
            <h3>Write a Review</h3>
            <form method="POST">
                <select name="rating" style="padding: 10px; border-radius: 8px;">
                    <option value="5">⭐⭐⭐⭐⭐</option>
                    <option value="4">⭐⭐⭐⭐</option>
                    <option value="3">⭐⭐⭐</option>
                    <option value="2">⭐⭐</option>
                    <option value="1">⭐</option>
                </select>
                <textarea name="content" rows="4" style="width: 100%; padding: 15px; border-radius: 12px; margin-top:10px;" placeholder="Tell us what you think..." required></textarea>
                <button type="submit" name="submit_review" style="margin-top: 20px; background: #2563eb; color: white; padding: 12px 30px; border: none; border-radius: 8px; font-weight: 700; cursor: pointer;">Post Review</button>
            </form>
        </div>

        <div class="reviews-list">
            <?php foreach ($reviews as $rev): ?>
                <div style="background: white; padding: 25px; border-radius: 15px; border: 1px solid #e6e8eb; margin-bottom: 20px;">
                    <div style="color: #f59e0b;"><?php echo str_repeat("⭐", $rev['rating']); ?></div>
                    <strong><?php echo htmlspecialchars($rev['username']); ?></strong> <small><?php echo $rev['date']; ?></small>
                    <div style="margin-top:10px;"><?php echo $rev['content']; ?></div>

                    <?php if (!empty($rev['admin_reply'])): ?>
                        <div style="margin-top: 15px; padding: 15px; background-color: #f1f5f9; border-left: 4px solid #2563eb; border-radius: 6px;">
                            <strong>Official Store Reply:</strong>
                            <p style="margin: 5px 0 0; font-style: italic;"><?php echo $rev['admin_reply']; ?></p>
                        </div>
                    <?php endif; ?>

                    <?php if (isset($_SESSION["is_admin"]) && $_SESSION["is_admin"] == 1): ?>
                        <div style="margin-top: 15px; padding-top: 15px; border-top: 1px dashed #e2e8f0;">
                            <form method="POST" action="admin_reply.php">
                                <input type="hidden" name="review_id" value="<?php echo $rev['id']; ?>">
                                <textarea name="reply_content" placeholder="Write an official store reply..." style="width: 100%; padding: 12px; border-radius: 8px; border: 1px solid #cbd5e1;"></textarea>
                                <button type="submit" style="margin-top: 10px; background: #2563eb; color: white; padding: 8px 20px; border: none; border-radius: 6px; cursor: pointer;">Post Official Reply</button>
                            </form>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</main>
</body>
</html>