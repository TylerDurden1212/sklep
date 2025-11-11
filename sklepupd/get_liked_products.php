<?php
session_start();
require_once 'config.php';

header('Content-Type: application/json');

if (empty($_SESSION['user_id'])) {
    echo json_encode(['products' => []]);
    exit;
}

$conn = getDBConnection();
$user_id = $_SESSION['user_id'];

$stmt = $conn->prepare("
    SELECT p.*, l.username AS sprzedawca, l.profile_picture
    FROM likes lk
    JOIN produkty p ON lk.produkt_id = p.id
    LEFT JOIN logi l ON p.id_sprzedawcy = l.id
    WHERE lk.user_id = ?
    ORDER BY lk.created_at DESC
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

$products = [];
while ($row = $result->fetch_assoc()) {
    $products[] = $row;
}

$stmt->close();
$conn->close();

echo json_encode(['products' => $products]);