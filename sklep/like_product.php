<?php
session_start();
require_once 'config.php';

header('Content-Type: application/json');

if (empty($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Musisz być zalogowany']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Nieprawidłowa metoda']);
    exit;
}

$conn = getDBConnection();
$user_id = $_SESSION['user_id'];
$product_id = intval($_POST['product_id'] ?? 0);

if ($product_id <= 0) {
    echo json_encode(['success' => false, 'error' => 'Nieprawidłowy ID produktu']);
    exit;
}

// Sprawdź czy produkt istnieje
$stmt = $conn->prepare("SELECT id FROM produkty WHERE id = ?");
$stmt->bind_param("i", $product_id);
$stmt->execute();
if ($stmt->get_result()->num_rows === 0) {
    $stmt->close();
    $conn->close();
    echo json_encode(['success' => false, 'error' => 'Produkt nie istnieje']);
    exit;
}
$stmt->close();

// Sprawdź czy już polubiony
$stmt = $conn->prepare("SELECT id FROM likes WHERE user_id = ? AND produkt_id = ?");
$stmt->bind_param("ii", $user_id, $product_id);
$stmt->execute();
$isLiked = $stmt->get_result()->num_rows > 0;
$stmt->close();

if ($isLiked) {
    // Usuń polubienie
    $stmt = $conn->prepare("DELETE FROM likes WHERE user_id = ? AND produkt_id = ?");
    $stmt->bind_param("ii", $user_id, $product_id);
    $stmt->execute();
    $stmt->close();
    
    $liked = false;
} else {
    // Dodaj polubienie
    $stmt = $conn->prepare("INSERT INTO likes (user_id, produkt_id) VALUES (?, ?)");
    $stmt->bind_param("ii", $user_id, $product_id);
    $stmt->execute();
    $stmt->close();
    
    $liked = true;
    
    // Dodaj powiadomienie dla właściciela produktu
    $stmt = $conn->prepare("
        SELECT id_sprzedawcy, nazwa 
        FROM produkty 
        WHERE id = ?
    ");
    $stmt->bind_param("i", $product_id);
    $stmt->execute();
    $product = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    
    if ($product && $product['id_sprzedawcy'] != $user_id) {
        $content = $_SESSION['username'] . " polubił Twój produkt: " . $product['nazwa'];
        $stmt = $conn->prepare("
            INSERT INTO notifications (user_id, type, content, related_id) 
            VALUES (?, 'like', ?, ?)
        ");
        $seller_id = $product['id_sprzedawcy'];
        $stmt->bind_param("isi", $seller_id, $content, $product_id);
        $stmt->execute();
        $stmt->close();
    }
}

// Pobierz aktualną liczbę polubień
$stmt = $conn->prepare("SELECT COUNT(*) as count FROM likes WHERE produkt_id = ?");
$stmt->bind_param("i", $product_id);
$stmt->execute();
$count = $stmt->get_result()->fetch_assoc()['count'];
$stmt->close();

$conn->close();

echo json_encode([
    'success' => true,
    'liked' => $liked,
    'count' => $count
]);