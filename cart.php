<?php
session_start();
if (!isset($_SESSION["username"])) { header("Location: login.php"); exit; }

$_SESSION["cart"] = $_SESSION["cart"] ?? [];

$db = new PDO('sqlite:' . __DIR__ . '/app.db');
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// --- Coupon logic ---
$coupon_msg = "";
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["apply_coupon"])) {
    $code_input = $_POST["coupon_code"] ?? "";
    
    // Fix: use CAST to convert user input string to BLOB for comparison
    $stmt = $db->prepare("SELECT discount_val FROM internal_coupons WHERE encrypted_code = CAST(? AS BLOB)");
    $stmt->execute([$code_input]);
    $coupon = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($coupon) {
        $_SESSION["discount_rate"] = (int)$coupon["discount_val"];
        $coupon_msg = "<span style='color:green;'>Coupon Applied: " . $_SESSION["discount_rate"] . "% OFF!</span>";
    } else {
        unset($_SESSION["discount_rate"]);
        $coupon_msg = "<span style='color:red;'>Invalid Coupon Code.</span>";
    }
}
$current_discount = $_SESSION["discount_rate"] ?? 0;
// -----------------------

/* Handle cart actions */
if ($_SERVER["REQUEST_METHOD"] === "POST" && !isset($_POST["apply_coupon"])) {
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
$subtotal = 0.0;

foreach ($_SESSION["cart"] as $id => $qty) {
    $qty = (int)$qty;
    if ($qty <= 0) continue;
    $p = $productsById[(int)$id] ?? null;
    if (!$p) continue;

    $dealQty = $p["deal_qty"];
    $originalPrice = $p["original_price"];
    $dealPrice = $p["deal_price"];

    if ($p["is_deal"] && $dealQty > 1 && $qty >= $dealQty) {
        $sets = floor($qty / $dealQty); 
        $remainder = $qty % $dealQty;
        $lineTotal = ($sets * $dealPrice) + ($remainder * $originalPrice);
        $displayUnitPrice = $dealPrice / $dealQty;
    } else {
        $priceToUse = $p["is_deal"] && $dealQty == 1 ? $dealPrice : $originalPrice;
        $lineTotal = $priceToUse * $qty;
        $displayUnitPrice = $priceToUse;
    }

    $subtotal += $lineTotal;
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

// Final discount calculation
$discount_amount = ($subtotal * $current_discount) / 100;
$final_total = $subtotal - $discount_amount;
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
    .total-area { padding:20px; border-top: 1px solid #eee; display: flex; justify-content: space-between; align-items: flex-start; }
    .total-label { font-size:18px; font-weight:800; }
    .final-price { font-size: 24px; font-weight: 800; color: #1e293b; }
    .discount-line { color: #ef4444; font-weight: 600; }
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
                    <?php if ($it["is_deal"] && $it["qty"] >= $productsById[$it["id"]]["deal_qty"]): ?>
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
        
        <div class="total-area">
          <div class="coupon-section">
            <form method="POST" style="display: flex; gap: 10px;">
                <input type="text" name="coupon_code" placeholder="Enter Secret Coupon" style="padding: 10px; border-radius: 8px; border: 1px solid #cbd5e1;">
                <button type="submit" name="apply_coupon" style="background: #475569; color: white; padding: 10px 20px; border: none; border-radius: 8px; cursor: pointer; font-weight: 600;">Apply</button>
            </form>
            <div style="margin-top: 10px;"><?php echo $coupon_msg; ?></div>
          </div>

          <div style="text-align: right;">
            <div style="color: #64748b;">Subtotal: <?php echo number_format($subtotal, 2); ?> ₪</div>
            <?php if ($current_discount > 0): ?>
                <div class="discount-line">Discount (<?php echo $current_discount; ?>%): -<?php echo number_format($discount_amount, 2); ?> ₪</div>
            <?php endif; ?>
            <div class="final-price">Total: <?php echo number_format($final_total, 2); ?> ₪</div>
          </div>
        </div>
      </div>
    <?php endif; ?>
  </main>
</body>
</html>