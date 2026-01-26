<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$user_id = (int)$_SESSION['user_id'];

// Database Connection
$conn = new mysqli('127.0.0.1', 'root', '', 'ddts_pnpki');
if ($conn->connect_error) {
    die(json_encode(['error' => 'DB Connection Failed']));
}

// 1. Fetch Notifications (Limit 10)
$sql = "SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT 10";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

$notifications = [];
while ($row = $result->fetch_assoc()) {
    // Calculate relative time (e.g., "5 mins ago")
    $time_ago = time_elapsed_string($row['created_at']);
    $row['time_ago'] = $time_ago;
    $notifications[] = $row;
}

// 2. Count Unread
$sql_count = "SELECT COUNT(*) as unread FROM notifications WHERE user_id = ? AND is_read = 0";
$stmt_count = $conn->prepare($sql_count);
$stmt_count->bind_param("i", $user_id);
$stmt_count->execute();
$res_count = $stmt_count->get_result();
$unread_count = $res_count->fetch_assoc()['unread'];

echo json_encode([
    'unread_count' => $unread_count,
    'notifications' => $notifications
]);

$conn->close();

// Helper Function for "Time Ago"
function time_elapsed_string($datetime, $full = false) {
    $now = new DateTime;
    $ago = new DateTime($datetime);
    $diff = $now->diff($ago);

    $diff->w = floor($diff->d / 7);
    $diff->d -= $diff->w * 7;

    $string = array('y' => 'yr', 'm' => 'mo', 'w' => 'wk', 'd' => 'day', 'h' => 'hr', 'i' => 'min', 's' => 'sec');
    foreach ($string as $k => &$v) {
        if ($diff->$k) {
            $v = $diff->$k . ' ' . $v . ($diff->$k > 1 ? 's' : '');
        } else {
            unset($string[$k]);
        }
    }
    if (!$full) $string = array_slice($string, 0, 1);
    return $string ? implode(', ', $string) . ' ago' : 'just now';
}
?>