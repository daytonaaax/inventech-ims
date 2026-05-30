<?php

//  InvenTech — Database Configuration
//  File: db_config.php

// ── CONNECTION SETTINGS ──────────────────────────────────────
define('DB_HOST', 'localhost');    // use localhost
define('DB_USER', 'root');         // Default MySQL username
define('DB_PASS', '');             // Default MySQL password (empty)
define('DB_NAME', 'inventech_db'); // database name

try {

    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

    // If connection_error is set, something went wrong - throw an exception
    if ($conn->connect_error) {
        throw new Exception("Connection failed: " . $conn->connect_error);
    }

    // Set UTF-8 encoding so special characters (e.g. ñ, é) work correctly
    $conn->set_charset('utf8');

} catch (Exception $e) {

    // Show a safe, clean error page instead of raw PHP errors
    // Raw errors can expose server paths which is a security risk
    die("
        <div style='
            font-family: sans-serif;
            background: #fee2e2;
            color: #991b1b;
            border: 1px solid #fca5a5;
            border-left: 4px solid #ef4444;
            border-radius: 12px;
            padding: 24px 32px;
            max-width: 520px;
            margin: 60px auto;
            box-shadow: 0 4px 16px rgba(0,0,0,.08);
        '>
            <strong style='font-size:16px'>&#9888;&#65039; System Error</strong><br><br>
            Unable to connect to the database. Please make sure:<br><br>
            &bull; MySQL is running in Laragon<br>
            &bull; The database <strong>inventech_db</strong> exists in phpMyAdmin<br>
            &bull; Your database credentials are correct<br><br>
            <small style='color:#b91c1c'>Error Reference: DB_CONNECTION_FAILED</small>
        </div>
    ");
}
?>
