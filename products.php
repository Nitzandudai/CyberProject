<?php
session_start();
if (!isset($_SESSION["username"])) { header("Location: login.php"); exit; }
$_SESSION["cart"] = $_SESSION["cart"] ?? [];

$categories = [
  "fruits_veg" => "Fruits & Vegetables",
  "dairy_eggs" => "Dairy & Eggs",
  "snacks_dry" => "Snacks & Pantry",
  "meat_fish"  => "Meat & Fish",
  "frozen"     => "Frozen",
  "soft_drink" => "Soft Drinks",
  "alcohol"    => "Alcohol",
  "electronics"=> "Electrical Appliances",
];

// --- Database connection ---
$db = new PDO('sqlite:' . __DIR__ . '/app.db');
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$selectedCat = $_GET["cat"] ?? "";
$q = $_GET["q"] ?? "";

// --- Step 1: Build vulnerable query (intentional insecure example) ---
// Direct concatenation of $q into SQL with no protection
$sql = "SELECT id, name, price, category, image FROM products WHERE name LIKE '%$q%'";

// If a category is selected, append it (also insecurely)
if ($selectedCat !== "") {
    $sql .= " AND category = '$selectedCat'";
}

$sql .= " ORDER BY name ASC";

// --- Step 2: Run query and fetch data ---
try {
    $res = $db->query($sql);
    $rows = $res->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // Display SQL errors - important for us to verify if injection is working
    die("<div style='color:red; background:#eee; padding:10px; border:1px solid red;'>
            <strong>SQL Error:</strong> " . $e->getMessage() . "<br>
            <strong>Query:</strong> " . $sql . "
         </div>");
}

// --- Step 3: Convert to structure the HTML expects ---
$filtered = [];
foreach ($rows as $r) {
    $id = (int)$r["id"];
    $filtered[$id] = [
        "name"  => $r["name"],
        "price" => $r["price"],
        "cat"   => $r["category"],
        "img"   => $r["image"],
    ];
}

// Add-to-cart logic (regular behavior)
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["add_id"])) {
    $add_id = (int)$_POST["add_id"];
    
    // Read quantity from form; default 1 if not provided (as on main page)
    $qty = isset($_POST["qty"]) ? (int)$_POST["qty"] : 1;

    $catStmt = $db->prepare("SELECT category FROM products WHERE id = ?");
    $catStmt->execute([$add_id]);
    $productCategory = $catStmt->fetchColumn();

    if ($productCategory === "alcohol") {
        $hasIdPhoto = isset($_FILES["id_photo"]) && $_FILES["id_photo"]["error"] === UPLOAD_ERR_OK;
        if (!$hasIdPhoto) {
            header("Location: products.php?cat=alcohol&id_required=1");
            exit;
        }
    }
    
    $_SESSION["cart"][$add_id] = ($_SESSION["cart"][$add_id] ?? 0) + $qty;
    
    header("Location: products.php" . (isset($_SERVER['QUERY_STRING']) ? "?" . $_SERVER['QUERY_STRING'] : ""));
    exit;
}

$cartCount = array_sum($_SESSION["cart"]);
$pageTitle = ($selectedCat && isset($categories[$selectedCat])) ? $categories[$selectedCat] : "All Products";
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <link rel="stylesheet" href="assets/styles.css?v=3">
  <title><?php echo htmlspecialchars($pageTitle); ?></title>
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
<header class="site-header">
  <div class="topbar">
    <form class="search-form" action="products.php" method="GET">
      <?php if ($selectedCat): ?>
        <input type="hidden" name="cat" value="<?php echo htmlspecialchars($selectedCat); ?>">
      <?php endif; ?>
      <input type="text" name="q" value="<?php echo htmlspecialchars($q); ?>" placeholder="Search products" />
      <button type="submit">Search</button>
    </form>
    <nav class="toplinks">
      <a href="home.php">Home</a> | <a href="products.php">All Products</a> | <a href="cart.php">Cart (<?php echo $cartCount; ?>)</a>
    </nav>
  </div>
</header>

<main class="container">
  <h1><?php echo htmlspecialchars($pageTitle); ?></h1>

  <?php if (!$filtered): ?>
    <p>No products found.</p>
  <?php else: ?>
    <div class="products-grid">
      <?php foreach ($filtered as $id => $p): ?>
        <article class="product-card">
          <div class="product-img">
            <img src="<?php echo htmlspecialchars($p["img"]); ?>" onerror="this.src='assets/images/placeholder.jpg'">
          </div>
          <div class="product-body">
            <div class="product-name">
              <a href="product_view.php?id=<?php echo $id; ?>" style="color: inherit; text-decoration: none;">
                  <?php echo htmlspecialchars($p["name"]); ?>
              </a>
          </div>
            <div class="product-price"><?php echo $p["price"]; ?> ₪</div>
            <form method="POST" class="<?php echo $p["cat"] === "alcohol" ? "alcohol-add-form" : ""; ?>">
              <input type="hidden" name="add_id" value="<?php echo $id; ?>">
              <button class="add-btn" type="<?php echo $p["cat"] === "alcohol" ? "button" : "submit"; ?>">Add to cart</button>
            </form>
          </div>
        </article>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</main>

<div class="id-modal" id="id-modal" aria-hidden="true">
  <form class="id-modal-card" id="id-upload-form" method="POST" enctype="multipart/form-data">
    <h2>Alcohol ID Check</h2>
    <p>To add alcohol to your cart, please upload a photo of your ID:</p>
    <input type="hidden" name="add_id" id="modal-add-id">
    <input type="hidden" name="qty" id="modal-qty" value="1">
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
  const modalQty = document.getElementById('modal-qty');
  const cancelIdModal = document.getElementById('id-modal-cancel');

  document.querySelectorAll('.alcohol-add-form .add-btn').forEach((button) => {
    button.addEventListener('click', () => {
      const form = button.closest('form');
      const qtyInput = form.querySelector('input[name="qty"]');
      modalAddId.value = form.querySelector('input[name="add_id"]').value;
      modalQty.value = qtyInput ? qtyInput.value : '1';
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
