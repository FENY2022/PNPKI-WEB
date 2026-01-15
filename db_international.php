<?php
// db_international.php
declare(strict_types=1);

// ... (error reporting code)

// RENAME THESE CONSTANTS
define('OTOS_DB_HOST', '153.92.15.60');
define('OTOS_DB_USER', 'u645536029_otos_root');
define('OTOS_DB_PASS', '6yI3PF3OZ');
define('OTOS_DB_NAME', 'u645536029_otos');
define('OTOS_DB_CHARSET', 'utf8mb4');

function get_db_connection(): mysqli
{
    mysqli_report(MYSQLI_REPORT_STRICT | MYSQLI_REPORT_ERROR);

    try {
        // UPDATE THE CONNECTION TO USE THE NEW CONSTANTS
        $conn = new mysqli(OTOS_DB_HOST, OTOS_DB_USER, OTOS_DB_PASS, OTOS_DB_NAME);
        
        if (! $conn->set_charset(OTOS_DB_CHARSET)) {
            error_log('DB charset set failed: ' . $conn->error);
        }
        return $conn;
    } catch (mysqli_sql_exception $e) {
        error_log('Database connection error: ' . $e->getMessage());
        die('Database connection failed. Please try again later.');
    }
}
?>

