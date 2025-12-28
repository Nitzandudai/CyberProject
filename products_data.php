<?php
// products_data.php

$CATEGORIES = [
  "fruits_veg" => ["label" => "Fruits & Vegetables", "icon" => "🥬"],
  "dairy_eggs" => ["label" => "Dairy & Eggs",        "icon" => "🥛"],
  "snacks_dry" => ["label" => "Snacks & Pantry",     "icon" => "🍪"],
  "meat_fish"  => ["label" => "Meat & Fish",         "icon" => "🐟"],
  "frozen"     => ["label" => "Frozen",              "icon" => "🧊"],
  "soft_drink" => ["label" => "Soft Drinks",         "icon" => "🥤"],
  "alcohol"    => ["label" => "Alcohol",             "icon" => "🍺"],
];

/*
  IMPORTANT:
  Use / (slashes), not \ (backslashes)
  And make sure the folder names match your project:
  assets/images/productsImg/fruitsAndVegs/banana.jpg
*/

$PRODUCTS = [
  // ===== Fruits & Veg =====
  1001 => ["name"=>"Banana",          "price"=>7.90,  "cat"=>"fruits_veg", "img"=>"assets/images/productsImg/fruitsAndVegs/banana.jpg"],
  1002 => ["name"=>"Pink Lady Apple", "price"=>9.90,  "cat"=>"fruits_veg", "img"=>"assets/images/productsImg/fruitsAndVegs/pinkLadyApple.jpg"],
  1003 => ["name"=>"Tomato",          "price"=>9.90,  "cat"=>"fruits_veg", "img"=>"assets/images/productsImg/fruitsAndVegs/tomato.jpg"],
  1004 => ["name"=>"Cabbage",         "price"=>6.90,  "cat"=>"fruits_veg", "img"=>"assets/images/productsImg/fruitsAndVegs/cabbage.jpg"],

  // ===== Dairy & Eggs =====
  2001 => ["name"=>"Organic Eggs",    "price"=>17.90, "cat"=>"dairy_eggs", "img"=>"assets/images/productsImg/dairyAndEggs/organicEggs.jpg"],
  2002 => ["name"=>"Milk 3%",         "price"=>6.50,  "cat"=>"dairy_eggs", "img"=>"assets/images/productsImg/dairyAndEggs/milk.jpg"],

  // ===== Snacks & Pantry =====
  3001 => ["name"=>"Bamba",           "price"=>3.90,  "cat"=>"snacks_dry", "img"=>"assets/images/productsImg/snacksAndDry/bamba.jpg"],

  // ===== Alcohol =====
  7001 => ["name"=>"Wine for Heroes", "price"=>49.90, "cat"=>"alcohol",    "img"=>"assets/images/productsImg/alcohol/winrForHeros copy.png"],
];
