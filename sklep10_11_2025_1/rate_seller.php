<?php
session_start();
require_once 'config.php';

header('Content-Type: application/json');

if (empty($_SESSION['user_id']) || $_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Brak autoryzacji']);
    exit;
}

$conn = getDBConnection();
$buyer_id = $_SESSION['user_id'];
$seller_id = intval($_POST['seller_id'] ?? 0);
$produkt_id = intval($_POST['produkt_id'] ?? 0);
$rating = intval($_POST['rating'] ?? 0);
$comment = trim($_POST['comment'] ?? '');

// Walidacja
if ($seller_id <= 0 || $produkt_id <= 0) {
    echo json_encode(['success' => false, 'error' => 'Nieprawidłowe dane']);
    exit;
}

if ($rating < 1 || $rating > 5) {
    echo json_encode(['success' => false, 'error' => 'Ocena musi być od 1 do 5']);
    exit;
}

if ($buyer_id == $seller_id) {
    echo json_encode(['success' => false, 'error' => 'Nie możesz ocenić sam siebie']);
    exit;
}

// Sprawdź czy kupił produkt
$stmt = $conn->prepare("SELECT id FROM produkty WHERE id = ? AND buyer_id = ? AND is_sold = 1");
$stmt->bind_param("ii", $produkt_id, $buyer_id);
$stmt->execute();
if ($stmt->get_result()->num_rows === 0) {
    $stmt->close();
    $conn->close();
    echo json_encode(['success' => false, 'error' => 'Możesz ocenić tylko zakupione produkty']);
    exit;
}
$stmt->close();

// Sprawdź czy już ocenił
$stmt = $conn->prepare("SELECT id FROM ratings WHERE seller_id = ? AND buyer_id = ? AND produkt_id = ?");
$stmt->bind_param("iii", $seller_id, $buyer_id, $produkt_id);
$stmt->execute();
$existing = $stmt->get_result()->fetch_assoc();
$stmt->close();

if ($existing) {
    // Aktualizuj istniejącą ocenę
    $stmt = $conn->prepare("UPDATE ratings SET rating = ?, comment = ? WHERE id = ?");
    $stmt->bind_param("isi", $rating, $comment, $existing['id']);
    $stmt->execute();
    $stmt->close();
} else {
    // Dodaj nową ocenę
    $stmt = $conn->prepare("INSERT INTO ratings (seller_id, buyer_id, produkt_id, rating, comment) VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param("iiiis", $seller_id, $buyer_id, $produkt_id, $rating, $comment);
    $stmt->execute();
    $stmt->close();
}

$stmt = $conn->prepare("
    UPDATE logi 
    SET rating_avg = (SELECT AVG(rating) FROM ratings WHERE seller_id = ?),
        rating_count = (SELECT COUNT(*) FROM ratings WHERE seller_id = ?)
    WHERE id = ?
");
$stmt->bind_param("iii", $seller_id, $seller_id, $seller_id);
$stmt->execute();
$stmt->close();

// Pobierz nowe statystyki
$stmt = $conn->prepare("SELECT rating_avg, rating_count FROM logi WHERE id = ?");
$stmt->bind_param("i", $seller_id);
$stmt->execute();
$stats = $stmt->get_result()->fetch_assoc();
$stmt->close();

// Dodaj powiadomienie dla sprzedawcy
$content = $_SESSION['username'] . " wystawił Ci ocenę " . $rating . " ⭐";
$stmt = $conn->prepare("INSERT INTO notifications (user_id, type, content, related_id) VALUES (?, 'rating', ?, ?)");
$stmt->bind_param("isi", $seller_id, $content, $produkt_id);
$stmt->execute();
$stmt->close();

$conn->close();

echo json_encode([
    'success' => true,
    'rating_avg' => round($stats['rating_avg'], 1),
    'rating_count' => $stats['rating_count']
]);