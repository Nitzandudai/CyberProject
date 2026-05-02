<?php
session_start();
if (!isset($_SESSION["username"])) { header("Location: login.php"); exit; }

$_SESSION["cart"] = $_SESSION["cart"] ?? [];

try {
    // חיבור למסד הנתונים הראשי (מוצרים ומבצעים)[cite: 3]
    $db = new PDO('sqlite:' . __DIR__ . '/app.db');
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // חיבור למסד הנתונים המבודד (קופונים)[cite: 3]
    $internal_db = new PDO('sqlite:' . __DIR__ . '/internal.db');
    $internal_db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Database Connection Error: " . $e->getMessage());
}

// --- לוגיקת קופונים (פגיעה ל-Time-based Blind SQLi וחסימת UNION) ---
$coupon_msg = "";
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["apply_coupon"])) {
    $code_input = $_POST["coupon_code"] ?? "";
    if (preg_match('/union/i', $code_input)) {
        $coupon_msg = "<span style='color:#f97316; font-weight:700; margin-top:8px; display:block;'>Security Alert: UNION keyword is forbidden!</span>";
    } else {
        $sql = "SELECT discount_val FROM CUPONS WHERE encrypted_code = '$code_input'";
        try {
            $res = $internal_db->query($sql);
            $coupon = $res->fetch(PDO::FETCH_ASSOC);
            if ($coupon) {
                $_SESSION["discount_rate"] = (int)$coupon["discount_val"];
                $coupon_msg = "<span style='color:#22c55e; font-weight:700; margin-top:8px; display:block;'>Coupon Applied!</span>";
            } else {
                unset($_SESSION["discount_rate"]);
                $coupon_msg = "<span style='color:#ef4444; font-weight:700; margin-top:8px; display:block;'>Invalid Coupon Code.</span>";
            }
        } catch (PDOException $e) {
            $coupon_msg = "<span style='color:#ef4444; font-weight:700; margin-top:8px; display:block;'>Invalid Coupon Code.</span>";
        }
    }
}
$current_discount = $_SESSION["discount_rate"] ?? 0;

/* טיפול בפעולות עגלה (פלוס/מינוס/הסרה) */
if ($_SERVER["REQUEST_METHOD"] === "POST" && !isset($_POST["apply_coupon"])) {
    $action = $_POST["action"] ?? "";
    $id = isset($_POST["id"]) ? (int)$_POST["id"] : 0;
    if ($action === "inc" && $id) { $_SESSION["cart"][$id] = ($_SESSION["cart"][$id] ?? 0) + 1; }
    if ($action === "dec" && $id && isset($_SESSION["cart"][$id])) {
        $_SESSION["cart"][$id] -= 1;
        if ($_SESSION["cart"][$id] <= 0) unset($_SESSION["cart"][$id]);
    }
    if ($action === "remove" && $id) { unset($_SESSION["cart"][$id]); }
    header("Location: cart.php");
    exit;
}

// שליפת נתונים כולל מבצעים מטבלת deals
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
            "name" => $r["name"], "original_price" => (float)$r["original_price"],
            "deal_price" => $r["deal_price"] !== null ? (float)$r["deal_price"] : null,
            "deal_qty" => $r["deal_qty"] !== null ? (int)$r["deal_qty"] : 1,
            "img" => $r["image"], "is_deal" => ($r["deal_price"] !== null)
        ];
    }
}

$items = [];
$subtotal = 0.0;
foreach ($_SESSION["cart"] as $id => $qty) {
    $qty = (int)$qty;
    $p = $productsById[(int)$id] ?? null;
    if (!$p) continue;

    $dealQty = $p["deal_qty"];
    $originalPrice = $p["original_price"];
    $dealPrice = $p["deal_price"];

    if ($p["is_deal"] && $dealQty > 1 && $qty >= $dealQty) {
        $sets = floor($qty / $dealQty); 
        $remainder = $qty % $dealQty;
        $lineTotal = ($sets * $dealPrice) + ($remainder * $originalPrice);
        $displayUnitPrice = $lineTotal / $qty;
    } else {
        $priceToUse = ($p["is_deal"] && $dealQty == 1) ? $dealPrice : $originalPrice;
        $lineTotal = $priceToUse * $qty;
        $displayUnitPrice = $priceToUse;
    }

    $subtotal += $lineTotal;
    $items[] = [
        "id" => $id, "name" => $p["name"], "img" => $p["img"], 
        "qty" => $qty, "line" => $lineTotal, "price" => $displayUnitPrice, "is_deal" => $p["is_deal"]
    ];
}

