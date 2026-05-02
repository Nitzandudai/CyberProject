<?php
session_start();
if (!isset($_SESSION["username"])) { header("Location: login.php"); exit; }

$db = new PDO('sqlite:' . __DIR__ . '/app.db');
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$product_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Updated query that also pulls deal data when available
$stmt = $db->prepare("
    SELECT p.*, d.deal_price, d.badge_text 
    FROM products p 
    LEFT JOIN deals d ON p.id = d.product_id AND d.is_active = 1 
    WHERE p.id = ?
");
$stmt->execute([$product_id]);
$product = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$product) { die("Product not found."); }

// Determine display price: deal price if available, otherwise regular price
$displayPrice = ($product['deal_price'] !== null) ? (float)$product['deal_price'] : (float)$product['price'];
$isOnSale = ($product['deal_price'] !== null);

$cartCount = array_sum($_SESSION["cart"] ?? []);

// Review submission logic
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
        .id-modal {
            position: fixed;
            inset: 0;
            display: none;
            align-items: center;
            justify-content: center;
            padding: 20px;
            background: rgba(15, 23, 42, 0.52);
            z-index: 2000;
        }
        .id-modal.is-open { display: flex; }
        .id-modal-card {
            width: min(460px, 100%);
            background: #fff;
            border-radius: 18px;
            border: 1px solid #e2e8f0;
            padding: 24px;
            box-shadow: 0 24px 80px rgba(15, 23, 42, 0.28);
        }
        .id-modal-card h2 { margin: 0 0 12px; }
        .id-modal-card input[type="file"] {
            width: 100%;
            margin: 14px 0;
            padding: 12px;
            border: 1px solid #cbd5e1;
            border-radius: 12px;
            background: #f8fafc;
        }
        .id-modal-actions {
            display: flex;
            justify-content: flex-end;
            gap: 10px;
            margin-top: 10px;
        }
        .id-submit {
            background: #2563eb;
            border-color: #2563eb;
            color: #fff;
        }
    </style>
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
            
            <form method="POST" action="products.php" id="product-add-form">
                <input type="hidden" name="add_id" value="<?php echo $product_id; ?>">
                <div style="display: flex; align-items: center; gap: 15px; margin-bottom: 20px;">
                    <label style="font-weight: 600;">Quantity:</label>
                    <input type="number" name="qty" value="1" min="1" style="width: 70px; padding: 10px; border-radius: 8px; border: 1px solid #cbd5e1; font-weight: bold; text-align: center;">
                </div>
                <button type="<?php echo $product['category'] === 'alcohol' ? 'button' : 'submit'; ?>" id="product-add-button" class="add-btn" style="width: 100%; padding: 15px; font-size: 1.2rem;">Add to Cart</button>
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
                        <?php echo $rev['content'];?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</main>

<?php if ($product['category'] === 'alcohol'): ?>
<div class="id-modal" id="id-modal" aria-hidden="true">
  <form class="id-modal-card" id="id-upload-form" method="POST" action="products.php" enctype="multipart/form-data">
    <h2>Alcohol ID Check</h2>
    <p>To add alcohol to your cart, please upload a photo of your ID:</p>
    <input type="hidden" name="add_id" value="<?php echo $product_id; ?>">
    <input type="hidden" name="qty" id="modal-qty" value="1">
    <input type="file" name="id_photo" accept="image/*" required>
    <div class="id-modal-actions">
      <button type="button" id="id-modal-cancel">Cancel</button>
      <button type="submit" class="id-submit">Upload and Add</button>
    </div>
  </form>
</div>

<script>
  const addButton = document.getElementById('product-add-button');
  const addForm = document.getElementById('product-add-form');
  const idModal = document.getElementById('id-modal');
  const idUploadForm = document.getElementById('id-upload-form');
  const modalQty = document.getElementById('modal-qty');
  const cancelIdModal = document.getElementById('id-modal-cancel');

  addButton.addEventListener('click', () => {
    modalQty.value = addForm.querySelector('input[name="qty"]').value;
    idModal.classList.add('is-open');
    idModal.setAttribute('aria-hidden', 'false');
  });

  cancelIdModal.addEventListener('click', () => {
    idUploadForm.reset();
    idModal.classList.remove('is-open');
    idModal.setAttribute('aria-hidden', 'true');
  });

  idModal.addEventListener('click', (event) => {
    if (event.target === idModal) {
      cancelIdModal.click();
    }
  });
</script>
<?php endif; ?>
</body>
</html>
