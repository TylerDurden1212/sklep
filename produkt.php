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

// Pobierz liczbę nieprzeczytanych wiadomości
$unread_count = 0;
if (!empty($_SESSION['user_id'])) {
    $unread_count = getUnreadCount($_SESSION['user_id'], $conn);
}

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

.product-wrapper {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 40px;
    background: white;
    padding: 40px;
    border-radius: 25px;
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
                <a href="profil.php" class="menu-item">👤 Profil</a>
                <a href="logout.php" class="menu-item">Wyloguj</a>
            <?php else: ?>
                <a href="index.php" class="menu-item">🏠 Strona główna</a>
                <a href="logowanie.php" class="btn-add">🔑 Zaloguj się</a>
            <?php endif; ?>
        </div>
    </div>
</div>

<div class="container">
    <div class="product-wrapper">
        <!-- Galeria zdjęć -->
        <div class="product-gallery">
            <?php if (!empty($zdjecia)): ?>
                <div class="main-image-container" onclick="openGallery(0)">
                    <img src="<?= htmlspecialchars($zdjecia[0]) ?>" 
                         alt="<?= htmlspecialchars($product['nazwa']) ?>" 
                         class="main-product-image" 
                         id="mainImage"
                         onerror="this.src='https://via.placeholder.com/500x500/ff8c42/ffffff?text=Brak+zdjęcia'">
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
                <div class="price"><?= number_format($product['cena'], 2) ?> zł</div>
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

            <?php if ($isOwner): ?>
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
                    <a href="czat.php?produkt_id=<?= $product['id'] ?>&user_id=<?= $product['seller_id'] ?>" 
                       class="btn btn-primary">
                        💬 Napisz do sprzedawcy
                    </a>
                </div>
            <?php else: ?>
                <div class="action-buttons">
                    <a href="logowanie.php" class="btn btn-primary">
                        🔑 Zaloguj się, aby napisać
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
</script>

</body>
</html>