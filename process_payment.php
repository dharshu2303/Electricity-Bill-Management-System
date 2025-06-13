<?php
require_once 'db_connect.php';
session_start();
header('Content-Type: application/json');
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not logged in']);
    exit;
}
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $bill_id = $_POST['bill_id'] ?? 0;
    $user_id = $_SESSION['user_id'];
    $stmt = $conn->prepare("SELECT * FROM bills WHERE bill_id = ? AND user_id = ?");
    $stmt->bind_param("ii", $bill_id, $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows === 1) {
        $bill = $result->fetch_assoc();
        
        if ($bill['status'] === 'paid') {
            echo json_encode(['success' => false, 'message' => 'Bill already paid']);
            exit;
        }
        $transaction_id = 'TXN' . uniqid();
        $update_stmt = $conn->prepare("UPDATE bills SET status = 'paid', paid_date = CURDATE(), transaction_id = ? WHERE bill_id = ?");
        $update_stmt->bind_param("si", $transaction_id, $bill_id);
if ($update_stmt->execute()) {
    $points = floor($bill['amount'] / 10);
    $reward_stmt = $conn->prepare("INSERT INTO reward_points (user_id, points_earned, reason) VALUES (?, ?, 'Bill payment')");
    $reward_stmt->bind_param("ii", $user_id, $points);
    $reward_stmt->execute();
    echo json_encode([
        'success' => true, 
        'transaction_id' => $transaction_id,
        'bill_id' => $bill_id 
    ]);
} else {
            echo json_encode(['success' => false, 'message' => 'Payment processing failed']);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Invalid bill']);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
}
?>     