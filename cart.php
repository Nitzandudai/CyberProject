<?php
session_start();
if (!isset($_SESSION["username"])) { header("Location: login.php"); exit; }

$_SESSION["cart"] = $_SESSION["cart"] ?? [];

$db = new PDO('sqlite:' . __DIR__ . '/app.db');
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

/* Handle cart actions */
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $action = $_POST["action"] ?? "";
    $id = isset($_POST["id"]) ? (int)$_POST["id"] : 0;
    if ($action === "inc" && $id) { $_SESSION["cart"][$id] = ($_SESSION["cart"][$id] ?? 0) + 1; }
    if ($action === "dec" && $id && isset($_SESSION["cart"][$id])) {
        $_SESSION["cart"][$id] -= 1;
        if ($_SESSION["cart"][$id] <= 0) unset($_SESSION["cart"][$id]);
    }
    if ($action === "remove" && $id) { unset($_SESSION["cart"][$id]); }
    if ($action === "clear") { $_SESSION["cart"] = []; }
    header("Location: cart.php");
    exit;
}

$cartCount = array_sum($_SESSION["cart"]);
$ids = array_keys($_SESSION["cart"]);
$productsById = [];

if (!empty($ids)) {
    $ph = implode(",", array_fill(0, count($ids), "?"));
    $sql = "SELECT p.id, p.name, p.price AS original_price, p.image, d.deal_price, d.deal_qty 
            FROM products p 
            LEFT JOIN deals d ON p.id = d.product_id AND d.is_active = 1
            WHERE p.id IN ($ph)";
    $stmt = $db->prepare($sql);
    $stmt->execute($ids);
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $productsById[(int)$r["id"]] = [
            "name"           => $r["name"],
            "original_price" => (float)$r["original_price"],
            "deal_price"     => $r["deal_price"] !== null ? (float)$r["deal_price"] : null,
            "deal_qty"       => $r["deal_qty"] !== null ? (int)$r["deal_qty"] : 1,
            "img"            => $r["image"],
            "is_deal"        => ($r["deal_price"] !== null)
        ];
    }
}

$items = [];
$total = 0.0;

foreach ($_SESSION["cart"] as $id => $qty) {
    $qty = (int)$qty;
    if ($qty <= 0) continue;
    $p = $productsById[(int)$id] ?? null;
    if (!$p) continue;

    $dealQty = $p["deal_qty"];
    $originalPrice = $p["original_price"];
    $dealPrice = $p["deal_price"];

    if ($p["is_deal"] && $dealQty > 1 && $qty >= $dealQty) {
        // המשתמש זכאי למבצע (למשל 2 ב-5)
        $sets = floor($qty / $dealQty); 
        $remainder = $qty % $dealQty;
        $lineTotal = ($sets * $dealPrice) + ($remainder * $originalPrice);
        
        $displayUnitPrice = $dealPrice / $dealQty; // מחיר ממוצע ליחידה במבצע
        $showDealBadge = true;
    } else {
        // אין מבצע (או כי הכמות נמוכה מדי, או כי זה מבצע מחיר פשוט)
        $priceToUse = $p["is_deal"] && $dealQty == 1 ? $dealPrice : $originalPrice;
        $lineTotal = $priceToUse * $qty;
        $displayUnitPrice = $priceToUse;
        $showDealBadge = ($p["is_deal"] && $dealQty == 1); // הצג רק אם זה מבצע הנחה פשוט
    }

    $total += $lineTotal;
    $items[] = [
        "id"      => (int)$id,
        "name"    => $p["name"],
        "price"   => $displayUnitPrice,
        "img"     => $p["img"],
        "qty"     => $qty,
        "line"    => $lineTotal,
        "is_deal" => $p["is_deal"]
    ];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <link rel="stylesheet" href="assets/styles.css?v=99">
  <title>My Cart</title>
  <style>
    .cart-wrap{ margin-top:16px; background:#fff; border:1px solid #e6e8eb; border-radius:18px; overflow:hidden; }
    .cart-table{ width:100%; border-collapse:collapse; }
    .cart-table td, .cart-table th{ padding:12px; border-bottom:1px solid #eef2f6; text-align:left; }
    .sale-badge { color: #ef4444; font-size: 0.75em; font-weight: 800; display: block; }
    .total{ font-size:18px; font-weight:800; }
  </style>
</head>
<body class="home-page">
  <img class="corner-logo" src="assets/images/logo.jpg" alt="Logo">
  <header class="site-header">
    <div class="topbar">
      <nav class="toplinks">
        <a href="home.php">Home</a> | <a href="products.php">All Products</a> | <a href="cart.php">My Cart (<?php echo $cartCount; ?>)</a>
      </nav>
    </div>
  </header>
  <main class="container">
    <h1>My Cart</h1>
    <?php if (!$items): ?>
      <p>Your cart is empty. <a href="home.php">Back to Home</a></p>
    <?php else: ?>
      <div class="cart-wrap">
        <table class="cart-table">
          <thead><tr><th>Product</th><th>Unit Price</th><th>Qty</th><th>Subtotal</th><th></th></tr></thead>
          <tbody>
            <?php foreach ($items as $it): ?>
              <tr>
                <td>
                    <div style="display: flex; align-items: center; gap: 15px;">
                        <img src="<?php echo htmlspecialchars($it["img"]); ?>" style="width: 50px; height: 50px; object-fit: cover; border-radius: 8px;">
                        <strong><?php echo htmlspecialchars($it["name"]); ?></strong>
                    </div>
                </td>
                <td>
                    <?php echo number_format($it["price"], 2); ?> ₪
                    <?php 
                      // הצגת התווית רק אם הכמות בסל גדולה או שווה לכמות המבצע
                      // או אם זה מבצע הנחה פשוט (שבו deal_qty הוא 1)
                      if ($it["is_deal"] && $it["qty"] >= $productsById[$it["id"]]["deal_qty"]): 
                    ?>
                        <span class="sale-badge">DEAL APPLIED</span>
                    <?php endif; ?>
                </td>
                <td>
                  <form method="POST" style="display:inline;"><input type="hidden" name="action" value="dec"><input type="hidden" name="id" value="<?php echo $it["id"]; ?>"><button type="submit">−</button></form>
                  <?php echo $it["qty"]; ?>
                  <form method="POST" style="display:inline;"><input type="hidden" name="action" value="inc"><input type="hidden" name="id" value="<?php echo $it["id"]; ?>"><button type="submit">+</button></form>
                </td>
                <td><strong><?php echo number_format($it["line"], 2); ?> ₪</strong></td>
                <td><form method="POST"><input type="hidden" name="action" value="remove"><input type="hidden" name="id" value="<?php echo $it["id"]; ?>"><button type="submit">Remove</button></form></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
        <div style="padding:20px; text-align:right;">
          <div class="total">Total: <?php echo number_format($total, 2); ?> ₪</div>
        </div>
      </div>
    <?php endif; ?>
  </main>
</body>
</html>