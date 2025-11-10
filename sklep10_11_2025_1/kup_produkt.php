<?php
session_start();
require_once 'config.php';

requireLogin();

$conn = getDBConnection();
$product_id = intval($_GET['id'] ?? 0);
$user_id = $_SESSION['user_id'];

if ($product_id <= 0) {
    header("Location: index.php");
    exit;
}

// Pobierz produkt
$stmt = $conn->prepare("
    SELECT p.*, l.username AS sprzedawca, l.id AS seller_id 
    FROM produkty p 
    LEFT JOIN logi l ON p.id_sprzedawcy = l.id 
    WHERE p.id = ?
");
$stmt->bind_param("i", $product_id);
$stmt->execute();
$product = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$product) {
    header("Location: index.php?error=" . urlencode("Produkt nie istnieje"));
    exit;
}

// Sprawdź czy produkt już nie jest sprzedany
if ($product['is_sold'] == 1) {
    $conn->close();
    header("Location: produkt.php?id=$product_id&error=" . urlencode("Ten produkt został już sprzedany"));
    exit;
}

// Sprawdź czy to nie właściciel próbuje kupić swój produkt
if ($product['seller_id'] == $user_id) {
    $conn->close();
    header("Location: produkt.php?id=$product_id&error=" . urlencode("Nie możesz kupić własnego produktu"));
    exit;
}

$unread_count = getUnreadCount($user_id, $conn);
$search = '';

// Dekoduj zdjęcia
$zdjecia = [];
if (!empty($product['zdjecie'])) {
    $decoded = json_decode($product['zdjecie'], true);
    if (is_array($decoded)) {
        $zdjecia = $decoded;
    } else {
        $zdjecia = [$product['zdjecie']];
    }
}

