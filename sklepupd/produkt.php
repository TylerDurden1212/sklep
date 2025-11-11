<?php
session_start();
require_once 'config.php';

$conn = getDBConnection();

$productId = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($productId <= 0) {
    header("Location: index.php");
    exit;
}

// Pobierz produkt
$stmt = $conn->prepare("
    SELECT p.*, l.username AS sprzedawca, l.id AS seller_id, l.profile_picture 
    FROM produkty p 
    LEFT JOIN logi l ON p.id_sprzedawcy = l.id 
    WHERE p.id = ?
");
$stmt->bind_param("i", $productId);
$stmt->execute();
$result = $stmt->get_result();
$product = $result->fetch_assoc();
$stmt->close();

if (!$product) {
    header("Location: index.php");
    exit;
}
$display_price = $product['cena']; // Domyślna cena

if (!empty($_SESSION['user_id'])) {
    $stmt = $conn->prepare("
        SELECT current_price 
        FROM price_negotiations 
        WHERE produkt_id = ? 
        AND status = 'accepted'
        AND ((buyer_id = ? AND seller_id = ?) OR (buyer_id = ? AND seller_id = ?))
        ORDER BY updated_at DESC 
        LIMIT 1
    ");
    $buyer_id = $_SESSION['user_id'];
    $seller_id = $product['seller_id'];
    $stmt->bind_param("iiiii", $product['id'], $buyer_id, $seller_id, $seller_id, $buyer_id);
    $stmt->execute();
    $nego_result = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    
    if ($nego_result) {
        $display_price = $nego_result['current_price'];
    }
}
?>

// Pobierz liczbę nieprzeczytanych wiadomości
$unread_count = 0;
if (!empty($_SESSION['user_id'])) {
    $unread_count = getUnreadCount($_SESSION['user_id'], $conn);
}

// Pobierz liczbę nieprzeczytanych powiadomień
$notif_count = 0;
if (!empty($_SESSION['user_id'])) {
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM notifications WHERE user_id = ? AND is_read = 0");
    $uid = $_SESSION['user_id'];
    $stmt->bind_param("i", $uid);
    $stmt->execute();
    $notif_count = $stmt->get_result()->fetch_assoc()['count'];
    $stmt->close();
}

// Sprawdź czy użytkownik polubił ten produkt
$isLiked = false;
if (!empty($_SESSION['user_id'])) {
    $stmt = $conn->prepare("SELECT id FROM likes WHERE user_id = ? AND produkt_id = ?");
    $userId = $_SESSION['user_id'];
    $stmt->bind_param("ii", $userId, $productId);
    $stmt->execute();
    $isLiked = $stmt->get_result()->num_rows > 0;
    $stmt->close();
}

// Policz wszystkie polubienia produktu
$stmt = $conn->prepare("SELECT COUNT(*) as count FROM likes WHERE produkt_id = ?");
$stmt->bind_param("i", $productId);
$stmt->execute();
$likes_count = $stmt->get_result()->fetch_assoc()['count'];
$stmt->close();

$search = '';
$isOwner = !empty($_SESSION['user_id']) && $_SESSION['user_id'] == $product['id_sprzedawcy'];

// Dekoduj zdjęcia z JSON
$zdjecia = [];
if (!empty($product['zdjecie'])) {
    $decoded = json_decode($product['zdjecie'], true);
    if (is_array($decoded)) {
        $zdjecia = $decoded;
    } else {
        // Stary format - pojedyncze zdjęcie
        $zdjecia = [$product['zdjecie']];
    }
}

$conn->close();
?>
<!doctype html>
<html lang="pl">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= htmlspecialchars($product['nazwa']) ?> - GórkaSklep.pl</title>
<link rel="icon" href="./images/logo_strona.png">
<style>
* { margin: 0; padding: 0; box-sizing: border-box; }

:root {
    --primary: #ff8c42;
    --secondary: #ff6b35;
    --accent: #ffa500;
    --dark: #2c3e50;
    --light: #fff5f0;
    --white: #ffffff;
    --success: #10b981;
    --danger: #ef4444;
}

body {
    font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
    background: linear-gradient(135deg, #ff8c42 0%, #ff6b35 100%);
    min-height: 100vh;
}

/* Header */
.header {
    background: white;
    box-shadow: 0 4px 20px rgba(0,0,0,0.1);
    position: sticky;
    top: 0;
    z-index: 1000;
    animation: slideDown 0.5s;
}

@keyframes slideDown {
    from { transform: translateY(-100%); }
    to { transform: translateY(0); }
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

.logo-section:hover {
    transform: scale(1.02);
}

.logo-icon {
    font-size: 48px;
}

.logo-text {
    display: flex;
    flex-direction: column;
}

.logo-main {
    font-size: 28px;
    font-weight: 900;
    background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    letter-spacing: -0.5px;
    line-height: 1;
}

.logo-subtitle {
    font-size: 11px;
    color: #999;
    font-weight: 600;
    margin-top: 2px;
}

.school-link {
    font-size: 18px;
    color: var(--primary);
    text-decoration: none;
    display: flex;
    align-items: center;
    gap: 3px;
    margin-top: 2px;
    transition: 0.3s;
}

.school-link:hover {
    color: var(--secondary);
    text-decoration: underline;
}

.search-section {
    display: flex;
    gap: 10px;
}

.search-bar {
    flex: 1;
    position: relative;
}

.search-bar input {
    width: 100%;
    padding: 14px 50px 14px 20px;
    border: 2px solid #e0e0e0;
    border-radius: 30px;
    font-size: 15px;
    transition: 0.3s;
}

.search-bar input:focus {
    outline: none;
    border-color: var(--primary);
    box-shadow: 0 0 0 3px rgba(255, 140, 66, 0.1);
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
    transition: 0.3s;
}

.search-btn:hover {
    transform: translateY(-50%) scale(1.05);
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
    transition: 0.3s;
    font-weight: 600;
    font-size: 14px;
    position: relative;
    white-space: nowrap;
}

.menu-item:hover {
    background: var(--primary);
    color: white;
    transform: translateY(-2px);
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
    animation: pulse 2s infinite;
}

@keyframes pulse {
    0%, 100% { transform: scale(1); }
    50% { transform: scale(1.1); }
}

.btn-add {
    background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
    color: white;
    padding: 12px 24px;
    border-radius: 25px;
    text-decoration: none;
    font-weight: bold;
    transition: 0.3s;
    box-shadow: 0 4px 15px rgba(255, 140, 66, 0.3);
}

.btn-add:hover {
    transform: translateY(-3px);
    box-shadow: 0 6px 25px rgba(255, 140, 66, 0.5);
}

/* Main Container */
.container {
    max-width: 1200px;
    margin: 30px auto;
    padding: 0 20px;
}

.alert {
    padding: 18px 24px;
    border-radius: 15px;
    margin-bottom: 25px;
    text-align: center;
    font-weight: 500;
    animation: slideDown 0.3s;
    border-left: 5px solid;
}

.alert-success {
    background: #d4edda;
    color: #155724;
    border-color: #28a745;
}

.alert-error {
    background: #f8d7da;
    color: #721c24;
    border-color: #dc3545;
}

.product-wrapper {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 40px;
    background: white;
    padding: 40px;
    border-radius: 25px;
    box-shadow: 0 20px 60px rgba(0,0,0,0.3);
    animation: slideUp 0.5s;
    position: relative;
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

.sold-banner {
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    background: var(--success);
    color: white;
    padding: 20px;
    text-align: center;
    font-weight: bold;
    font-size: 20px;
    border-radius: 25px 25px 0 0;
    box-shadow: 0 4px 15px rgba(16, 185, 129, 0.3);
}

.product-wrapper.sold {
    padding-top: 80px;
}

/* Galeria zdjęć */
.product-gallery {
    position: relative;
}

.main-image-container {
    position: relative;
    height: 500px;
    border-radius: 20px;
    overflow: hidden;
    cursor: pointer;
    background: #f5f5f5;
    transition: 0.3s;
}

.main-image-container:hover {
    box-shadow: 0 10px 30px rgba(0,0,0,0.2);
}

.main-product-image {
    width: 100%;
    height: 100%;
    object-fit: contain;
    transition: 0.3s;
}

.main-image-container:hover .main-product-image {
    transform: scale(1.05);
}

.like-btn {
    position: absolute;
    top: 15px;
    right: 15px;
    background: white;
    border: none;
    width: 60px;
    height: 60px;
    border-radius: 50%;
    font-size: 28px;
    cursor: pointer;
    box-shadow: 0 5px 20px rgba(0,0,0,0.3);
    transition: 0.3s;
    z-index: 10;
    display: flex;
    align-items: center;
    justify-content: center;
}

.like-btn:hover {
    transform: scale(1.15);
    box-shadow: 0 8px 30px rgba(0,0,0,0.4);
}

.like-btn.liked {
    background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
    color: white;
    animation: heartbeat 0.3s;
}

@keyframes heartbeat {
    0%, 100% { transform: scale(1); }
    50% { transform: scale(1.2); }
}

.like-count {
    position: absolute;
    top: 80px;
    right: 15px;
    background: white;
    padding: 8px 15px;
    border-radius: 20px;
    font-size: 14px;
    font-weight: bold;
    color: var(--primary);
    box-shadow: 0 3px 15px rgba(0,0,0,0.2);
}

.gallery-thumbnails {
    display: flex;
    gap: 10px;
    margin-top: 15px;
    overflow-x: auto;
    padding: 10px 0;
}

.gallery-thumbnails::-webkit-scrollbar {
    height: 8px;
}

.gallery-thumbnails::-webkit-scrollbar-track {
    background: #f1f1f1;
    border-radius: 10px;
}

.gallery-thumbnails::-webkit-scrollbar-thumb {
    background: var(--primary);
    border-radius: 10px;
}

.thumbnail {
    width: 100px;
    height: 100px;
    border-radius: 10px;
    object-fit: cover;
    cursor: pointer;
    border: 3px solid transparent;
    transition: 0.3s;
    flex-shrink: 0;
}

.thumbnail:hover {
    border-color: var(--primary);
    transform: scale(1.05);
}

.thumbnail.active {
    border-color: var(--primary);
    box-shadow: 0 0 0 2px white, 0 0 0 4px var(--primary);
}

.gallery-badge {
    position: absolute;
    bottom: 15px;
    right: 15px;
    background: rgba(0,0,0,0.7);
    color: white;
    padding: 8px 15px;
    border-radius: 20px;
    font-size: 14px;
    font-weight: bold;
    backdrop-filter: blur(10px);
}

/* Modal galerii */
.gallery-modal {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0,0,0,0.95);
    z-index: 10000;
    align-items: center;
    justify-content: center;
}

.gallery-modal.active {
    display: flex;
}

.modal-content {
    position: relative;
    max-width: 90%;
    max-height: 90%;
}

.modal-image {
    max-width: 100%;
    max-height: 90vh;
    object-fit: contain;
    border-radius: 10px;
}

.modal-close {
    position: absolute;
    top: 20px;
    right: 20px;
    background: white;
    border: none;
    width: 50px;
    height: 50px;
    border-radius: 50%;
    font-size: 30px;
    cursor: pointer;
    z-index: 10001;
    transition: 0.3s;
    display: flex;
    align-items: center;
    justify-content: center;
}

.modal-close:hover {
    background: var(--danger);
    color: white;
    transform: rotate(90deg);
}

.modal-nav {
    position: absolute;
    top: 50%;
    transform: translateY(-50%);
    background: rgba(255,255,255,0.9);
    border: none;
    width: 60px;
    height: 60px;
    border-radius: 50%;
    font-size: 30px;
    cursor: pointer;
    transition: 0.3s;
    z-index: 10001;
    display: flex;
    align-items: center;
    justify-content: center;
}

.modal-nav:hover {
    background: var(--primary);
    color: white;
    transform: translateY(-50%) scale(1.1);
}

.modal-nav.prev {
    left: 20px;
}

.modal-nav.next {
    right: 20px;
}

.modal-counter {
    position: absolute;
    bottom: 20px;
    left: 50%;
    transform: translateX(-50%);
    background: rgba(255,255,255,0.9);
    padding: 10px 20px;
    border-radius: 20px;
    font-weight: bold;
    z-index: 10001;
}

/* Product Info */
.product-info {
    display: flex;
    flex-direction: column;
    gap: 25px;
}

.category-badge {
    display: inline-block;
    padding: 8px 16px;
    background: var(--light);
    color: var(--primary);
    border-radius: 20px;
    font-size: 14px;
    font-weight: 600;
    width: fit-content;
}

.product-title {
    font-size: 36px;
    color: var(--dark);
    font-weight: 900;
    line-height: 1.2;
}

.price-section {
    background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
    padding: 25px;
    border-radius: 20px;
    text-align: center;
}

.price-label {
    color: white;
    font-size: 16px;
    margin-bottom: 10px;
    opacity: 0.9;
}

.price {
    font-size: 48px;
    font-weight: 900;
    color: white;
}

.description-section {
    background: var(--light);
    padding: 25px;
    border-radius: 20px;
}

.section-title {
    font-size: 20px;
    color: var(--dark);
    margin-bottom: 15px;
    font-weight: 700;
}

.description-text {
    color: #666;
    line-height: 1.8;
    font-size: 15px;
    white-space: pre-wrap;
}

.seller-section {
    background: white;
    border: 2px solid var(--light);
    padding: 20px;
    border-radius: 20px;
    display: flex;
    align-items: center;
    gap: 15px;
    transition: 0.3s;
    cursor: pointer;
}

.seller-section:hover {
    border-color: var(--primary);
    box-shadow: 0 5px 20px rgba(255, 140, 66, 0.2);
}

.seller-avatar {
    width: 60px;
    height: 60px;
    border-radius: 50%;
    object-fit: cover;
    border: 3px solid var(--primary);
}

.seller-avatar-placeholder {
    width: 60px;
    height: 60px;
    border-radius: 50%;
    background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
    color: white;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: bold;
    font-size: 24px;
    border: 3px solid var(--primary);
}

.seller-info h3 {
    font-size: 18px;
    color: var(--dark);
    margin-bottom: 5px;
}

.seller-info p {
    color: #999;
    font-size: 13px;
}

.action-buttons {
    display: flex;
    flex-direction: column;
    gap: 12px;
}

.btn {
    padding: 16px 24px;
    border-radius: 15px;
    font-size: 16px;
    font-weight: bold;
    text-align: center;
    text-decoration: none;
    cursor: pointer;
    transition: 0.3s;
    border: none;
}

.btn-buy {
    background: linear-gradient(135deg, var(--success) 0%, #059669 100%);
    color: white;
    box-shadow: 0 5px 20px rgba(16, 185, 129, 0.3);
    font-size: 18px;
}

.btn-buy:hover {
    transform: translateY(-3px);
    box-shadow: 0 8px 30px rgba(16, 185, 129, 0.5);
}

.btn-primary {
    background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
    color: white;
    box-shadow: 0 5px 20px rgba(255, 140, 66, 0.3);
}

.btn-primary:hover {
    transform: translateY(-3px);
    box-shadow: 0 8px 30px rgba(255, 140, 66, 0.5);
}

.btn-secondary {
    background: white;
    color: var(--primary);
    border: 2px solid var(--primary);
}

.btn-secondary:hover {
    background: var(--primary);
    color: white;
}

.btn-danger {
    background: var(--danger);
    color: white;
}

.btn-danger:hover {
    background: #dc2626;
    transform: translateY(-3px);
}

.meta-info {
    display: flex;
    justify-content: space-between;
    padding-top: 20px;
    border-top: 2px solid var(--light);
    color: #999;
    font-size: 13px;
}

@media (max-width: 968px) {
    .header-content {
        grid-template-columns: 1fr;
        gap: 15px;
    }
    
    .search-section {
        order: 3;
    }
    
    .user-menu {
        order: 2;
        justify-content: center;
        flex-wrap: wrap;
    }
    
    .product-wrapper {
        grid-template-columns: 1fr;
        padding: 25px;
        gap: 30px;
    }
    
    .product-title {
        font-size: 28px;
    }
    
    .price {
        font-size: 36px;
    }
    
    .main-image-container {
        height: 350px;
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
                <input type="text" 
                       name="search" 
                       placeholder="Czego szukasz? 🔍" 
                       value="<?= htmlspecialchars($search) ?>">
                <button type="submit" class="search-btn">Szukaj</button>
            </div>
        </form>

        <div class="user-menu">
            <?php if (!empty($_SESSION['user_id'])): ?>
                <a href="index.php" class="menu-item">🏠 Strona główna</a>
                <a href="wiadomosci.php" class="menu-item">
                    💬 Wiadomości
                    <?php if ($unread_count > 0): ?>
                        <span class="badge"><?= $unread_count ?></span>
                    <?php endif; ?>
                </a>
                <a href="powiadomienia.php" class="menu-item">
                    🔔 Powiadomienia
                    <?php if ($notif_count > 0): ?>
                        <span class="badge"><?= $notif_count ?></span>
                    <?php endif; ?>
                </a>
                <a href="profil.php" class="menu-item">👤 Profil</a>
                <a href="polubione.php" class="menu-item">❤️ Polubione</a>
                <a href="logout.php" class="menu-item">Wyloguj</a>
            <?php else: ?>
                <a href="index.php" class="menu-item">🏠 Strona główna</a>
                <a href="logowanie.php" class="btn-add">🔑 Zaloguj się</a>
            <?php endif; ?>
        </div>
    </div>
</div>

<div class="container">
    <?php if (isset($_GET['success'])): ?>
        <div class="alert alert-success">
            ✅ <?= htmlspecialchars($_GET['success']) ?>
        </div>
    <?php endif; ?>
    
    <?php if (isset($_GET['error'])): ?>
        <div class="alert alert-error">
            ⚠️ <?= htmlspecialchars($_GET['error']) ?>
        </div>
    <?php endif; ?>

    <div class="product-wrapper <?= $product['is_sold'] == 1 ? 'sold' : '' ?>">
        <?php if ($product['is_sold'] == 1): ?>
            <div class="sold-banner">
                ✅ PRODUKT SPRZEDANY
                <?php if (!empty($_SESSION['user_id']) && $product['buyer_id'] == $_SESSION['user_id']): ?>
                    - Kupiłeś ten produkt!
                <?php endif; ?>
            </div>
        <?php endif; ?>
        
        <!-- Galeria zdjęć -->
        <div class="product-gallery">
            <?php if (!empty($zdjecia)): ?>
                <div class="main-image-container" onclick="openGallery(0)">
                    <img src="<?= htmlspecialchars($zdjecia[0]) ?>" 
                         alt="<?= htmlspecialchars($product['nazwa']) ?>" 
                         class="main-product-image" 
                         id="mainImage"
                         onerror="this.src='https://via.placeholder.com/500x500/ff8c42/ffffff?text=Brak+zdjęcia'">
                    
                    <?php if (!empty($_SESSION['user_id']) && !$isOwner): ?>
                        <button class="like-btn <?= $isLiked ? 'liked' : '' ?>" 
                                id="likeBtn" 
                                onclick="event.stopPropagation(); toggleLike()">
                            <?= $isLiked ? '❤️' : '🤍' ?>
                        </button>
                        <?php if ($likes_count > 0): ?>
                            <div class="like-count" id="likeCount">
                                ❤️ <?= $likes_count ?>
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>
                    
                    <?php if (count($zdjecia) > 1): ?>
                        <div class="gallery-badge">📷 <?= count($zdjecia) ?> zdjęć</div>
                    <?php endif; ?>
                </div>
                
                <?php if (count($zdjecia) > 1): ?>
                    <div class="gallery-thumbnails">
                        <?php foreach ($zdjecia as $index => $zdjecie): ?>
                            <img src="<?= htmlspecialchars($zdjecie) ?>" 
                                 alt="Miniatura <?= $index + 1 ?>" 
                                 class="thumbnail <?= $index === 0 ? 'active' : '' ?>"
                                 onclick="changeMainImage(<?= $index ?>)"
                                 onerror="this.src='https://via.placeholder.com/100/ff8c42/ffffff?text=?'">
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            <?php else: ?>
                <div class="main-image-container">
                    <img src="https://via.placeholder.com/500x500/ff8c42/ffffff?text=Brak+zdjęcia" 
                         alt="Brak zdjęcia" 
                         class="main-product-image">
                </div>
            <?php endif; ?>
        </div>

        <!-- Informacje o produkcie -->
        <div class="product-info">
            <?php if (!empty($product['kategoria'])): ?>
                <div class="category-badge">
                    <?php
                    $icons = [
                        'elektronika' => '📱 Elektronika',
                        'odziez' => '👕 Odzież',
                        'dom' => '🏠 Dom i Ogród',
                        'sport' => '⚽ Sport',
                        'inne' => '📦 Inne'
                    ];
                    echo $icons[$product['kategoria']] ?? '📦 Inne';
                    ?>
                </div>
            <?php endif; ?>

            <h1 class="product-title"><?= htmlspecialchars($product['nazwa']) ?></h1>

            <div class="price-section">
                <div class="price-label">Cena</div>
                <div class="price"><?= number_format($display_price, 2) ?> zł</div>
                <?php if ($display_price != $product['cena']): ?>
                    <div style="font-size: 14px; color: rgba(255,255,255,0.8); margin-top: 10px;">
                        <del><?= number_format($product['cena'], 2) ?> zł</del> - Twoja wynegocjowana cena
                    </div>
                <?php endif; ?>
            </div>


            <div class="description-section">
                <h2 class="section-title">📋 Opis produktu</h2>
                <div class="description-text"><?= htmlspecialchars($product['opis']) ?></div>
            </div>

            <div class="seller-section" onclick="window.location='profil.php?id=<?= $product['seller_id'] ?>'">
                <?php if (!empty($product['profile_picture'])): ?>
                    <img src="<?= htmlspecialchars($product['profile_picture']) ?>" 
                         alt="<?= htmlspecialchars($product['sprzedawca']) ?>" 
                         class="seller-avatar"
                         onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                    <div class="seller-avatar-placeholder" style="display:none;">
                        <?= strtoupper(substr($product['sprzedawca'] ?? 'U', 0, 1)) ?>
                    </div>
                <?php else: ?>
                    <div class="seller-avatar-placeholder">
                        <?= strtoupper(substr($product['sprzedawca'] ?? 'U', 0, 1)) ?>
                    </div>
                <?php endif; ?>
                <div class="seller-info">
                    <h3>👤 Sprzedawca</h3>
                    <p><?= htmlspecialchars($product['sprzedawca'] ?? 'Nieznany') ?></p>
                </div>
            </div>
            <!-- NOWE: System ocen -->
            <?php 
            // Wyświetl oceny sprzedawcy
            if (!empty($product['is_sold']) && $product['is_sold'] == 1 && 
                !empty($_SESSION['user_id']) && 
                !empty($product['buyer_id']) && 
                $product['buyer_id'] == $_SESSION['user_id']) {
                // Kupujący może wystawić ocenę
                echo getRatingHTML($product['seller_id'], true, $product['id']);
            } else {
                // Tylko wyświetl oceny
                echo getRatingHTML($product['seller_id'], false);
            }
            ?>
            <?php if ($product['is_sold'] == 1): ?>
                <!-- Produkt sprzedany - brak akcji -->
                <div style="text-align: center; padding: 20px; background: #f0f0f0; border-radius: 15px; color: #666;">
                    <strong>Ten produkt został już sprzedany</strong>
                </div>
            <?php elseif ($isOwner): ?>
                <div class="action-buttons">
                    <a href="edytuj_produkt.php?id=<?= $product['id'] ?>" class="btn btn-secondary">
                        ✏️ Edytuj ogłoszenie
                    </a>
                    <a href="usun_produkt.php?id=<?= $product['id'] ?>" 
                       class="btn btn-danger" 
                       onclick="return confirm('Czy na pewno chcesz usunąć ten produkt?')">
                        🗑️ Usuń ogłoszenie
                    </a>
                </div>
            <?php elseif (!empty($_SESSION['user_id'])): ?>
                <div class="action-buttons">
                    <a href="kup_produkt.php?id=<?= $product['id'] ?>" class="btn btn-buy">
                        🛒 KUP TERAZ
                    </a>
                    <a href="czat.php?produkt_id=<?= $product['id'] ?>&user_id=<?= $product['seller_id'] ?>" 
                       class="btn btn-primary">
                        💬 Napisz do sprzedawcy
                    </a>
                </div>
            <?php else: ?>
                <div class="action-buttons">
                    <a href="logowanie.php" class="btn btn-buy">
                        🔑 Zaloguj się, aby kupić
                    </a>
                </div>
            <?php endif; ?>

            <div class="meta-info">
                <span>📅 Dodano: <?= date('d.m.Y', strtotime($product['data_dodania'])) ?></span>
                <span>🆔 ID: <?= $product['id'] ?></span>
            </div>
        </div>
    </div>
</div>

<!-- Modal galerii -->
<?php if (!empty($zdjecia)): ?>
<div class="gallery-modal" id="galleryModal">
    <button class="modal-close" onclick="closeGallery()">×</button>
    <?php if (count($zdjecia) > 1): ?>
        <button class="modal-nav prev" onclick="prevImage()">‹</button>
        <button class="modal-nav next" onclick="nextImage()">›</button>
    <?php endif; ?>
    <div class="modal-content">
        <img src="" alt="Galeria" class="modal-image" id="modalImage">
    </div>
    <div class="modal-counter" id="modalCounter">1 / <?= count($zdjecia) ?></div>
</div>
<?php endif; ?>

<script>
// Galeria zdjęć
const zdjecia = <?= json_encode($zdjecia) ?>;
let currentImageIndex = 0;

function changeMainImage(index) {
    currentImageIndex = index;
    document.getElementById('mainImage').src = zdjecia[index];
    
    // Aktualizuj aktywną miniaturę
    document.querySelectorAll('.thumbnail').forEach((thumb, i) => {
        thumb.classList.toggle('active', i === index);
    });
}

function openGallery(index) {
    if (zdjecia.length === 0) return;
    
    currentImageIndex = index;
    const modal = document.getElementById('galleryModal');
    const modalImage = document.getElementById('modalImage');
    
    modalImage.src = zdjecia[index];
    modal.classList.add('active');
    updateCounter();
    
    // Zablokuj scrollowanie tła
    document.body.style.overflow = 'hidden';
}

function closeGallery() {
    const modal = document.getElementById('galleryModal');
    modal.classList.remove('active');
    document.body.style.overflow = 'auto';
}

function nextImage() {
    currentImageIndex = (currentImageIndex + 1) % zdjecia.length;
    document.getElementById('modalImage').src = zdjecia[currentImageIndex];
    updateCounter();
}

function prevImage() {
    currentImageIndex = (currentImageIndex - 1 + zdjecia.length) % zdjecia.length;
    document.getElementById('modalImage').src = zdjecia[currentImageIndex];
    updateCounter();
}

function updateCounter() {
    document.getElementById('modalCounter').textContent = 
        `${currentImageIndex + 1} / ${zdjecia.length}`;
}

// Zamykanie modala po kliknięciu poza obrazem
document.getElementById('galleryModal')?.addEventListener('click', function(e) {
    if (e.target === this) {
        closeGallery();
    }
});

// Obsługa klawiszy strzałek
document.addEventListener('keydown', function(e) {
    const modal = document.getElementById('galleryModal');
    if (!modal || !modal.classList.contains('active')) return;
    
    if (e.key === 'ArrowLeft') prevImage();
    if (e.key === 'ArrowRight') nextImage();
    if (e.key === 'Escape') closeGallery();
});

// System polubień
async function toggleLike() {
    try {
        const formData = new FormData();
        formData.append('product_id', <?= $productId ?>);
        
        const resp = await fetch('like_product.php', {
            method: 'POST',
            body: formData
        });
        
        const data = await resp.json();
        
        if (data.success) {
            const likeBtn = document.getElementById('likeBtn');
            const likeCount = document.getElementById('likeCount');
            
            if (data.liked) {
                likeBtn.textContent = '❤️';
                likeBtn.classList.add('liked');
            } else {
                likeBtn.textContent = '🤍';
                likeBtn.classList.remove('liked');
            }
            
            // Aktualizuj licznik
            if (data.count > 0) {
                if (likeCount) {
                    likeCount.innerHTML = `❤️ ${data.count}`;
                } else {
                    // Utwórz licznik jeśli nie istnieje
                    const countDiv = document.createElement('div');
                    countDiv.className = 'like-count';
                    countDiv.id = 'likeCount';
                    countDiv.innerHTML = `❤️ ${data.count}`;
                    document.querySelector('.main-image-container').appendChild(countDiv);
                }
            } else if (likeCount) {
                likeCount.remove();
            }
        }
    } catch(e) {
        console.error('Błąd podczas polubienia:', e);
        alert('Nie udało się zmienić statusu polubienia');
    }
}

// Auto-odświeżanie licznika nieprzeczytanych wiadomości
<?php if (!empty($_SESSION['user_id'])): ?>
setInterval(async () => {
    try {
        const resp = await fetch('check_messages.php', {cache: 'no-store'});
        const data = await resp.json();
        
        const badges = document.querySelectorAll('.user-menu .badge');
        badges.forEach(badge => {
            if (badge.parentElement.href.includes('wiadomosci.php')) {
                if (data.count > 0) {
                    badge.textContent = data.count;
                } else {
                    badge.remove();
                }
            }
        });
    } catch(e) {
        console.error('Błąd sprawdzania wiadomości:', e);
    }
}, 30000);

// Auto-odświeżanie licznika powiadomień
setInterval(async () => {
    try {
        const resp = await fetch('get_notifications.php', {cache: 'no-store'});
        const data = await resp.json();
        
        const unreadCount = data.notifications.filter(n => n.is_read == 0).length;
        
        const badges = document.querySelectorAll('.user-menu .badge');
        badges.forEach(badge => {
            if (badge.parentElement.href.includes('powiadomienia.php')) {
                if (unreadCount > 0) {
                    badge.textContent = unreadCount;
                } else {
                    badge.remove();
                }
            }
        });
    } catch(e) {
        console.error('Błąd sprawdzania powiadomień:', e);
    }
}, 30000);
<?php endif; ?>


// Zabezpieczenie przed DevTools i Console
(function() {
    'use strict';
    
    // Wykryj czy DevTools jest otwarty
    const devtools = {
        isOpen: false,
        orientation: null
    };
    
    const threshold = 160;
    
    const emitEvent = (isOpen, orientation) => {
        if (devtools.isOpen !== isOpen || devtools.orientation !== orientation) {
            if (isOpen) {
                // DevTools został otwarty - przekieruj
                window.location.href = 'index.php';
            }
        }
        devtools.isOpen = isOpen;
        devtools.orientation = orientation;
    };
    
    setInterval(() => {
        const widthThreshold = window.outerWidth - window.innerWidth > threshold;
        const heightThreshold = window.outerHeight - window.innerHeight > threshold;
        const orientation = widthThreshold ? 'vertical' : 'horizontal';
        
        if (heightThreshold || widthThreshold) {
            emitEvent(true, orientation);
        } else {
            emitEvent(false, null);
        }
    }, 500);
    
    // Wyłącz prawy przycisk myszy
    document.addEventListener('contextmenu', e => e.preventDefault());
    
    // Wyłącz F12, Ctrl+Shift+I, Ctrl+Shift+C, Ctrl+Shift+J, Ctrl+U
    document.addEventListener('keydown', e => {
        if (
            e.keyCode === 123 || // F12
            (e.ctrlKey && e.shiftKey && e.keyCode === 73) || // Ctrl+Shift+I
            (e.ctrlKey && e.shiftKey && e.keyCode === 67) || // Ctrl+Shift+C
            (e.ctrlKey && e.shiftKey && e.keyCode === 74) || // Ctrl+Shift+J
            (e.ctrlKey && e.keyCode === 85) // Ctrl+U
        ) {
            e.preventDefault();
            return false;
        }
    });
    
    // Nadpisz console
    if (window.location.hostname !== 'localhost' && window.location.hostname !== '127.0.0.1') {
        console.log = function() {};
        console.warn = function() {};
        console.error = function() {};
        console.info = function() {};
        console.debug = function() {};
    }
})();

// Zabezpieczenie przed nieskończonymi pętlami fetch
(function() {
    const originalFetch = window.fetch;
    let fetchCount = 0;
    let lastReset = Date.now();
    const LIMIT = 50; // Max 50 requestów
    const WINDOW = 10000; // W ciągu 10 sekund
    
    window.fetch = function(...args) {
        const now = Date.now();
        
        // Reset licznika co 10 sekund
        if (now - lastReset > WINDOW) {
            fetchCount = 0;
            lastReset = now;
        }
        
        fetchCount++;
        
        // Blokada jeśli przekroczono limit
        if (fetchCount > LIMIT) {
            console.warn('Zbyt wiele żądań - blokada fetch()');
            return Promise.reject(new Error('Rate limit exceeded'));
        }
        
        return originalFetch.apply(this, args);
    };
})();
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
</body>
</html>