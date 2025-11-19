<?php
// get_dropdown_data.php

// 1. Include your database connection
require_once 'db_international.php';

// 2. Set header to return JSON so JavaScript can read it
header('Content-Type: application/json');

// Initialize response array
$response = [
    'offices' => [],
    'stations' => []
];

try {
    // Check if connection variable exists (Adjust '$conn' if your db_international.php uses a different name like $db or $mysqli)
    if (!isset($conn)) {
        throw new Exception("Database connection variable (\$conn) not found in db_international.php");
    }

    // 3. Fetch Offices from 'Office_StationMain' column
    // distinct() ensures we don't get duplicates
    $officeQuery = "SELECT DISTINCT Office_StationMain FROM cofigurationdata_tbl WHERE Office_StationMain IS NOT NULL AND Office_StationMain != '' ORDER BY Office_StationMain ASC";
    $officeResult = $conn->query($officeQuery);

    if ($officeResult) {
        while ($row = $officeResult->fetch_assoc()) {
            $response['offices'][] = $row['Office_StationMain'];
        }
    }

    // 4. Fetch Stations from 'Office_Station' column
    $stationQuery = "SELECT DISTINCT Office_Station FROM cofigurationdata_tbl WHERE Office_Station IS NOT NULL AND Office_Station != '' ORDER BY Office_Station ASC";
    $stationResult = $conn->query($stationQuery);

    if ($stationResult) {
        while ($row = $stationResult->fetch_assoc()) {
            $response['stations'][] = $row['Office_Station'];
        }
    }

    // 5. Send data back
    echo json_encode($response);

} catch (Exception $e) {
    // If something goes wrong, send error
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
?>