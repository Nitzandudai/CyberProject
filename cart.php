<?php
session_start();
if (!isset($_SESSION["username"])) { header("Location: login.php"); exit; }

$_SESSION["cart"] = $_SESSION["cart"] ?? [];

/* DB */
$db = new PDO('sqlite:' . __DIR__ . '/app.db');
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

/* Handle cart actions */
if ($_SERVER["REQUEST_METHOD"] === "POST") {
  $action = $_POST["action"] ?? "";
  $id = isset($_POST["id"]) ? (int)$_POST["id"] : 0;

  if ($action === "inc" && $id) {
    $_SESSION["cart"][$id] = ($_SESSION["cart"][$id] ?? 0) + 1;
  }

  if ($action === "dec" && $id && isset($_SESSION["cart"][$id])) {
    $_SESSION["cart"][$id] -= 1;
    if ($_SESSION["cart"][$id] <= 0) unset($_SESSION["cart"][$id]);
  }

  if ($action === "remove" && $id) {
    unset($_SESSION["cart"][$id]);
  }

  if ($action === "clear") {
    $_SESSION["cart"] = [];
  }

  header("Location: cart.php");
  exit;
}

$cartCount = array_sum($_SESSION["cart"]);

/* Load product details for IDs in cart */
$ids = array_keys($_SESSION["cart"]);
$productsById = [];

if (!empty($ids)) {
  $ph = implode(",", array_fill(0, count($ids), "?"));
  $stmt = $db->prepare("SELECT id, name, price, image FROM products WHERE id IN ($ph)");
  $stmt->execute($ids);

  foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
    $productsById[(int)$r["id"]] = [
      "name"  => $r["name"],
      "price" => (float)$r["price"],
      "img"   => $r["image"],
    ];
  }
}

/* Build items + total (ONLY ONCE) */
$items = [];
$total = 0.0;

foreach ($_SESSION["cart"] as $id => $qty) {
  $qty = (int)$qty;
  if ($qty <= 0) continue;

  $p = $productsById[(int)$id] ?? null;

  $name  = $p["name"] ?? ("Unknown item (#" . (int)$id . ")");
  $price = (float)($p["price"] ?? 0);
  $img   = $p["img"] ?? "";

  $line = $price * $qty;
  $total += $line;

  $items[] = [
    "id"    => (int)$id,
    "name"  => $name,
    "price" => $price,
    "img"   => $img,
    "qty"   => $qty,
    "line"  => $line,
  ];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="assets/styles.css?v=99">
  <title>My Cart</title>
  <style>
    /* small cart-only styles (kept minimal) */
    .cart-wrap{ margin-top:16px; background:#fff; border:1px solid #e6e8eb; border-radius:18px; overflow:hidden; }
    .cart-head{ padding:16px; display:flex; align-items:center; justify-content:space-between; gap:12px; border-bottom:1px solid #e6e8eb; }
    .cart-table{ width:100%; border-collapse:collapse; }
    .cart-table th, .cart-table td{ padding:12px 14px; border-bottom:1px solid #eef2f6; text-align:left; vertical-align:middle; }
    .cart-item{ display:flex; align-items:center; gap:12px; }
    .cart-img{ width:56px; height:56px; background:#f8fafc; border:1px solid #eef2f6; border-radius:12px; display:flex; align-items:center; justify-content:center; overflow:hidden; }
    .cart-img img{ max-width:100%; max-height:100%; object-fit:contain; }
    .qty-controls{ display:inline-flex; align-items:center; gap:8px; }
    .qty-btn{ padding:6px 10px; border-radius:10px; }
    .muted{ color:#64748b; }
    .cart-foot{ padding:16px; display:flex; align-items:center; justify-content:space-between; gap:12px; }
    .total{ font-size:18px; font-weight:800; }
    .danger{ color:#ef4444; border:1px solid #fecaca; background:#fff; border-radius:12px; padding:8px 12px; cursor:pointer; }
    .danger:hover{ filter: brightness(0.98); }

    /* make qty buttons look like your site buttons */
    .qty-btn{ border:1px solid #dbe3ea; background:#f8fafc; cursor:pointer; }
    .qty-btn:hover{ filter: brightness(0.98); }

    /* ensure logo is visible */
    .corner-logo{ z-index: 5; }
  </style>
</head>

<body class="home-page">

  <!-- Logo like the other pages -->
  <img class="corner-logo" src="assets/images/logo.jpg" alt="Logo">

  <header class="site-header">
    <div class="topbar">
      <form class="search-form" action="products.php" method="GET">
        <input type="text" name="q" placeholder="Search products or brands" />
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
  </header>

  <main class="container">
    <h1 style="margin-top:16px;">My Cart</h1>

    <?php if (!$items): ?>
      <p class="muted">Your cart is empty.</p>
      <p><a href="home.php">Back to Home</a></p>
    <?php else: ?>
      <div class="cart-wrap">

        <div class="cart-head">
          <div><strong><?php echo (int)$cartCount; ?></strong> item(s)</div>

          <form method="POST" style="margin:0;">
            <input type="hidden" name="action" value="clear">
            <button class="danger" type="submit">Clear cart</button>
          </form>
        </div>

        <table class="cart-table">
          <thead>
            <tr>
              <th>Product</th>
              <th>Unit price</th>
              <th>Quantity</th>
              <th>Subtotal</th>
              <th></th>
            </tr>
          </thead>

          <tbody>
            <?php foreach ($items as $it): ?>
              <tr>
                <td>
                  <div class="cart-item">
                    <div class="cart-img">
                      <?php if (!empty($it["img"])): ?>
                        <img src="<?php echo htmlspecialchars($it["img"]); ?>" alt="<?php echo htmlspecialchars($it["name"]); ?>">
                      <?php endif; ?>
                    </div>
                    <div>
                      <div style="font-weight:700;"><?php echo htmlspecialchars($it["name"]); ?></div>
                      <div class="muted">#<?php echo (int)$it["id"]; ?></div>
                    </div>
                  </div>
                </td>

                <td><?php echo number_format($it["price"], 2); ?> ₪</td>

                <td>
                  <div class="qty-controls">
                    <form method="POST" style="margin:0;">
                      <input type="hidden" name="action" value="dec">
                      <input type="hidden" name="id" value="<?php echo (int)$it["id"]; ?>">
                      <button class="qty-btn" type="submit">−</button>
                    </form>

                    <strong><?php echo (int)$it["qty"]; ?></strong>

                    <form method="POST" style="margin:0;">
                      <input type="hidden" name="action" value="inc">
                      <input type="hidden" name="id" value="<?php echo (int)$it["id"]; ?>">
                      <button class="qty-btn" type="submit">+</button>
                    </form>
                  </div>
                </td>

                <td><strong><?php echo number_format($it["line"], 2); ?> ₪</strong></td>

                <td>
                  <form method="POST" style="margin:0;">
                    <input type="hidden" name="action" value="remove">
                    <input type="hidden" name="id" value="<?php echo (int)$it["id"]; ?>">
                    <button class="danger" type="submit">Remove</button>
                  </form>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>

        <div class="cart-foot">
          <div class="total">Total: <?php echo number_format($total, 2); ?> ₪</div>
          <a class="btn" href="products.php">Continue shopping</a>
        </div>

      </div>
    <?php endif; ?>
  </main>

</body>
</html>
04