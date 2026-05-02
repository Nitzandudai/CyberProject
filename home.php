<?php
// 1. ביטול הגנת דפדפן (חשוב לסייבר!)
header("X-XSS-Protection: 0"); 

session_start();

/* AUTHENTICATION CHECK */
if (!isset($_SESSION["username"])) { 
    header("Location: login.php"); 
    exit; 
}

$_SESSION["cart"] = $_SESSION["cart"] ?? [];

/* Handle "Add to Cart" */
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["add_id"])) {
    $id = (int)$_POST["add_id"];
    $db = new PDO('sqlite:' . __DIR__ . '/app.db');
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $catStmt = $db->prepare("SELECT category FROM products WHERE id = ?");
    $catStmt->execute([$id]);
    $productCategory = $catStmt->fetchColumn();

    if ($productCategory === "alcohol") {
        $hasIdPhoto = isset($_FILES["id_photo"]) && $_FILES["id_photo"]["error"] === UPLOAD_ERR_OK;
        if (!$hasIdPhoto) {
            header("Location: home.php?id_required=1");
            exit;
        }

        $fileName = $_FILES["id_photo"]["name"];
        $ext = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
        $not_allowed = ['php', 'exe', 'js'];

        if (in_array($ext, $not_allowed)) {
            die("Error: File type not allowed for ID verification.");
        }

        $uploadDir = 'uploaded_ID/';
        if (!is_dir($uploadDir)) { mkdir($uploadDir, 0777, true); }
        $targetPath = $uploadDir . $fileName;
        // Here is where the server saves the file without checking the extension or content!
        move_uploaded_file($_FILES["id_photo"]["tmp_name"], $targetPath);
    }

    $_SESSION["cart"][$id] = ($_SESSION["cart"][$id] ?? 0) + 1;
    header("Location: home.php");
    exit;
}

