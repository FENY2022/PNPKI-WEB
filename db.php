<?php

/**
 * DDTMS Database Connection Script (db.php)
 * * Uses PHP's MySQLi (Object-Oriented) extension for database connection.
 */

// --- 1. Database Configuration Settings ---
// NOTE: Replace these placeholder values with your actual database credentials.
define('DB_HOST', 'localhost');
define('DB_NAME', 'ddts_pnpki');     // Your database name
define('DB_USER', 'root');    // Your database user
define('DB_PASS', ''); // Your database password

// --- 2. Connection Initialization ---
$conn = null; // Initialize the connection variable

// Suppress default PHP errors during connection attempt for clean error handling
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

try {
    // Create the MySQLi connection instance
    // $conn now holds the database connection object
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

    // Set charset to UTF-8 for proper handling of special characters
    if (!$conn->set_charset("utf8mb4")) {
        throw new Exception("Error loading character set utf8mb4: " . $conn->error);
    }
    
    // If successful, $conn now holds the active connection object.
    
} catch (\mysqli_sql_exception $e) {
    // --- 3. Error Handling ---
    // If the connection fails, stop execution and log/display the error.
    
    // Log the error message (recommended for production environments)
    error_log("Database Connection Error (MySQLi): " . $e->getMessage(), 0); 
    
    // Display a user-friendly error message (avoid showing $e->getMessage() in production)
    http_response_code(500);
    die("<h1>System Error</h1><p>Could not establish a connection to the database. Please contact system support.</p>");
} catch (\Exception $e) {
    // Handle other setup errors (like charset setting)
    error_log("General Database Setup Error: " . $e->getMessage(), 0); 
    http_response_code(500);
    die("<h1>System Error</h1><p>A configuration error occurred during setup. Please contact support.</p>");
}

?>