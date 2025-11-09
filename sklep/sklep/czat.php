<?php
session_start();
if (empty($_SESSION['user_id'])) {
    header("Location: logowanie.php");
    exit;
}

$host = "192.168.1.202";
$user = "sklepuser";
$pass = "twojehaslo";
$dbname = "sklep";

$conn = new mysqli($host, $user, $pass, $dbname);
if ($conn->connect_error) {
    die("Błąd połączenia: " . $conn->connect_error);
}
$conn->set_charset("utf8mb4");

$from_id = $_SESSION['user_id'];
$produkt_id = intval($_GET['produkt_id'] ?? 0);
$other_user_id = intval($_GET['user_id'] ?? 0);

if (!$produkt_id && $other_user_id) {
    $stmt = $conn->prepare("
        SELECT DISTINCT produkt_id 
        FROM chats 
        WHERE (user_from = ? AND user_to = ?) OR (user_from = ? AND user_to = ?)
        ORDER BY created_at DESC 
        LIMIT 1
    ");
    if ($stmt) {
        $stmt->bind_param("iiii", $from_id, $other_user_id, $other_user_id, $from_id);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($row = $result->fetch_assoc()) {
            $produkt_id = $row['produkt_id'];
        }
        $stmt->close();
    }
}

if (!$produkt_id) {
    die("Błąd: Brak ID produktu");
}

$stmt = $conn->prepare("
    SELECT p.*, l.username AS sprzedawca, l.id AS sprzedawca_id
    FROM produkty p
    LEFT JOIN logi l ON p.id_sprzedawcy = l.id
    WHERE p.id = ?
");
if (!$stmt) {
    die("Błąd przygotowania zapytania: " . $conn->error);
}
$stmt->bind_param("i", $produkt_id);
$stmt->execute();
$produkt = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$produkt) {
    die("Nie znaleziono produktu");
}

if ($other_user_id == 0) {
    $to_id = $produkt['sprzedawca_id'];
} else {
    $to_id = $other_user_id;
}

$stmt = $conn->prepare("SELECT username, bio, profile_picture FROM logi WHERE id = ?");
if (!$stmt) {
    die("Błąd przygotowania zapytania: " . $conn->error);
}
$stmt->bind_param("i", $to_id);
$stmt->execute();
$other_user = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$other_user) {
    die("Nie znaleziono użytkownika");
}

$stmt = $conn->prepare("UPDATE chats SET read_status=1 WHERE produkt_id=? AND user_to=? AND user_from=?");
if ($stmt) {
    $stmt->bind_param("iii", $produkt_id, $from_id, $to_id);
    $stmt->execute();
    $stmt->close();
}

// Pobierz lub utwórz negocjację
$stmt = $conn->prepare("SELECT * FROM price_negotiations WHERE produkt_id = ? AND ((buyer_id = ? AND seller_id = ?) OR (buyer_id = ? AND seller_id = ?)) ORDER BY updated_at DESC LIMIT 1");
if ($stmt) {
    $stmt->bind_param("iiiii", $produkt_id, $from_id, $to_id, $to_id, $from_id);
    $stmt->execute();
    $negotiation = $stmt->get_result()->fetch_assoc();
    $stmt->close();
} else {
    $negotiation = null;
}

$current_price = ($negotiation && $negotiation['status'] === 'accepted') ? $negotiation['current_price'] : $produkt['cena'];
$is_seller = ($from_id == $produkt['sprzedawca_id']);

// Obsługa propozycji ceny
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['propose_price'])) {
    $proposed_price = floatval($_POST['proposed_price']);
    
    if ($proposed_price > 0 && $proposed_price <= 10000) {
        if (!$negotiation) {
            $buyer_id = $is_seller ? $to_id : $from_id;
            $seller_id = $is_seller ? $from_id : $to_id;
            $stmt = $conn->prepare("INSERT INTO price_negotiations (produkt_id, buyer_id, seller_id, original_price, current_price, status, last_proposer) VALUES (?, ?, ?, ?, ?, 'pending', ?)");
            if ($stmt) {
                $stmt->bind_param("iidddi", $produkt_id, $buyer_id, $seller_id, $produkt['cena'], $proposed_price, $from_id);
                $stmt->execute();
                $nego_id = $stmt->insert_id;
                $stmt->close();
            }
        } else {
            $stmt = $conn->prepare("UPDATE price_negotiations SET current_price = ?, status = 'pending', last_proposer = ?, updated_at = NOW() WHERE id = ?");
            if ($stmt) {
                $stmt->bind_param("dii", $proposed_price, $from_id, $negotiation['id']);
                $stmt->execute();
                $nego_id = $negotiation['id'];
                $stmt->close();
            }
        }
        
        // Dodaj wiadomość z propozycją
        $msg = json_encode([
            'type' => 'price_proposal',
            'price' => $proposed_price,
            'nego_id' => $nego_id,
            'from_role' => $is_seller ? 'seller' : 'buyer',
            'from_username' => $_SESSION['username'] ?? 'Użytkownik'
        ], JSON_UNESCAPED_UNICODE);
        $stmt = $conn->prepare("INSERT INTO chats (user_from, user_to, produkt_id, message, read_status, is_system) VALUES (?, ?, ?, ?, 0, 1)");
        if ($stmt) {
            $stmt->bind_param("iiis", $from_id, $to_id, $produkt_id, $msg);
            $stmt->execute();
            $stmt->close();
        }
    }
    
    if (!empty($_SERVER['HTTP_X_REQUESTED_WITH'])) {
        header('Content-Type: application/json');
        echo json_encode(['status' => 'ok']);
        exit;
    }
}

