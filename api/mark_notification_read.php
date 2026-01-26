<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false]);
    exit;
}

$user_id = (int)$_SESSION['user_id'];
$conn = new mysqli('127.0.0.1', 'root', '', 'ddts_pnpki');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);

    if (isset($data['action']) && $data['action'] == 'mark_all') {
        // Mark ALL as read
        $stmt = $conn->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ?");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
    } elseif (isset($data['id'])) {
        // Mark ONE as read
        $stmt = $conn->prepare("UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?");
        $notif_id = (int)$data['id'];
        $stmt->bind_param("ii", $notif_id, $user_id);
        $stmt->execute();
    }
    
    echo json_encode(['success' => true]);
}
$conn->close();
?>