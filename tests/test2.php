<?php
session_start();
require_once 'db_config.php';

echo "Step 1 - DB connected OK<br>";

$result = $conn->query("SELECT COUNT(*) AS cnt FROM appliances");
echo "Step 2 - Query ran OK<br>";

$row = $result->fetch_assoc();
echo "Step 3 - Total appliances: " . $row['cnt'] . "<br>";

echo "Step 4 - Session user_id: " . ($_SESSION['user_id'] ?? 'NOT SET') . "<br>";

require_once __DIR__ . '/includes/styles.php';
echo "Step 5 - styles.php loaded OK<br>";

require_once __DIR__ . '/includes/sidebar.php';
echo "Step 6 - sidebar.php loaded OK<br>";

require_once __DIR__ . '/includes/topbar.php';
echo "Step 7 - topbar.php loaded OK<br>";
?>