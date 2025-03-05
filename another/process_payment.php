<?php
include 'config.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $transaction_id = $_POST['transaction_id'];
    $payment = $_POST['payment'];

    // Validate transaction ID and payment amount
    if (empty($transaction_id) || empty($payment)) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid transaction details']);
        exit;
    }

    // Delete the transaction from the database
    $stmt = $conn->prepare("DELETE FROM pending_transactions WHERE id = ?");
    $stmt->bind_param('i', $transaction_id);

    if ($stmt->execute()) {
        echo json_encode(['status' => 'success']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Could not complete the transaction.']);
    }

    $stmt->close();
}