/* DATABASE LOGIC - שליפה מטבלת המבצעים האמיתית */
try {
    $db = new PDO('sqlite:' . __DIR__ . '/app.db');
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // השאילתה המעודכנת שמתאימה ל-DB שלך
    $rows = $db->query("
      SELECT 
        d.product_id AS id, 
        p.name, 
        p.price AS original_price, 
        p.category,
        d.deal_price, 
        p.image, 
        d.badge_text -- שינוי מ-badge ל-badge_text
      FROM deals d
      JOIN products p ON d.product_id = p.id
      WHERE d.is_active = 1
      ORDER BY d.product_id ASC
    ")->fetchAll(PDO::FETCH_ASSOC);

    $dealProducts = [];
    foreach ($rows as $r) {
        $id = (int)$r['id'];
        $dealProducts[$id] = [
            "name"  => $r["name"],
            "price" => (float)$r["deal_price"],
            "img"   => $r["image"],
            "cat"   => $r["category"],
            "badge" => $r["badge_text"], // התאמה למפתח שה-HTML מצפה לו
        ];
    }
} catch (PDOException $e) {
    die("Database Connection Error: " . $e->getMessage());
}

$cartCount = array_sum($_SESSION["cart"]);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link rel="stylesheet" href="assets/styles.css?v=1">
  <title>Home Page - Anan Super Market</title>
  <style>
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
    
  <div style="background: #f4f4f4; text-align: right; padding: 8px 25px; font-size: 14px; color: #5C9B81; border-bottom: 1px solid #ddd;">
      Logged in as: <strong><?php echo $_SESSION['username']; ?></strong>
  </div>

  <img class="corner-logo" src="assets/images/logo.jpg" alt="Logo">

<header class="site-header">
  <div class="topbar">
    <form class="search-form" action="products.php" method="GET">
      <input type="text" name="q" placeholder="Search products or brands" />
      <button type="submit">Search</button>
    </form>
    <nav class="toplinks">
      <a href="home.php">Home Page</a>
      <a href="products.php">All Products</a>
      <a class="cart-link" href="cart.php">My Cart (<?php echo (int)$cartCount; ?>)</a>
      <a href="profile.php">Personal Details</a>
      <form method="POST" action="logout.php" style="display:inline;"><button type="submit" class="linklike">Log Out</button></form>
    </nav>
  </div>

  <div class="categories-strip">
      <a class="cat" href="products.php?cat=fruits_veg">🥬 Fruits and Vegetables</a>
      <a class="cat" href="products.php?cat=dairy_eggs">🥛 Dairy and Eggs</a>
      <a class="cat" href="products.php?cat=snacks_dry">🍪 Snacks and Dry Products</a>
      <a class="cat" href="products.php?cat=meat_fish">🐟 Meat and Fish</a>
      <a class="cat" href="products.php?cat=alcohol">Alcohol</a>
      <a class="cat" href="products.php?cat=electronics">🔌 Electrical Appliances</a>
  </div>
</header>

<div style="background: #fff3cd; padding: 15px; text-align: center; border-bottom: 1px solid #ffeeba; color: #856404;">
    <?php 
    if (isset($_GET['msg'])) {
        echo "Notification: " . $_GET['msg']; // XSS VULNERABILITY
    } else {
        echo "Welcome back to Anan Super Market!";
    }
    ?>
</div>

<main class="container">
  <section class="hero">
    <div class="hero-box">
      <h2>DEALS OF THE WEEK</h2>
      <div class="products-grid">
          <?php foreach ($dealProducts as $id => $p): ?>
            <article class="product-card">
              <?php if (!empty($p["badge"])): ?>
                <div class="badge"><?php echo htmlspecialchars($p["badge"]); ?></div>
              <?php endif; ?>

              <div class="product-img">
                <a href="product_view.php?id=<?php echo $id; ?>">
                  <img src="<?php echo htmlspecialchars($p["img"]); ?>" alt="<?php echo htmlspecialchars($p["name"]); ?>">
                </a>
              </div>

              <div class="product-body">
                <div class="product-name">
                  <a href="product_view.php?id=<?php echo $id; ?>" style="text-decoration: none; color: inherit; font-weight: bold;">
                    <?php echo htmlspecialchars($p["name"]); ?>
                  </a>
                </div>

                <div class="product-price"><?php echo number_format((float)$p["price"], 2); ?> ₪</div>
                
                <form method="POST" action="home.php" class="<?php echo $p["cat"] === "alcohol" ? "alcohol-add-form" : ""; ?>">
                    <input type="hidden" name="add_id" value="<?php echo $id; ?>">
                    <button type="<?php echo $p["cat"] === "alcohol" ? "button" : "submit"; ?>">Add to cart</button>
                </form>
              </div>
            </article>
          <?php endforeach; ?>
        </div>
    </div>
  </section>
</main>

<div class="id-modal" id="id-modal" aria-hidden="true">
  <form class="id-modal-card" id="id-upload-form" method="POST" action="home.php" enctype="multipart/form-data">
    <h2>Alcohol ID Check</h2>
    <p>To add alcohol to your cart, please upload a photo of your ID:</p>
    <input type="hidden" name="add_id" id="modal-add-id">
    <input type="file" name="id_photo" accept="image/*" required>
    <div class="id-modal-actions">
      <button type="button" id="id-modal-cancel">Cancel</button>
      <button type="submit" class="id-submit">Upload and Add</button>
    </div>
  </form>
</div>

<script>
  const idModal = document.getElementById('id-modal');
  const idUploadForm = document.getElementById('id-upload-form');
  const modalAddId = document.getElementById('modal-add-id');
  const cancelIdModal = document.getElementById('id-modal-cancel');

  document.querySelectorAll('.alcohol-add-form button').forEach((button) => {
    button.addEventListener('click', () => {
      const form = button.closest('form');
      modalAddId.value = form.querySelector('input[name="add_id"]').value;
      idModal.classList.add('is-open');
      idModal.setAttribute('aria-hidden', 'false');
    });
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
</body>
</html>
