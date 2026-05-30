<?php

//  InvenTech — Get Next SKU
//  File: get_next_sku.php
//  called via fetch() from appliances.php add modal
//  returns the next available SKU string (e.g. APL-005)

session_start();
require_once 'db_config.php';

//security code blocks
if (!isset($_SESSION['user_id'])) {
    echo 'APL-001';
    exit();
}

//get last sku to avoid duplicates when adding appliances
$stmt = $conn->prepare("SELECT sku FROM appliances ORDER BY appliance_id DESC LIMIT 1");
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();
$stmt->close();

//does the math
if ($row) {
    $last_num = intval(substr($row['sku'], 4)); //remove 'APL-' then proceed to the next 3 digits
    echo 'APL-' . str_pad($last_num + 1, 3, '0', STR_PAD_LEFT); //add leading zeros
} else {
    echo 'APL-001'; //does nothing
}
?>