$discount_amount = ($subtotal * $current_discount) / 100;
$final_total = $subtotal - $discount_amount;
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <link rel="stylesheet" href="assets/styles.css?v=101">
  <title>My Cart</title>
  <style>
    .cart-wrap { margin-top:20px; background:#fff; border:1px solid #e2e8f0; border-radius:18px; padding: 24px; box-shadow: 0 4px 6px -1px rgb(0 0 0 / 0.1); }
    .cart-table { width:100%; border-collapse:collapse; margin-bottom: 24px; }
    .cart-table th { text-align:left; color:#475569; font-size:14px; padding-bottom:12px; border-bottom:1px solid #f1f5f9; }
    .cart-table td { padding:16px 0; border-bottom:1px solid #f1f5f9; }
    .deal-badge { color:#ef4444; font-size:12px; font-weight:800; display:block; margin-top:2px; }
    .qty-controls { display:flex; align-items:center; gap:12px; background:#f8fafc; border:1px solid #e2e8f0; border-radius:20px; padding:4px 12px; width:fit-content; }
    .qty-btn { background:none; border:none; cursor:pointer; font-size:18px; color:#64748b; font-weight:bold; }
    .remove-btn { border:1px solid #e2e8f0; border-radius:12px; background:#fff; color:#475569; padding:6px 16px; font-size:13px; font-weight:600; cursor:pointer; }
    .remove-btn:hover { background:#f8fafc; }
    .coupon-input { padding:10px 16px; border-radius:12px; border:1px solid #cbd5e1; width:200px; }
    .apply-btn { padding:10px 24px; border-radius:12px; background:#475569; color:#fff; border:none; font-weight:700; cursor:pointer; margin-left:8px; }
    .total-labels { text-align:right; color:#64748b; font-size:15px; }
    .final-total { font-size:28px; font-weight:900; color:#0f172a; margin-top:4px; }
    .discount-line { color:#ef4444; font-weight:700; }
    .pay-button { display:inline-block; margin-top:16px; padding:14px 32px; background:#2563eb; color:#fff; border-radius:12px; font-weight:800; text-decoration:none; font-size:16px; }
  </style>
</head>
<body class="home-page">
  <img class="corner-logo" src="assets/images/logo.jpg" alt="Logo">
  <header class="site-header">
    <div class="topbar">
      <nav class="toplinks">
        <a href="home.php">Home Page</a> | <a href="products.php">All Products</a> | <a href="cart.php">My Cart (<?= $cartCount ?>)</a>
      </nav>
    </div>
  </header>

  <main class="container">
    <h1 style="font-size:32px; font-weight:900; color:#0f172a;">My Cart</h1>
    
    <?php if (!$items): ?>
      <div class="cart-wrap"><p>Your cart is empty. <a href="home.php">Back to Shopping</a></p></div>
    <?php else: ?>
      <div class="cart-wrap">
        <table class="cart-table">
          <thead>
            <tr>
              <th>Product</th>
              <th>Unit Price</th>
              <th style="text-align:center">Qty</th>
              <th>Subtotal</th>
              <th></th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($items as $it): ?>
              <tr>
                <td style="width:35%">
                  <div style="display:flex; align-items:center; gap:16px;">
                    <img src="<?= htmlspecialchars($it["img"]) ?>" style="width:50px; height:50px; object-fit:cover; border-radius:8px; border:1px solid #f1f5f9;">
                    <strong style="color:#1e293b;"><?= htmlspecialchars($it["name"]) ?></strong>
                  </div>
                </td>
                <td style="width:20%">
                  <div style="color:#0f172a; font-weight:600;"><?= number_format($it["price"], 2) ?> ₪</div>
                  <?php if ($it["is_deal"]): ?><span class="deal-badge">DEAL APPLIED</span><?php endif; ?>
                </td>
                <td style="width:15%">
                  <div style="display:flex; justify-content:center;">
                    <div class="qty-controls">
                      <form method="POST"><input type="hidden" name="action" value="dec"><input type="hidden" name="id" value="<?= $it["id"] ?>"><button type="submit" class="qty-btn">−</button></form>
                      <span style="font-weight:700; color:#0f172a; min-width:20px; text-align:center;"><?= $it["qty"] ?></span>
                      <form method="POST"><input type="hidden" name="action" value="inc"><input type="hidden" name="id" value="<?= $it["id"] ?>"><button type="submit" class="qty-btn">+</button></form>
                    </div>
                  </div>
                </td>
                <td style="width:20%; color:#0f172a; font-weight:800; font-size:17px;"><?= number_format($it["line"], 2) ?> ₪</td>
                <td style="width:10%; text-align:right;">
                  <form method="POST"><input type="hidden" name="action" value="remove"><input type="hidden" name="id" value="<?= $it["id"] ?>"><button type="submit" class="remove-btn">Remove</button></form>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
        
        <div style="display:flex; justify-content:space-between; align-items:flex-start;">
          <div>
            <form method="POST" style="display:flex; align-items:center;">
              <input type="text" name="coupon_code" placeholder="Enter Secret Coupon" class="coupon-input">
              <button type="submit" name="apply_coupon" class="apply-btn">Apply</button>
            </form>
            <?= $coupon_msg ?>
          </div>
          
          <div class="total-labels">
            <div>Subtotal: <?= number_format($subtotal, 2) ?> ₪</div>
            <?php if ($current_discount > 0): ?>
              <div class="discount-line">Discount (<?= $current_discount ?>%): -<?= number_format($discount_amount, 2) ?> ₪</div>
            <?php endif; ?>
            <div class="final-total">Total: <?= number_format($final_total, 2) ?> ₪</div>
            <a href="payment_method.php" class="pay-button">Go To Pay</a>
          </div>
        </div>
      </div>
    <?php endif; ?>
  </main>
</body>
</html>