// Obsługa potwierdzenia zakupu
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm_purchase'])) {
    // Rozpocznij transakcję
    $conn->begin_transaction();
    
    try {
        // Oznacz produkt jako sprzedany
        $stmt = $conn->prepare("
            UPDATE produkty 
            SET is_sold = 1, buyer_id = ?, sold_at = NOW() 
            WHERE id = ? AND is_sold = 0
        ");
        $stmt->bind_param("ii", $user_id, $product_id);
        $stmt->execute();
        
        if ($stmt->affected_rows === 0) {
            throw new Exception("Produkt został już sprzedany lub nie istnieje");
        }
        $stmt->close();
        
        // Dodaj powiadomienie dla sprzedawcy
        $content = $_SESSION['username'] . " kupił Twój produkt: " . $product['nazwa'];
        $stmt = $conn->prepare("
            INSERT INTO notifications (user_id, type, content, related_id) 
            VALUES (?, 'sale', ?, ?)
        ");
        $seller_id = $product['seller_id'];
        $stmt->bind_param("isi", $seller_id, $content, $product_id);
        $stmt->execute();
        $stmt->close();
        
        // Dodaj powiadomienie dla kupującego
        $content_buyer = "Kupiłeś produkt: " . $product['nazwa'] . " od " . $product['sprzedawca'];
        $stmt = $conn->prepare("
            INSERT INTO notifications (user_id, type, content, related_id) 
            VALUES (?, 'purchase', ?, ?)
        ");
        $stmt->bind_param("isi", $user_id, $content_buyer, $product_id);
        $stmt->execute();
        $stmt->close();
        
        // Dodaj wiadomość systemową w czacie
        $msg = json_encode([
            'type' => 'purchase_completed',
            'buyer_username' => $_SESSION['username'],
            'product_name' => $product['nazwa'],
            'price' => $product['cena']
        ], JSON_UNESCAPED_UNICODE);
        
        $stmt = $conn->prepare("
            INSERT INTO chats (user_from, user_to, produkt_id, message, is_system) 
            VALUES (?, ?, ?, ?, 1)
        ");
        $stmt->bind_param("iiis", $user_id, $seller_id, $product_id, $msg);
        $stmt->execute();
        $stmt->close();
        
        // Zatwierdź transakcję
        $conn->commit();
        $conn->close();
        
        // Przekieruj z sukcesem
        header("Location: produkt.php?id=$product_id&success=" . urlencode("Gratulacje! Produkt został kupiony. Skontaktuj się ze sprzedawcą w celu ustalenia szczegółów odbioru."));
        exit;
        
    } catch (Exception $e) {
        // Wycofaj transakcję w przypadku błędu
        $conn->rollback();
        $conn->close();
        header("Location: produkt.php?id=$product_id&error=" . urlencode($e->getMessage()));
        exit;
    }
}

$conn->close();
?>
<!doctype html>
<html lang="pl">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>🛒 Kup produkt - <?= htmlspecialchars($product['nazwa']) ?></title>
<link rel="icon" href="./images/logo_strona.png">
<style>
* { margin: 0; padding: 0; box-sizing: border-box; }

:root {
    --primary: #ff8c42;
    --secondary: #ff6b35;
    --success: #10b981;
    --danger: #ef4444;
    --dark: #2c3e50;
    --light: #fff5f0;
}

body {
    font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
    background: linear-gradient(135deg, #ff8c42 0%, #ff6b35 100%);
    min-height: 100vh;
}

.header {
    background: white;
    box-shadow: 0 4px 20px rgba(0,0,0,0.1);
    position: sticky;
    top: 0;
    z-index: 1000;
}

.header-content {
    max-width: 1400px;
    margin: 0 auto;
    padding: 15px 20px;
    display: grid;
    grid-template-columns: auto 1fr auto;
    gap: 25px;
    align-items: center;
}

.logo-section {
    display: flex;
    align-items: center;
    gap: 15px;
    cursor: pointer;
}

.logo-icon { font-size: 48px; }

.logo-text { display: flex; flex-direction: column; }

.logo-main {
    font-size: 28px;
    font-weight: 900;
    background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
}

.logo-subtitle {
    font-size: 11px;
    color: #999;
    font-weight: 600;
}

.school-link {
    font-size: 18px;
    color: var(--primary);
    text-decoration: none;
}

.search-section { display: flex; gap: 10px; }
.search-bar { flex: 1; position: relative; }
.search-bar input {
    width: 100%;
    padding: 14px 50px 14px 20px;
    border: 2px solid #e0e0e0;
    border-radius: 30px;
    font-size: 15px;
}

.search-btn {
    position: absolute;
    right: 5px;
    top: 50%;
    transform: translateY(-50%);
    background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
    border: none;
    color: white;
    padding: 10px 20px;
    border-radius: 25px;
    cursor: pointer;
    font-weight: bold;
}

.user-menu {
    display: flex;
    align-items: center;
    gap: 12px;
}

.menu-item {
    text-decoration: none;
    color: var(--dark);
    padding: 10px 18px;
    border-radius: 25px;
    background: var(--light);
    font-weight: 600;
    font-size: 14px;
    position: relative;
    transition: 0.3s;
}

.menu-item:hover {
    background: var(--primary);
    color: white;
}

.badge {
    position: absolute;
    top: -5px;
    right: -5px;
    background: var(--danger);
    color: white;
    border-radius: 50%;
    width: 22px;
    height: 22px;
    font-size: 11px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: bold;
}

.btn-add {
    background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
    color: white;
    padding: 12px 24px;
    border-radius: 25px;
    text-decoration: none;
    font-weight: bold;
}

.container {
    max-width: 900px;
    margin: 30px auto;
    padding: 0 20px;
}

.purchase-card {
    background: white;
    border-radius: 25px;
    padding: 40px;
    box-shadow: 0 20px 60px rgba(0,0,0,0.3);
    animation: slideUp 0.5s;
}

@keyframes slideUp {
    from {
        opacity: 0;
        transform: translateY(30px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.purchase-header {
    text-align: center;
    margin-bottom: 40px;
}

.purchase-header h1 {
    font-size: 32px;
    background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    margin-bottom: 10px;
}

.purchase-header p {
    color: #666;
    font-size: 16px;
}

.product-summary {
    display: grid;
    grid-template-columns: 200px 1fr;
    gap: 30px;
    background: var(--light);
    padding: 25px;
    border-radius: 20px;
    margin-bottom: 30px;
}

.product-image {
    width: 200px;
    height: 200px;
    border-radius: 15px;
    object-fit: cover;
}

.product-details h2 {
    font-size: 24px;
    color: #333;
    margin-bottom: 15px;
}

.detail-row {
    display: flex;
    justify-content: space-between;
    padding: 12px 0;
    border-bottom: 2px solid white;
}

.detail-label {
    font-weight: 600;
    color: #666;
}

.detail-value {
    font-weight: 700;
    color: #333;
}

.price-highlight {
    font-size: 32px;
    background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
}

.info-box {
    background: #e3f2fd;
    border-left: 5px solid #2196f3;
    padding: 20px;
    border-radius: 15px;
    margin-bottom: 30px;
}

.info-box h3 {
    color: #1976d2;
    margin-bottom: 10px;
    font-size: 18px;
}

.info-box ul {
    margin-left: 20px;
    color: #0d47a1;
    line-height: 1.8;
}

.warning-box {
    background: #fff3cd;
    border-left: 5px solid #ffc107;
    padding: 20px;
    border-radius: 15px;
    margin-bottom: 30px;
}

.warning-box h3 {
    color: #f57c00;
    margin-bottom: 10px;
    font-size: 18px;
}

.warning-box p {
    color: #e65100;
    line-height: 1.6;
}

.action-buttons {
    display: flex;
    gap: 15px;
}

.btn {
    flex: 1;
    padding: 18px;
    border: none;
    border-radius: 15px;
    font-size: 18px;
    font-weight: bold;
    cursor: pointer;
    transition: 0.3s;
    text-decoration: none;
    text-align: center;
}

.btn-confirm {
    background: linear-gradient(135deg, var(--success) 0%, #059669 100%);
    color: white;
    box-shadow: 0 5px 20px rgba(16, 185, 129, 0.3);
}

.btn-confirm:hover {
    transform: translateY(-3px);
    box-shadow: 0 8px 30px rgba(16, 185, 129, 0.5);
}

.btn-cancel {
    background: white;
    color: var(--danger);
    border: 2px solid var(--danger);
}

.btn-cancel:hover {
    background: var(--danger);
    color: white;
}

@media (max-width: 768px) {
    .header-content {
        grid-template-columns: 1fr;
    }
    
    .product-summary {
        grid-template-columns: 1fr;
    }
    
    .product-image {
        width: 100%;
        height: 250px;
    }
    
    .action-buttons {
        flex-direction: column;
    }
}
</style>
</head>
<body>

<div class="header">
    <div class="header-content">
        <div class="logo-section" onclick="window.location='index.php'">
            <div class="logo-icon"><img src="./images/logo.png" height="50px" width="50px"></div>
            <div class="logo-text">
                <div class="logo-main">GórkaSklep.pl</div>
                <div class="logo-subtitle">Szkolny Sklep Internetowy</div>
                <a href="https://lo2rabka.nowotarski.edu.pl" target="_blank" class="school-link" onclick="event.stopPropagation()">
                    Przejdź na naszą stronę szkoły! 🏫
                </a>
            </div>
        </div>
        
        <form class="search-section" method="get" action="index.php">
            <div class="search-bar">
                <input type="text" name="search" placeholder="Czego szukasz? 🔍" value="<?= htmlspecialchars($search) ?>">
                <button type="submit" class="search-btn">Szukaj</button>
            </div>
        </form>

        <div class="user-menu">
            <a href="index.php" class="menu-item">🏠 Strona główna</a>
            <a href="wiadomosci.php" class="menu-item">
                💬 Wiadomości
                <?php if ($unread_count > 0): ?>
                    <span class="badge"><?= $unread_count ?></span>
                <?php endif; ?>
            </a>
            <a href="profil.php" class="menu-item">👤 Profil</a>
            <a href="dodaj_produkt.php" class="btn-add">+ Dodaj</a>
            <a href="logout.php" class="menu-item">Wyloguj</a>
        </div>
    </div>
</div>

<div class="container">
    <div class="purchase-card">
        <div class="purchase-header">
            <h1>🛒 Potwierdzenie zakupu</h1>
            <p>Sprawdź szczegóły przed finalizacją</p>
        </div>

        <div class="product-summary">
            <?php if (!empty($zdjecia)): ?>
                <img src="<?= htmlspecialchars($zdjecia[0]) ?>" 
                     alt="<?= htmlspecialchars($product['nazwa']) ?>" 
                     class="product-image"
                     onerror="this.src='https://via.placeholder.com/200?text=Brak+zdjęcia'">
            <?php else: ?>
                <img src="https://via.placeholder.com/200?text=Brak+zdjęcia" class="product-image" alt="">
            <?php endif; ?>
            
            <div class="product-details">
                <h2><?= htmlspecialchars($product['nazwa']) ?></h2>
                
                <div class="detail-row">
                    <span class="detail-label">Sprzedawca:</span>
                    <span class="detail-value">👤 <?= htmlspecialchars($product['sprzedawca']) ?></span>
                </div>
                
                <div class="detail-row">
                    <span class="detail-label">Kategoria:</span>
                    <span class="detail-value">
                        <?php
                        $category_names = [
                            'elektronika' => '📱 Elektronika',
                            'odziez' => '👕 Odzież',
                            'dom' => '🏠 Dom i Ogród',
                            'sport' => '⚽ Sport',
                            'inne' => '📦 Inne'
                        ];
                        echo $category_names[$product['kategoria']] ?? '📦 Inne';
                        ?>
                    </span>
                </div>
                
                <div class="detail-row" style="border-bottom: none; margin-top: 15px;">
                    <span class="detail-label" style="font-size: 20px;">Cena:</span>
                    <span class="price-highlight"><?= number_format($product['cena'], 2) ?> zł</span>
                </div>
            </div>
        </div>

        <div class="info-box">
            <h3>📋 Co dalej?</h3>
            <ul>
                <li>Po potwierdzeniu zakupu, produkt zostanie oznaczony jako sprzedany</li>
                <li>Sprzedawca otrzyma powiadomienie o zakupie</li>
                <li>Będziesz mógł skontaktować się ze sprzedawcą przez czat</li>
                <li>Ustalcie wspólnie szczegóły odbioru i płatności</li>
            </ul>
        </div>

        <div class="warning-box">
            <h3>⚠️ Ważne informacje</h3>
            <p>
                <strong>GórkaSklep.pl nie pośredniczy w transakcjach finansowych.</strong> 
                Jesteśmy platformą ogłoszeniową. Wszelkie kwestie dotyczące płatności i odbioru 
                ustalaj bezpośrednio ze sprzedawcą. Zachowaj ostrożność i spotykaj się 
                w bezpiecznych, publicznych miejscach.
            </p>
        </div>

        <form method="post" id="purchaseForm">
            <div class="action-buttons">
                <button type="submit" name="confirm_purchase" class="btn btn-confirm" onclick="return confirm('Czy na pewno chcesz kupić ten produkt?\n\n<?= htmlspecialchars($product['nazwa']) ?>\nCena: <?= number_format($product['cena'], 2) ?> zł\n\nPo potwierdzeniu skontaktuj się ze sprzedawcą w celu ustalenia szczegółów.')">
                    ✅ POTWIERDŹ ZAKUP
                </button>
                <a href="produkt.php?id=<?= $product_id ?>" class="btn btn-cancel">
                    ❌ Anuluj
                </a>
            </div>
        </form>
    </div>
</div>

</body>
</html>