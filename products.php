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

// --- חיבור למסד הנתונים ---
$db = new PDO('sqlite:' . __DIR__ . '/app.db');
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$selectedCat = $_GET["cat"] ?? "";
$q = $_GET["q"] ?? "";

// --- שלב 1: בניית השאילתה הפגיעה (Vulnerable Query) ---
// שרשור ישיר של $q לתוך ה-SQL בלי שום הגנה
$sql = "SELECT id, name, price, category, image FROM products WHERE name LIKE '%$q%'";

// אם נבחרה קטגוריה, נוסיף אותה (גם בצורה פגיעה)
if ($selectedCat !== "") {
    $sql .= " AND category = '$selectedCat'";
}

$sql .= " ORDER BY name ASC";

// --- שלב 2: הרצת השאילתה והוצאת נתונים ---
try {
    $res = $db->query($sql);
    $rows = $res->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // הצגת שגיאות SQL - קריטי בשבילנו כדי לראות אם ההזרקה עובדת
    die("<div style='color:red; background:#eee; padding:10px; border:1px solid red;'>
            <strong>SQL Error:</strong> " . $e->getMessage() . "<br>
            <strong>Query:</strong> " . $sql . "
         </div>");
}

// --- שלב 3: המרה למבנה שה-HTML מכיר ---
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

// לוגיקת הוספה לסל (נשארת רגילה)
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["add_id"])) {
    $add_id = (int)$_POST["add_id"];
    $_SESSION["cart"][$add_id] = ($_SESSION["cart"][$add_id] ?? 0) + 1;
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
            <div class="product-name"><?php echo htmlspecialchars($p["name"]); ?></div>
            <div class="product-price"><?php echo $p["price"]; ?> ₪</div>
            <form method="POST">
              <input type="hidden" name="add_id" value="<?php echo $id; ?>">
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