// Obsługa odpowiedzi na propozycję
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['price_action']) && $negotiation) {
    $action = $_POST['price_action'];
    $nego_id = intval($_POST['nego_id']);
    
    if ($action === 'accept') {
        $stmt = $conn->prepare("UPDATE price_negotiations SET status = 'accepted' WHERE id = ?");
        if ($stmt) {
            $stmt->bind_param("i", $nego_id);
            $stmt->execute();
            $stmt->close();
        }
        
        $msg = json_encode([
            'type' => 'price_accepted',
            'price' => $negotiation['current_price'],
            'nego_id' => $nego_id,
            'accepter_username' => $_SESSION['username'] ?? 'Użytkownik'
        ], JSON_UNESCAPED_UNICODE);
        $stmt = $conn->prepare("INSERT INTO chats (user_from, user_to, produkt_id, message, read_status, is_system) VALUES (?, ?, ?, ?, 0, 1)");
        if ($stmt) {
            $stmt->bind_param("iiis", $from_id, $to_id, $produkt_id, $msg);
            $stmt->execute();
            $stmt->close();
        }
    } elseif ($action === 'reject') {
        $stmt = $conn->prepare("UPDATE price_negotiations SET status = 'rejected' WHERE id = ?");
        if ($stmt) {
            $stmt->bind_param("i", $nego_id);
            $stmt->execute();
            $stmt->close();
        }
        
        $msg = json_encode([
            'type' => 'price_rejected',
            'price' => $negotiation['current_price'],
            'nego_id' => $nego_id,
            'rejecter_username' => $_SESSION['username'] ?? 'Użytkownik'
        ], JSON_UNESCAPED_UNICODE);
        $stmt = $conn->prepare("INSERT INTO chats (user_from, user_to, produkt_id, message, read_status, is_system) VALUES (?, ?, ?, ?, 0, 1)");
        if ($stmt) {
            $stmt->bind_param("iiis", $from_id, $to_id, $produkt_id, $msg);
            $stmt->execute();
            $stmt->close();
        }
    }
    
    if (!empty($_SERVER['HTTP_X_REQUESTED_WITH'])) {
        header('Content-Type: application/json');
        echo json_encode(['status' => 'ok']);
        exit;
    }
}

// Wysyłanie zwykłych wiadomości
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['message'])) {
    $msg = trim($_POST['message']);
    if ($msg !== '' && strlen($msg) <= 1000) {
        $stmt = $conn->prepare("INSERT INTO chats (user_from, user_to, produkt_id, message, read_status) VALUES (?, ?, ?, ?, 0)");
        if ($stmt) {
            $stmt->bind_param("iiis", $from_id, $to_id, $produkt_id, $msg);
            $stmt->execute();
            $stmt->close();
        }
    }
    if (!empty($_SERVER['HTTP_X_REQUESTED_WITH'])) {
        header('Content-Type: application/json');
        echo json_encode(['status' => 'ok']);
        exit;
    }
}

