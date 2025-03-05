<?php
include 'config.php';
$data = json_decode(file_get_contents("php://input"), true);
$transactionId = $data['transactionId'];
$paymentAmount = $data['paymentAmount'];

// Validate and update the payment status in the database
$update_query = $conn->prepare("UPDATE pending_transactions SET payment_status = 'Paid', payment_amount = ? WHERE id = ?");
$update_query->bind_param("di", $paymentAmount, $transactionId);
$success = $update_query->execute();

echo json_encode(['success' => $success]);

