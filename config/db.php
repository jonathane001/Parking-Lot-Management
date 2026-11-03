<?php
// Database settings. Change these to match your computer.
// XAMPP: user "root", password "" (empty), port 3306
// MAMP:  user "root", password "root", port 8889
define('DB_HOST', '127.0.0.1');
define('DB_USER', 'root');
define('DB_PASS', ' ');
define('DB_NAME', 'parking_lot');
define('DB_PORT', 3306);

// Make mysqli throw exceptions so errors can be caught with try/catch
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

try {
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME, DB_PORT);
    $conn->set_charset('utf8mb4');
} catch (mysqli_sql_exception $e) {
    // Don't show the real error to users, it can leak details about the server
    die('Could not connect to the database. Check the settings in config/db.php.');
}
