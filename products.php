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
];


// --- Load products from SQLite DB (instead of hardcoded array) ---
$db = new PDO('sqlite:' . __DIR__ . '/app.db');
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$selectedCat = is_string($_GET["cat"] ?? "") ? ($_GET["cat"] ?? "") : "";
$q = trim($_GET["q"] ?? "");

// Build query safely (no string concatenation)
$sql = "SELECT id, name, price, category, image FROM products WHERE 1=1";
$params = [];

if ($selectedCat !== "") {
  $sql .= " AND category = :cat";
  $params[":cat"] = $selectedCat;
}
if ($q !== "") {
  $sql .= " AND name LIKE :q";
  $params[":q"] = "%" . $q . "%";
}

$sql .= " ORDER BY name ASC";

$stmt = $db->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Convert DB rows to the same structure your code expects ($products[id] = [...])
$products = [];
foreach ($rows as $r) {
  $id = (int)$r["id"];
  $products[$id] = [
    "name" => $r["name"],
    "price" => (float)$r["price"],
    "cat" => $r["category"],
    "img" => $r["image"],
  ];
}
// --- end DB load ---


$selectedCat = is_string($_GET["cat"] ?? "") ? ($_GET["cat"] ?? "") : "";
$q = trim($_GET["q"] ?? "");

// Add to cart
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["add_id"])) {
  $id = (int)$_POST["add_id"];
  if (isset($products[$id])) {
    $_SESSION["cart"][$id] = ($_SESSION["cart"][$id] ?? 0) + 1;
  }
  $back = "products.php";
  $params = [];
  if ($selectedCat) $params["cat"] = $selectedCat;
  if ($q) $params["q"] = $q;
  if ($params) $back .= "?" . http_build_query($params);
  header("Location: $back");
  exit;
}

$cartCount = array_sum($_SESSION["cart"]);

// Filter
$filtered = [];
foreach ($products as $id => $p) {
  if ($selectedCat && ($p["cat"] ?? "") !== $selectedCat) continue;
  if ($q && stripos($p["name"], $q) === false) continue;
  $filtered[$id] = $p;
}

$pageTitle = ($selectedCat && isset($categories[$selectedCat])) ? $categories[$selectedCat] : "All Products";
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="assets/styles.css?v=3">
  <title><?php echo htmlspecialchars($pageTitle); ?></title>
</head>

<body class="home-page">
<header class="site-header">
  <div class="topbar">
    <form class="search-form" action="products.php" method="GET">
      <?php if ($selectedCat): ?>
        <input type="hidden" name="cat" value="<?php echo htmlspecialchars($selectedCat); ?>">
      <?php endif; ?>
      <input type="text" name="q" value="<?php echo htmlspecialchars($q); ?>" placeholder="Search products or brands" />
      <button type="submit">Search</button>
    </form>

    <nav class="toplinks">
      <a href="home.php">Home</a>
      <a href="products.php">All Products</a>
      <a href="cart.php">My Cart (<?php echo (int)$cartCount; ?>)</a>
      <a href="profile.php">My Account</a>
      <form method="POST" action="logout.php" style="display:inline; margin:0;">
        <button type="submit" class="linklike">Log out</button>
      </form>
    </nav>
  </div>

  <div class="categories-strip">
    <a class="cat" href="products.php?cat=fruits_veg">🥬 Fruits & Vegetables</a>
    <a class="cat" href="products.php?cat=dairy_eggs">🥛 Dairy & Eggs</a>
    <a class="cat" href="products.php?cat=snacks_dry">🍪 Snacks & Pantry</a>
    <a class="cat" href="products.php?cat=meat_fish">🐟 Meat & Fish</a>
    <a class="cat" href="products.php?cat=frozen">🧊 Frozen</a>
    <a class="cat" href="products.php?cat=soft_drink">🥤 Soft Drinks</a>
    <a class="cat" href="products.php?cat=alcohol">🍺 Alcohol</a>
  </div>
</header>

<main class="container">
  <h1 style="margin-top:16px;"><?php echo htmlspecialchars($pageTitle); ?></h1>

  <?php if (!$filtered): ?>
    <p>No products found.</p>
  <?php else: ?>
    <div class="products-grid">
      <?php foreach ($filtered as $id => $p): ?>
        <article class="product-card">
          <div class="product-img">
            <img src="<?php echo htmlspecialchars($p["img"]); ?>"
                 alt="<?php echo htmlspecialchars($p["name"]); ?>"
                 onerror="this.style.display='none'">
          </div>

          <div class="product-body">
            <div class="product-name"><?php echo htmlspecialchars($p["name"]); ?></div>
            <div class="product-price"><?php echo number_format((float)$p["price"], 2); ?> ₪</div>

            <form method="POST">
              <input type="hidden" name="add_id" value="<?php echo (int)$id; ?>">
              <button class="add-btn" type="submit">Add to cart</button>
            </form>
          </div>
        </article>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</main>

</body>
</html>
