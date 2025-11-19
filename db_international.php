<?php
// db_international.php
declare(strict_types=1);

// Disable direct error output in production, log instead
ini_set('display_errors', '0');
error_reporting(E_ALL);

define('DB_HOST', '153.92.15.60');
define('DB_USER', 'u645536029_otos_root');
define('DB_PASS', '6yI3PF3OZ');
define('DB_NAME', 'u645536029_otos');
define('DB_CHARSET', 'utf8mb4');

/**
 * Returns a connected mysqli instance or terminates with a generic message.
 *
 * Usage:
 *   $conn = get_db_connection();
 *   // ... use $conn ...
 *   $conn->close();
 */
function get_db_connection(): mysqli
{
    // Throw exceptions for mysqli errors to handle them uniformly
    mysqli_report(MYSQLI_REPORT_STRICT | MYSQLI_REPORT_ERROR);

    try {
        $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
        if (! $conn->set_charset(DB_CHARSET)) {
            // Log charset issues but continue
            error_log('DB charset set failed: ' . $conn->error);
        }
        return $conn;
    } catch (mysqli_sql_exception $e) {
        // Log detailed error for operators/admins, present generic message to users
        error_log('Database connection error: ' . $e->getMessage());
        // Do not expose internal details to end users
        die('Database connection failed. Please try again later.');
    }
}