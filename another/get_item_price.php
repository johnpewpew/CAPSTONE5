<?php
include 'config.php';

$item_id = $_GET['item_id'];
$size = $_GET['size'];

$query = $conn->prepare("SELECT medium_price, large_price FROM items WHERE id = ?");
$query->bind_param("i", $item_id);
$query->execute();
$result = $query->get_result()->fetch_assoc();

if ($result) {
    $price = ($size === 'Large') ? $result['large_price'] : $result['medium_price'];
    echo json_encode(['success' => true, 'price' => $price]);
} else {
    echo json_encode(['success' => false]);
}

