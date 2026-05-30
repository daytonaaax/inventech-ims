<?php

//  InvenTech — Logout
//  File: logout.php

session_start();
require_once 'db_config.php';

// Log the logout action before destroying session
if (isset($_SESSION['user_id'])) {
    $uid  = $_SESSION['user_id'];
    $name = $_SESSION['full_name'];
    $desc = "User " . $name . " signed out of the system.";
    $log  = $conn->prepare("INSERT INTO activity_logs (user_id, action, description) VALUES (?, 'login', ?)");
    $log->bind_param('is', $uid, $desc);
    $log->execute();
    $log->close();
}

// Destroy session and redirect
session_unset();
session_destroy();
header('Location: login.php');
exit();
?>
