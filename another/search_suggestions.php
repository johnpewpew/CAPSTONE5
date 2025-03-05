<?php
include 'config.php';

if (isset($_GET['query'])) {
    $searchTerm = $_GET['query'] . '%'; // Use starting characters for better match
    $stmt = $conn->prepare("SELECT name FROM items WHERE name LIKE ? LIMIT 5");
    $stmt->bind_param("s", $searchTerm);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $suggestions = [];
    while ($row = $result->fetch_assoc()) {
        $suggestions[] = $row['name'];
    }
    
    echo json_encode($suggestions);
}

