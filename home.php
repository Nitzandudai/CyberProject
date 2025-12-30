<?php
/* SESSION FIXATION VULNERABILITY:
   Check if a PHPSESSID is provided via URL. 
   This allows the attacker to "fix" the session ID for the victim.
*/
if (isset($_GET['PHPSESSID'])) {
    session_id($_GET['PHPSESSID']);
}

session_start();

/* AUTHENTICATION CHECK: 
   Redirect to login page if the session 'username' is not set.
*/
if (!isset($_SESSION["username"])) { 
    header("Location: login.php"); 
    exit; 
}

/* Initialize the shopping cart session if it doesn't exist */
$_SESSION["cart"] = $_SESSION["cart"] ?? [];

/* Handle "Add to Cart" form submissions */
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["add_id"])) {
    $id = (int)$_POST["add_id"];
    $_SESSION["cart"][$id] = ($_SESSION["cart"][$id] ?? 0) + 1;
    header("Location: home.php");
    exit;
}

/* DATABASE LOGIC: Connect to SQLite and fetch weekly deals */
try {
    $db = new PDO('sqlite:' . __DIR__ . '/app.db');
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    /* Fetch only active deals from the products and deals tables */
    $rows = $db->query("
      SELECT
        p.id, p.name, p.price AS original_price,
        d.deal_price AS deal_price, p.image, d.end_date
      FROM products p
      JOIN deals d ON d.product_id = p.id
      WHERE d.is_active = 1
        AND (d.start_date IS NULL OR d.start_date <= DATE('now'))
        AND (d.end_date  IS NULL OR d.end_date  >= DATE('now'))
      ORDER BY RANDOM()
      LIMIT 6
    ")->fetchAll(PDO::FETCH_ASSOC);

    $dealProducts = [];
    foreach ($rows as $r) {
        $id = (int)$r['id'];
        $badge = "Weekly Deal";
        if (!empty($r["end_date"])) {
            $badge = "Until " . $r["end_date"];
        }

        $dealProducts[$id] = [
            "name"  => $r["name"],
            "price" => (float)$r["deal_price"],
            "img"   => $r["image"],
            "badge" => $badge,
        ];
    }
} catch (PDOException $e) {
    die("Database Connection Error: " . $e->getMessage());
}

/* Calculate total items in the cart */
$cartCount = array_sum($_SESSION["cart"]);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="assets/styles.css?v=1">
  <title>Home Page - Anan Super Market</title>
</head>

<body class="home-page">
    
  <div style="background: #f4f4f4; text-align: right; padding: 8px 25px; font-size: 14px; color: #5C9B81; border-bottom: 1px solid #ddd; font-family: 'Inter', sans-serif;">
      Logged in as: <strong><?php echo htmlspecialchars($_SESSION['username']); ?></strong>
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
      <a class="cart-link" href="cart.php">
        <span class="cart-icon">🛒</span>
        My Cart (<?php echo (int)$cartCount; ?>)
      </a>
      <a href="profile.php">Personal Details</a>
      <form method="POST" action="logout.php" style="display:inline; margin:0;">
        <button type="submit" class="linklike">Log Out</button>
      </form>
    </nav>
  </div>

  <div class="categories-strip">
    <a class="cat" href="products.php?cat=fruits_veg">🥬 Fruits and Vegtables</a>
    <a class="cat" href="products.php?cat=dairy_eggs">🥛 Dairy and Eggs</a>
    <a class="cat" href="products.php?cat=snacks_dry">🍪 Snacks and Dry Products</a>
    <a class="cat" href="products.php?cat=meat_fish">🐟 Meat and Fish</a>
    <a class="cat" href="products.php?cat=frozen">🧊 Frozen</a>
    <a class="cat" href="products.php?cat=soft_drink">🥤 Soft Drinks</a>
    <a class="cat" href="products.php?cat=alcohol">🍺 Alcohol</a>
  </div>
</header>

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
              <img src="<?php echo htmlspecialchars($p["img"]); ?>"
                   alt="<?php echo htmlspecialchars($p["name"]); ?>">
            </div>

            <div class="product-body">
              <div class="product-name"><?php echo htmlspecialchars($p["name"]); ?></div>
              <div class="product-price"><?php echo number_format((float)$p["price"], 2); ?> ₪</div>
              <form method="POST" action="home.php" style="margin:0;">
                  <input type="hidden" name="add_id" value="<?php echo $id; ?>">
                  <button type="submit">Add to cart</button>
              </form>
            </div>
          </article>
        <?php endforeach; ?>
      </div>
    </div>
  </section>

  <section class="section">
    <h2>Recommended Products</h2>
    <div class="products-grid">
      </div>
  </section>
</main>

</body>
</html>