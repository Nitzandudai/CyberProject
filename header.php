<?php
$cartCount = isset($_SESSION["cart"]) ? array_sum($_SESSION["cart"]) : 0;
?>
<div style="background: #f4f4f4; text-align: right; padding: 8px 25px; font-size: 14px; color: #5C9B81; border-bottom: 1px solid #ddd;">
    Logged in as: <strong><?php echo htmlspecialchars($_SESSION['username']); ?></strong>
</div>

<header class="site-header">
  <div class="topbar">
    <img class="corner-logo" src="assets/images/logo.jpg" alt="Logo">
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
      <a class="cat" href="products.php?cat=alcohol">🍷 Alcohol</a>
      <a class="cat" href="products.php?cat=electronics">🔌 Electrical Appliances</a>
  </div>
</header>
