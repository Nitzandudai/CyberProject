<?php
session_start();
if (!isset($_SESSION["username"])) { header("Location: login.php"); exit; }

$_SESSION["cart"] = $_SESSION["cart"] ?? [];

$db = new PDO('sqlite:' . __DIR__ . '/app.db');
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$cartCount = array_sum($_SESSION["cart"]);
$ids = array_keys($_SESSION["cart"]);
$productsById = [];

if (!empty($ids)) {
    $ph = implode(",", array_fill(0, count($ids), "?"));
    $sql = "SELECT p.id, p.name, p.price AS original_price, d.deal_price, d.deal_qty
            FROM products p
            LEFT JOIN deals d ON p.id = d.product_id AND d.is_active = 1
            WHERE p.id IN ($ph)";
    $stmt = $db->prepare($sql);
    $stmt->execute($ids);
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $productsById[(int)$r["id"]] = [
            "original_price" => (float)$r["original_price"],
            "deal_price"     => $r["deal_price"] !== null ? (float)$r["deal_price"] : null,
            "deal_qty"       => $r["deal_qty"] !== null ? (int)$r["deal_qty"] : 1,
            "is_deal"        => ($r["deal_price"] !== null)
        ];
    }
}

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
        $subtotal += ($sets * $dealPrice) + ($remainder * $originalPrice);
    } else {
        $priceToUse = $p["is_deal"] && $dealQty == 1 ? $dealPrice : $originalPrice;
        $subtotal += $priceToUse * $qty;
    }
}

$current_discount = $_SESSION["discount_rate"] ?? 0;
$discount_amount = ($subtotal * $current_discount) / 100;
$final_total = $subtotal - $discount_amount;
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <link rel="stylesheet" href="assets/styles.css?v=99">
  <title>Payment Method</title>
  <style>
    .payment-panel {
      max-width: 760px;
      margin-top: 16px;
      background: #fff;
      border: 1px solid #e6e8eb;
      border-radius: 18px;
      padding: 24px;
    }
    .payment-options {
      display: grid;
      gap: 12px;
      margin: 20px 0;
    }
    .payment-option {
      display: flex;
      align-items: center;
      gap: 12px;
      padding: 14px;
      border: 1px solid #e2e8f0;
      border-radius: 12px;
      font-weight: 700;
      cursor: pointer;
    }
    .payment-option input { margin: 0; }
    .card-details {
      display: grid;
      gap: 12px;
      margin: -4px 0 4px 30px;
      padding: 16px;
      border: 1px solid #e2e8f0;
      border-radius: 12px;
      background: #f8fafc;
    }
    .card-details.is-hidden { display: none; }
    .card-details label {
      display: grid;
      gap: 6px;
      color: #475569;
      font-weight: 700;
    }
    .card-details input {
      width: 100%;
      padding: 11px;
      border: 1px solid #cbd5e1;
      border-radius: 10px;
      font: inherit;
      color: #0f172a;
      background: #fff;
    }
    .card-fields-row {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 12px;
    }
    .pay-summary {
      display: flex;
      justify-content: space-between;
      align-items: center;
      padding-top: 18px;
      border-top: 1px solid #eef2f6;
    }
    .pay-total {
      font-size: 24px;
      font-weight: 800;
      color: #1e293b;
    }
    .pay-submit {
      background: #2563eb;
      color: #fff;
      border-color: #2563eb;
      padding: 12px 24px;
      border-radius: 12px;
      font-weight: 800;
    }
  </style>
</head>
<body class="home-page">
  <?php include 'header.php'; ?>
  <main class="container">
    <h1>Payment Method</h1>

    <?php if ($cartCount <= 0): ?>
      <p>Your cart is empty. <a href="home.php">Back to Home</a></p>
    <?php else: ?>
      <form class="payment-panel" method="POST">
        <h2>Choose how you want to pay</h2>
        <div class="payment-options">
          <label class="payment-option">
            <input type="radio" name="payment_method" value="credit_card" checked>
            Credit Card
          </label>
          <div id="card-details" class="card-details">
            <label>
              Credit Card Number
              <input type="text" name="card_number" inputmode="numeric" autocomplete="cc-number" placeholder="1234 5678 9012 3456">
            </label>
            <div class="card-fields-row">
              <label>
                Expiry Date
                <input type="month" name="card_expiry" autocomplete="cc-exp">
              </label>
              <label>
                Owner ID
                <input type="text" name="card_owner_id" inputmode="numeric" placeholder="Card owner ID">
              </label>
            </div>
          </div>
          <label class="payment-option">
            <input type="radio" name="payment_method" value="paypal">
            PayPal
          </label>
          <label class="payment-option">
            <input type="radio" name="payment_method" value="cash">
            Cash on Delivery
          </label>
        </div>

        <div class="pay-summary">
          <div>
            <div>Items: <?php echo (int)$cartCount; ?></div>
            <div class="pay-total">Total: <?php echo number_format($final_total, 2); ?> ₪</div>
          </div>
          <button class="pay-submit" type="submit">Pay Now</button>
        </div>
      </form>
    <?php endif; ?>
  </main>
  <script>
    const paymentMethods = document.querySelectorAll('input[name="payment_method"]');
    const cardDetails = document.getElementById('card-details');

    function updateCardDetails() {
      const selectedMethod = document.querySelector('input[name="payment_method"]:checked');
      cardDetails.classList.toggle('is-hidden', selectedMethod.value !== 'credit_card');
    }

    paymentMethods.forEach((method) => method.addEventListener('change', updateCardDetails));
    updateCardDetails();
  </script>
</body>
</html>
