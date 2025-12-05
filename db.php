<?php

/**
 * DDTMS Database Connection Script (db.php)
 * * Uses PHP's MySQLi (Object-Oriented) extension for database connection.
 */

// --- 1. Database Configuration Settings ---
// NOTE: Replace these placeholder values with your actual database credentials.
define('DB_HOST', 'localhost');
define('DB_NAME', 'ddts_pnpki');     // Your database name
define('DB_USER', 'root');           // Your database user
define('DB_PASS', '');               // Your database password

// --- 1.1 Map Standard Config to LOCAL Config ---
// This fixes the 500 Error in office_stationManagement.php
define('LOCAL_DB_HOST', DB_HOST);
define('LOCAL_DB_NAME', DB_NAME);
define('LOCAL_DB_USER', DB_USER);
define('LOCAL_DB_PASS', DB_PASS);

// Optional: Write DB config if used elsewhere
define('WRITE_DB_HOST', 'localhost');
define('WRITE_DB_USER', 'root');
define('WRITE_DB_PASS', '');
define('WRITE_DB_NAME', 'ddts_pnpki');


// --- 2. Connection Initialization ---
$conn = null; // Initialize the connection variable

// Suppress default PHP errors during connection attempt for clean error handling
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

try {
    // Create the MySQLi connection instance
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

    // Set charset to UTF-8 for proper handling of special characters
    if (!$conn->set_charset("utf8mb4")) {
        throw new Exception("Error loading character set utf8mb4: " . $conn->error);
    }
    
    // If successful, $conn now holds the active connection object.
    
} catch (\mysqli_sql_exception $e) {
    // --- 3. Error Handling ---
    error_log("Database Connection Error (MySQLi): " . $e->getMessage(), 0); 
    http_response_code(500);
    die("<h1>System Error</h1><p>Could not establish a connection to the database. Please contact system support.</p>");
} catch (\Exception $e) {
    error_log("General Database Setup Error: " . $e->getMessage(), 0); 
    http_response_code(500);
    die("<h1>System Error</h1><p>A configuration error occurred during setup. Please contact support.</p>");
}

?>