// Pobieranie wiadomości AJAX
if (isset($_GET['fetch']) && $_GET['fetch'] == 1) {
    $stmt = $conn->prepare("
        SELECT c.*, l.username AS from_name
        FROM chats c
        LEFT JOIN logi l ON c.user_from = l.id
        WHERE c.produkt_id = ? AND ((c.user_from = ? AND c.user_to = ?) OR (c.user_from = ? AND c.user_to = ?))
        ORDER BY c.created_at ASC
    ");
    if ($stmt) {
        $stmt->bind_param("iiiii", $produkt_id, $from_id, $to_id, $to_id, $from_id);
        $stmt->execute();
        $messages = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
    } else {
        $messages = [];
    }
    
    $stmt = $conn->prepare("UPDATE chats SET read_status=1 WHERE produkt_id=? AND user_to=? AND user_from=?");
    if ($stmt) {
        $stmt->bind_param("iii", $produkt_id, $from_id, $to_id);
        $stmt->execute();
        $stmt->close();
    }
    
    // Pobierz aktualną negocjację
    $stmt = $conn->prepare("SELECT * FROM price_negotiations WHERE produkt_id = ? AND ((buyer_id = ? AND seller_id = ?) OR (buyer_id = ? AND seller_id = ?)) ORDER BY updated_at DESC LIMIT 1");
    if ($stmt) {
        $stmt->bind_param("iiiii", $produkt_id, $from_id, $to_id, $to_id, $from_id);
        $stmt->execute();
        $current_negotiation = $stmt->get_result()->fetch_assoc();
        $stmt->close();
    } else {
        $current_negotiation = null;
    }
    
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'messages' => $messages,
        'negotiation' => $current_negotiation
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$stmt = $conn->prepare("SELECT last_activity FROM logi WHERE id = ?");
if ($stmt) {
    $stmt->bind_param("i", $to_id);
    $stmt->execute();
    $activity = $stmt->get_result()->fetch_assoc();
    $stmt->close();
} else {
    $activity = null;
}

$is_online = false;
if ($activity && isset($activity['last_activity'])) {
    $last_time = strtotime($activity['last_activity']);
    $is_online = (time() - $last_time) < 300;
}

$stmt = $conn->prepare("UPDATE logi SET last_activity = NOW() WHERE id = ?");
if ($stmt) {
    $stmt->bind_param("i", $from_id);
    $stmt->execute();
    $stmt->close();
}

$unread_count = 0;
$stmt = $conn->prepare("SELECT COUNT(*) as cnt FROM chats WHERE user_to=? AND read_status=0");
if ($stmt) {
    $stmt->bind_param("i", $from_id);
    $stmt->execute();
    $unread_count = $stmt->get_result()->fetch_assoc()['cnt'];
    $stmt->close();
}

$search = '';

function getFirstImage($zdjecie) {
    if (empty($zdjecie)) {
        return null;
    }
    
    $decoded = json_decode($zdjecie, true);
    if (is_array($decoded) && !empty($decoded)) {
        return $decoded[0];
    }
    
    return $zdjecie;
}

$firstImage = getFirstImage($produkt['zdjecie']);
?>
<!doctype html>
<html lang="pl">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Czat - <?= htmlspecialchars($produkt['nazwa']) ?></title>
<link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><text y='.9em' font-size='90'>💬</text></svg>">
<style>
* { margin: 0; padding: 0; box-sizing: border-box; }

:root {
    --primary: #ff8c42;
    --secondary: #ff6b35;
    --accent: #ffa500;
    --dark: #2c3e50;
    --light: #fff5f0;
    --white: #ffffff;
}

body {
    font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
    background: linear-gradient(135deg, #ff8c42 0%, #ff6b35 100%);
    min-height: 100vh;
    overflow: hidden;
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
    transition: 0.3s;
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
    transition: 0.3s;
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
}

.badge {
    position: absolute;
    top: -5px;
    right: -5px;
    background: #ef4444;
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
    margin: 20px auto;
    background: white;
    border-radius: 20px;
    overflow: hidden;
    box-shadow: 0 10px 40px rgba(0,0,0,0.3);
    display: flex;
    flex-direction: column;
    height: calc(100vh - 160px);
}

.chat-header {
    background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
    color: white;
    padding: 20px;
    display: flex;
    align-items: center;
    gap: 15px;
    flex-shrink: 0;
}

.back-btn {
    background: rgba(255,255,255,0.2);
    border: none;
    color: white;
    padding: 10px 15px;
    border-radius: 10px;
    cursor: pointer;
    font-size: 18px;
}

.header-info { flex: 1; }

.chat-title {
    font-size: 18px;
    font-weight: bold;
    margin-bottom: 5px;
    display: flex;
    align-items: center;
    gap: 10px;
}

.profile-pic {
    width: 45px;
    height: 45px;
    border-radius: 50%;
    object-fit: cover;
    border: 3px solid rgba(255,255,255,0.3);
}

.online-status {
    width: 10px;
    height: 10px;
    border-radius: 50%;
    background: #4ade80;
}

.offline-status {
    width: 10px;
    height: 10px;
    border-radius: 50%;
    background: #94a3b8;
}

.product-mini {
    display: flex;
    align-items: center;
    gap: 10px;
    background: rgba(255,255,255,0.15);
    padding: 8px 12px;
    border-radius: 10px;
    cursor: pointer;
}

.product-mini img {
    width: 40px;
    height: 40px;
    border-radius: 8px;
    object-fit: cover;
}

.price-display {
    font-size: 15px;
    font-weight: bold;
    padding: 8px 15px;
    background: rgba(255,255,255,0.2);
    border-radius: 15px;
}

.price-changed {
    animation: priceChange 0.5s;
}

@keyframes priceChange {
    0%, 100% { transform: scale(1); }
    50% { transform: scale(1.15); background: rgba(255,255,255,0.4); }
}

.messages-container {
    flex: 1;
    overflow-y: auto;
    padding: 20px;
    background: #f8f9fa;
    display: flex;
    flex-direction: column;
    gap: 12px;
}

.messages-container::-webkit-scrollbar { width: 8px; }
.messages-container::-webkit-scrollbar-thumb { background: var(--primary); border-radius: 4px; }

.message {
    max-width: 70%;
    padding: 12px 16px;
    border-radius: 18px;
    word-wrap: break-word;
}

.message.from-me {
    align-self: flex-end;
    background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
    color: white;
}

.message.from-other {
    align-self: flex-start;
    background: white;
    color: #333;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
}

/* Wiadomości negocjacyjne */
.negotiation-message {
    align-self: center;
    max-width: 85%;
    background: linear-gradient(135deg, #fef3c7 0%, #fde68a 100%);
    border: 2px solid #f59e0b;
    padding: 20px;
    border-radius: 15px;
    box-shadow: 0 4px 15px rgba(245, 158, 11, 0.2);
}

.negotiation-header {
    font-weight: bold;
    font-size: 16px;
    color: #92400e;
    margin-bottom: 12px;
    display: flex;
    align-items: center;
    gap: 8px;
}

.negotiation-price {
    font-size: 24px;
    font-weight: bold;
    color: #92400e;
    text-align: center;
    margin: 12px 0;
}

.negotiation-actions {
    display: flex;
    gap: 8px;
    margin-top: 12px;
    flex-wrap: wrap;
}

.nego-btn {
    flex: 1;
    padding: 10px 16px;
    border: none;
    border-radius: 10px;
    font-weight: bold;
    cursor: pointer;
    font-size: 14px;
    min-width: 100px;
    transition: 0.3s;
}

.nego-btn-accept {
    background: #10b981;
    color: white;
}

.nego-btn-accept:hover { background: #059669; }

.nego-btn-reject {
    background: #ef4444;
    color: white;
}

.nego-btn-reject:hover { background: #dc2626; }

.nego-btn-counter {
    background: var(--primary);
    color: white;
}

.nego-btn-counter:hover { background: var(--secondary); }

.counter-input-group {
    display: flex;
    gap: 8px;
    margin-top: 12px;
    width: 100%;
}

.counter-price-input {
    flex: 1;
    padding: 10px;
    border: 2px solid #f59e0b;
    border-radius: 10px;
    font-size: 15px;
    font-weight: bold;
}

.negotiation-status {
    text-align: center;
    padding: 12px;
    border-radius: 10px;
    font-weight: bold;
    margin-top: 8px;
}

.status-accepted {
    background: #d1fae5;
    color: #065f46;
}

.status-rejected {
    background: #fee2e2;
    color: #991b1b;
}

.message-time {
    font-size: 10px;
    margin-top: 5px;
    opacity: 0.7;
}

.chat-input-container {
    padding: 20px;
    background: white;
    border-top: 1px solid #e0e0e0;
    flex-shrink: 0;
}

.chat-form {
    display: flex;
    gap: 12px;
}

.input-wrapper {
    flex: 1;
    position: relative;
}

.chat-input {
    width: 100%;
    padding: 12px 60px 12px 16px;
    border: 2px solid #e0e0e0;
    border-radius: 25px;
    font-size: 15px;
    font-family: inherit;
    resize: none;
}

.char-counter {
    position: absolute;
    right: 15px;
    bottom: 12px;
    font-size: 11px;
    color: #999;
}

.send-btn {
    background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
    color: white;
    border: none;
    padding: 12px 24px;
    border-radius: 25px;
    font-weight: bold;
    cursor: pointer;
}

@media (max-width: 768px) {
    .header-content {
        grid-template-columns: 1fr;
    }
    
    .negotiation-message {
        max-width: 95%;
    }
    
    .negotiation-actions {
        flex-direction: column;
    }
    
    .nego-btn {
        width: 100%;
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
    <div class="chat-header">
        <button class="back-btn" onclick="window.location='wiadomosci.php'">←</button>
        
        <div class="header-info">
            <div class="chat-title">
                <?php if (!empty($other_user['profile_picture'])): ?>
                    <img src="<?= htmlspecialchars($other_user['profile_picture']) ?>" alt="Profil" class="profile-pic">
                <?php else: ?>
                    <img src="data:image/svg+xml,<?= urlencode('<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><circle cx="50" cy="50" r="50" fill="#ddd"/><circle cx="50" cy="40" r="18" fill="#999"/><path d="M50 60c-15 0-27 8-27 18v12h54V78c0-10-12-18-27-18z" fill="#999"/></svg>') ?>" alt="Profil" class="profile-pic">
                <?php endif; ?>
                💬 <?= htmlspecialchars($other_user['username']) ?>
                <div class="<?= $is_online ? 'online' : 'offline' ?>-status"></div>
            </div>
        </div>
        
        <div class="product-mini" onclick="window.location='produkt.php?id=<?= $produkt['id'] ?>'">
            <?php if ($firstImage): ?>
                <img src="<?= htmlspecialchars($firstImage) ?>" alt="">
            <?php endif; ?>
            <div>
                <div class="price-display" id="currentPriceDisplay">
                    <span id="displayPrice"><?= number_format($current_price, 2) ?></span> zł
                </div>
                <?php if ($current_price != $produkt['cena']): ?>
                    <div style="font-size:11px;opacity:0.8;"><del><?= number_format($produkt['cena'], 2) ?> zł</del></div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="messages-container" id="messages"></div>

    <div class="chat-input-container">
        <form class="chat-form" id="chatForm">
            <div class="input-wrapper">
                <textarea class="chat-input" id="messageInput" name="message" placeholder="Wpisz wiadomość..." required maxlength="1000" rows="1"></textarea>
                <span class="char-counter" id="charCounter">0/1000</span>
            </div>
            <button type="submit" class="send-btn" id="sendBtn">Wyślij 📤</button>
        </form>
    </div>
</div>

<script>
const fromId = <?= $from_id ?>;
const toId = <?= $to_id ?>;
const produktId = <?= $produkt_id ?>;
const isSeller = <?= $is_seller ? 'true' : 'false' ?>;
const originalPrice = <?= $produkt['cena'] ?>;

const form = document.getElementById('chatForm');
const input = document.getElementById('messageInput');
const messagesDiv = document.getElementById('messages');
const charCounter = document.getElementById('charCounter');

input.addEventListener('input', function() {
    this.style.height = 'auto';
    this.style.height = (this.scrollHeight) + 'px';
    charCounter.textContent = this.value.length + '/1000';
});

async function fetchMessages() {
    try {
        const resp = await fetch(`czat.php?produkt_id=${produktId}&user_id=${toId}&fetch=1`, {cache: 'no-store'});
        const data = await resp.json();
        const msgs = data.messages;
        
        // Aktualizuj cenę tylko jeśli negocjacja została zaakceptowana
        if (data.negotiation && data.negotiation.status === 'accepted') {
            updatePrice(data.negotiation.current_price);
        }
        
        messagesDiv.innerHTML = '';
        
        for (const m of msgs) {
            const div = document.createElement('div');
            const isMyMessage = parseInt(m.user_from) === fromId;
            
            if (parseInt(m.is_system) === 1) {
                // Wiadomość negocjacyjna
                const msgData = JSON.parse(m.message);
                div.className = 'negotiation-message';
                div.innerHTML = renderNegotiationMessage(msgData, m.id, isMyMessage, data.negotiation);
            } else {
                // Zwykła wiadomość
                div.className = 'message ' + (isMyMessage ? 'from-me' : 'from-other');
                
                const text = document.createElement('div');
                text.textContent = m.message;
                div.appendChild(text);
                
                const time = document.createElement('div');
                time.className = 'message-time';
                time.textContent = new Date(m.created_at).toLocaleTimeString('pl-PL', {hour: '2-digit', minute: '2-digit'});
                div.appendChild(time);
            }
            
            messagesDiv.appendChild(div);
        }
        
        messagesDiv.scrollTop = messagesDiv.scrollHeight;
    } catch(e) {
        console.error('Błąd:', e);
    }
}

function renderNegotiationMessage(msgData, messageId, isMyMessage, currentNegotiation) {
    const price = parseFloat(msgData.price).toFixed(2);
    
    if (msgData.type === 'price_proposal') {
        const fromRole = msgData.from_role;
        const canRespond = (isSeller && fromRole === 'buyer') || (!isSeller && fromRole === 'seller');
        const fromUsername = msgData.from_username || 'Użytkownik';
        
        // Sprawdź czy negocjacja została już rozstrzygnięta
        const isResolved = currentNegotiation && 
                          currentNegotiation.id === msgData.nego_id && 
                          (currentNegotiation.status === 'accepted' || currentNegotiation.status === 'rejected');
        
        return `
            <div class="negotiation-header">
                💰 ${isMyMessage ? 'Zaproponowałeś cenę' : `${fromUsername} zaproponował cenę`}
            </div>
            <div class="negotiation-price">${price} zł</div>
            ${canRespond && !isResolved ? `
                <div class="negotiation-actions">
                    <button class="nego-btn nego-btn-accept" onclick="respondToPrice('accept', ${msgData.nego_id})">
                        ✅ Akceptuj
                    </button>
                    <button class="nego-btn nego-btn-reject" onclick="respondToPrice('reject', ${msgData.nego_id})">
                        ❌ Odrzuć
                    </button>
                </div>
                <div class="counter-input-group">
                    <input type="number" class="counter-price-input" id="counterInput_${messageId}" 
                           placeholder="Zaproponuj swoją cenę" min="0.01" max="10000" step="0.01">
                    <button class="nego-btn nego-btn-counter" onclick="proposeCounterPrice(${messageId})">
                        🔄 Kontroferta
                    </button>
                </div>
            ` : isResolved ? 
                `<div style="text-align:center;color:#92400e;margin-top:8px;">
                    ${currentNegotiation.status === 'accepted' ? '✅ Ta propozycja została zaakceptowana' : '❌ Ta propozycja została odrzucona'}
                </div>` :
                '<div style="text-align:center;color:#92400e;margin-top:8px;">Oczekiwanie na odpowiedź...</div>'}
        `;
    } else if (msgData.type === 'price_accepted') {
        const accepterUsername = msgData.accepter_username || 'Użytkownik';
        return `
            <div class="negotiation-header">✅ Cena zaakceptowana</div>
            <div class="negotiation-price">${price} zł</div>
            <div class="negotiation-status status-accepted">
                ${isMyMessage ? 'Zaakceptowałeś' : `${accepterUsername} zaakceptował`} tę cenę!
            </div>
        `;
    } else if (msgData.type === 'price_rejected') {
        const rejecterUsername = msgData.rejecter_username || 'Użytkownik';
        return `
            <div class="negotiation-header">❌ Propozycja odrzucona</div>
            <div class="negotiation-price">${price} zł</div>
            <div class="negotiation-status status-rejected">
                ${isMyMessage ? 'Odrzuciłeś' : `${rejecterUsername} odrzucił`} tę propozycję
            </div>
        `;
    }
}
// W czat.php - Dodaj te funkcje JavaScript (zastąp istniejące funkcje renderNegotiationMessage i handleProfileClick)

function renderNegotiationMessage(msgData, messageId, isMyMessage, currentNegotiation) {
    const price = parseFloat(msgData.price).toFixed(2);
    
    if (msgData.type === 'price_proposal') {
        const fromRole = msgData.from_role;
        const canRespond = (isSeller && fromRole === 'buyer') || (!isSeller && fromRole === 'seller');
        const fromUsername = msgData.from_username || 'Użytkownik';
        
        const isResolved = currentNegotiation && 
                          currentNegotiation.id === msgData.nego_id && 
                          (currentNegotiation.status === 'accepted' || currentNegotiation.status === 'rejected');
        
        // Jeśli cena zaakceptowana - pokaż przycisk KUP dla kupującego
        const showBuyButton = currentNegotiation && 
                             currentNegotiation.status === 'accepted' && 
                             currentNegotiation.id === msgData.nego_id &&
                             !isSeller;
        
        return `
            <div class="negotiation-header">
                💰 ${isMyMessage ? 'Zaproponowałeś cenę' : `${fromUsername} zaproponował cenę`}
            </div>
            <div class="negotiation-price">${price} zł</div>
            ${canRespond && !isResolved ? `
                <div class="negotiation-actions">
                    <button class="nego-btn nego-btn-accept" onclick="respondToPrice('accept', ${msgData.nego_id})">
                        ✅ Akceptuj
                    </button>
                    <button class="nego-btn nego-btn-reject" onclick="respondToPrice('reject', ${msgData.nego_id})">
                        ❌ Odrzuć
                    </button>
                </div>
                <div class="counter-input-group">
                    <input type="number" class="counter-price-input" id="counterInput_${messageId}" 
                           placeholder="Zaproponuj swoją cenę" min="0.01" max="10000" step="0.01">
                    <button class="nego-btn nego-btn-counter" onclick="proposeCounterPrice(${messageId})">
                        🔄 Kontroferta
                    </button>
                </div>
            ` : isResolved && currentNegotiation.status === 'accepted' ? 
                `<div class="negotiation-status status-accepted">
                    ✅ Ta propozycja została zaakceptowana
                </div>
                ${showBuyButton ? `
                    <div style="margin-top: 15px;">
                        <a href="kup_produkt.php?id=${produktId}" class="nego-btn nego-btn-buy" style="text-decoration: none; display: block; text-align: center;">
                            🛒 KUP TERAZ ZA ${price} zł
                        </a>
                    </div>
                ` : ''}` :
                isResolved ? 
                    `<div class="negotiation-status status-rejected">
                        ❌ Ta propozycja została odrzucona
                    </div>` :
                '<div style="text-align:center;color:#92400e;margin-top:8px;">Oczekiwanie na odpowiedź...</div>'}
        `;
    } else if (msgData.type === 'price_accepted') {
        const accepterUsername = msgData.accepter_username || 'Użytkownik';
        const showBuyButton = !isSeller; // Pokaż przycisk KUP dla kupującego
        
        return `
            <div class="negotiation-header">✅ Cena zaakceptowana</div>
            <div class="negotiation-price">${price} zł</div>
            <div class="negotiation-status status-accepted">
                ${isMyMessage ? 'Zaakceptowałeś' : `${accepterUsername} zaakceptował`} tę cenę!
            </div>
            ${showBuyButton ? `
                <div style="margin-top: 15px;">
                    <a href="kup_produkt.php?id=${produktId}" class="nego-btn nego-btn-buy" style="text-decoration: none; display: block; text-align: center;">
                        🛒 KUP TERAZ ZA ${price} zł
                    </a>
                </div>
            ` : ''}
        `;
    } else if (msgData.type === 'price_rejected') {
        const rejecterUsername = msgData.rejecter_username || 'Użytkownik';
        return `
            <div class="negotiation-header">❌ Propozycja odrzucona</div>
            <div class="negotiation-price">${price} zł</div>
            <div class="negotiation-status status-rejected">
                ${isMyMessage ? 'Odrzuciłeś' : `${rejecterUsername} odrzucił`} tę propozycję
            </div>
        `;
    } else if (msgData.type === 'purchase_completed') {
        return `
            <div class="negotiation-header">🎉 Transakcja zakończona!</div>
            <div class="negotiation-status status-accepted" style="text-align: center; padding: 20px;">
                <strong>${msgData.buyer_username}</strong> kupił produkt<br>
                <strong>${msgData.product_name}</strong><br>
                za <span style="font-size: 24px;">${parseFloat(msgData.price).toFixed(2)} zł</span>
                <div style="margin-top: 15px; font-size: 14px; color: #666;">
                    💡 Skontaktujcie się w celu ustalenia szczegółów odbioru
                </div>
            </div>
        `;
    }
}

// Dodaj style dla przycisku KUP
const buyButtonStyle = document.createElement('style');
buyButtonStyle.textContent = `
    .nego-btn-buy {
        background: linear-gradient(135deg, #10b981 0%, #059669 100%) !important;
        color: white !important;
        padding: 14px 24px !important;
        font-size: 16px !important;
        box-shadow: 0 5px 20px rgba(16, 185, 129, 0.3) !important;
        transition: 0.3s !important;
    }
    
    .nego-btn-buy:hover {
        transform: translateY(-3px) !important;
        box-shadow: 0 8px 30px rgba(16, 185, 129, 0.5) !important;
    }
`;
document.head.appendChild(buyButtonStyle);
function updatePrice(newPrice) {
    const displayPriceEl = document.getElementById('displayPrice');
    const priceDisplay = document.getElementById('currentPriceDisplay');
    
    if (displayPriceEl.textContent !== parseFloat(newPrice).toFixed(2)) {
        priceDisplay.classList.add('price-changed');
        setTimeout(() => priceDisplay.classList.remove('price-changed'), 500);
    }
    
    displayPriceEl.textContent = parseFloat(newPrice).toFixed(2);
}

async function proposeCounterPrice(messageId) {
    const input = document.getElementById(`counterInput_${messageId}`);
    const price = parseFloat(input.value);
    
    if (!price || price <= 0) {
        alert('Podaj prawidłową cenę');
        return;
    }
    
    if (price > 10000) {
        alert('Cena nie może przekraczać 10,000 zł');
        return;
    }
    
    try {
        const formData = new FormData();
        formData.append('propose_price', '1');
        formData.append('proposed_price', price);
        
        const resp = await fetch(`czat.php?produkt_id=${produktId}&user_id=${toId}`, {
            method: 'POST',
            body: formData,
            headers: {'X-Requested-With': 'XMLHttpRequest'}
        });
        
        if (resp.ok) {
            input.value = '';
            await fetchMessages();
        }
    } catch(e) {
        console.error('Błąd:', e);
        alert('Nie udało się wysłać propozycji');
    }
}

async function respondToPrice(action, negoId) {
    try {
        const formData = new FormData();
        formData.append('price_action', action);
        formData.append('nego_id', negoId);
        
        const resp = await fetch(`czat.php?produkt_id=${produktId}&user_id=${toId}`, {
            method: 'POST',
            body: formData,
            headers: {'X-Requested-With': 'XMLHttpRequest'}
        });
        
        if (resp.ok) {
            await fetchMessages();
        }
    } catch(e) {
        console.error('Błąd:', e);
        alert('Nie udało się wysłać odpowiedzi');
    }
}

form.addEventListener('submit', async function(e) {
    e.preventDefault();
    
    const message = input.value.trim();
    if (!message) return;
    
    const data = new FormData(form);
    
    try {
        const resp = await fetch(`czat.php?produkt_id=${produktId}&user_id=${toId}`, {
            method: 'POST',
            body: data,
            headers: {'X-Requested-With': 'XMLHttpRequest'}
        });
        
        if (resp.ok) {
            input.value = '';
            input.style.height = 'auto';
            charCounter.textContent = '0/1000';
            await fetchMessages();
        }
    } catch(e) {
        console.error('Błąd:', e);
    }
});

input.addEventListener('keydown', function(e) {
    if (e.key === 'Enter' && !e.shiftKey) {
        e.preventDefault();
        if (this.value.trim()) {
            form.dispatchEvent(new Event('submit'));
        }
    }
});

// Dodaj przycisk do rozpoczęcia negocjacji (tylko dla kupującego)
if (!isSeller) {
    const negotiateBtn = document.createElement('button');
    negotiateBtn.textContent = '💰 Negocjuj cenę';
    negotiateBtn.className = 'nego-btn nego-btn-counter';
    negotiateBtn.style.cssText = 'margin-bottom: 10px; width: 100%;';
    negotiateBtn.onclick = function() {
        const price = prompt('Zaproponuj swoją cenę:', originalPrice);
        if (price && parseFloat(price) > 0 && parseFloat(price) <= 10000) {
            const formData = new FormData();
            formData.append('propose_price', '1');
            formData.append('proposed_price', price);
            
            fetch(`czat.php?produkt_id=${produktId}&user_id=${toId}`, {
                method: 'POST',
                body: formData,
                headers: {'X-Requested-With': 'XMLHttpRequest'}
            }).then(() => fetchMessages());
        }
    };
    document.querySelector('.chat-input-container').prepend(negotiateBtn);
}

fetchMessages();
setInterval(fetchMessages, 2000);
</script>

</body>
<script>
// Toast Notifications
function showToast(type, title, message, duration = 5000) {
    let container = document.querySelector('.toast-container');
    if (!container) {
        container = document.createElement('div');
        container.className = 'toast-container';
        container.style.cssText = 'position: fixed; top: 100px; right: 20px; z-index: 10000; display: flex; flex-direction: column; gap: 10px;';
        document.body.appendChild(container);
    }
    
    const icons = { success: '✅', error: '❌', warning: '⚠️', info: 'ℹ️' };
    const colors = { 
        success: '#10b981', 
        error: '#ef4444', 
        warning: '#f59e0b', 
        info: '#3b82f6' 
    };
    
    const toast = document.createElement('div');
    toast.style.cssText = `
        min-width: 300px;
        background: white;
        padding: 20px;
        border-radius: 15px;
        box-shadow: 0 10px 40px rgba(0,0,0,0.3);
        display: flex;
        align-items: center;
        gap: 15px;
        animation: slideInRight 0.3s ease-out;
        position: relative;
        overflow: hidden;
        border-left: 5px solid ${colors[type]};
    `;
    
    toast.innerHTML = `
        <div style="font-size: 32px; flex-shrink: 0;">${icons[type]}</div>
        <div style="flex: 1;">
            <div style="font-weight: bold; font-size: 16px; margin-bottom: 5px;">${title}</div>
            <div style="font-size: 14px; color: #666; line-height: 1.4;">${message}</div>
        </div>
        <button onclick="this.parentElement.remove()" style="background: none; border: none; font-size: 24px; cursor: pointer; color: #999; padding: 0; width: 30px; height: 30px;">×</button>
        <div style="position: absolute; bottom: 0; left: 0; height: 4px; background: linear-gradient(90deg, #ff8c42, #ff6b35); width: 100%; transform-origin: left; animation: shrink ${duration}ms linear forwards;"></div>
    `;
    
    container.appendChild(toast);
    
    const style = document.createElement('style');
    style.textContent = `
        @keyframes slideInRight {
            from { transform: translateX(400px); opacity: 0; }
            to { transform: translateX(0); opacity: 1; }
        }
        @keyframes shrink {
            from { transform: scaleX(1); }
            to { transform: scaleX(0); }
        }
    `;
    document.head.appendChild(style);
    
    if (duration > 0) {
        setTimeout(() => toast.remove(), duration);
    }
}

// Dark Mode
function toggleTheme() {
    const html = document.documentElement;
    const currentTheme = html.getAttribute('data-theme');
    const newTheme = currentTheme === 'dark' ? 'light' : 'dark';
    
    html.setAttribute('data-theme', newTheme);
    localStorage.setItem('theme', newTheme);
    
    const icon = document.getElementById('themeIcon');
    if (icon) {
        icon.textContent = newTheme === 'dark' ? '☀️' : '🌙';
        icon.style.transform = 'rotate(360deg)';
        setTimeout(() => icon.style.transform = 'rotate(0deg)', 300);
    }
}

// Load saved theme
document.addEventListener('DOMContentLoaded', function() {
    const savedTheme = localStorage.getItem('theme') || 'light';
    document.documentElement.setAttribute('data-theme', savedTheme);
    
    const icon = document.getElementById('themeIcon');
    if (icon) {
        icon.textContent = savedTheme === 'dark' ? '☀️' : '🌙';
    }
});
</script>
</html>
<?php $conn->close(); ?>