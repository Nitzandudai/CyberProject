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
                
                <form method="POST" action="home.php">
                    <input type="hidden" name="add_id" value="<?php echo $id; ?>">
                    <button type="submit">Add to cart</button>
                </form>
              </div>
            </article>
          <?php endforeach; ?>
        </div>
    </div>
  </section>
</main>
</body>
</html>