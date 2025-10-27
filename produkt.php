<?php
session_start();
require_once 'config.php';

$conn = getDBConnection();

$id = intval($_GET['id'] ?? 0);

$result = $conn->query("
    SELECT p.*, l.username AS sprzedawca, l.id AS sprzedawca_id, l.profile_picture, l.bio
    FROM produkty p
    LEFT JOIN logi l ON p.id_sprzedawcy = l.id
    WHERE p.id = $id
");

if ($result->num_rows === 0) {
    die("Nie znaleziono produktu.");
}

$produkt = $result->fetch_assoc();

// Sprawdź czy użytkownik zalogowany
$logged_in = !empty($_SESSION['user_id']);
$current_user_id = $_SESSION['user_id'] ?? null;

// Pobierz nieprzeczytane wiadomości
$unread_count = 0;
if ($logged_in) {
    $unread_count = getUnreadCount($current_user_id, $conn);
}

$conn->close();
?>
<!doctype html>
<html lang="pl">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= htmlspecialchars($produkt['nazwa']) ?> - Sklep</title>
<link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><text y='.9em' font-size='90'>🛍️</text></svg>">
<style>
* { margin: 0; padding: 0; box-sizing: border-box; }

:root {
    --primary: #667eea;
    --secondary: #764ba2;
    --success: #10b981;
    --danger: #ef4444;
    --warning: #f59e0b;
    --dark: #1f2937;
    --light: #f8f9fa;
}

body {
    font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
    background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
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
    padding: 20px;
    display: grid;
    grid-template-columns: auto 1fr auto;
    gap: 25px;
    align-items: center;
}

.logo {
    font-size: 32px;
    font-weight: 900;
    background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    cursor: pointer;
    transition: 0.3s;
    letter-spacing: -1px;
}

.logo:hover {
    transform: scale(1.05);
}

.header-title {
    text-align: center;
    font-size: 24px;
    font-weight: bold;
    color: var(--dark);
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

.btn-add {
    background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
    color: white;
    padding: 12px 24px;
    border-radius: 25px;
    text-decoration: none;
    font-weight: bold;
    transition: 0.3s;
    box-shadow: 0 4px 15px rgba(102, 126, 234, 0.3);
}

.btn-add:hover {
    transform: translateY(-3px);
    box-shadow: 0 6px 25px rgba(102, 126, 234, 0.5);
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
    animation: pulse 2s infinite;
}

@keyframes pulse {
    0%, 100% { transform: scale(1); }
    50% { transform: scale(1.1); }
}

/* Main Content */
.container {
    max-width: 1200px;
    margin: 30px auto;
    padding: 0 20px;
}

.product-container {
    background: white;
    border-radius: 25px;
    overflow: hidden;
    box-shadow: 0 20px 60px rgba(0,0,0,0.3);
    display: grid;
    grid-template-columns: 1.2fr 1fr;
    gap: 0;
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

.product-image-section {
    position: relative;
    background: #f8f9fa;
}

.product-image {
    width: 100%;
    height: 600px;
    object-fit: cover;
}

.image-badge {
    position: absolute;
    top: 20px;
    left: 20px;
    background: rgba(255,255,255,0.95);
    padding: 10px 20px;
    border-radius: 25px;
    font-weight: bold;
    color: var(--primary);
    box-shadow: 0 5px 15px rgba(0,0,0,0.2);
}

.product-details {
    padding: 50px;
    display: flex;
    flex-direction: column;
}

.product-category {
    display: inline-block;
    background: #f0f0ff;
    color: var(--primary);
    padding: 8px 16px;
    border-radius: 20px;
    font-size: 13px;
    font-weight: 600;
    margin-bottom: 15px;
}

.product-title {
    font-size: 36px;
    color: #333;
    margin-bottom: 20px;
    line-height: 1.3;
}

.product-price {
    font-size: 42px;
    font-weight: bold;
    background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    margin-bottom: 25px;
}

.product-meta {
    display: flex;
    gap: 25px;
    margin-bottom: 30px;
    padding-bottom: 30px;
    border-bottom: 2px solid #f0f0f0;
}

.meta-item {
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 14px;
    color: #666;
}

.meta-item strong {
    color: #333;
}

.product-description {
    font-size: 16px;
    color: #666;
    line-height: 1.8;
    margin-bottom: 35px;
    flex: 1;
    white-space: pre-wrap;
}

.seller-section {
    border-top: 2px solid #f0f0f0;
    padding-top: 30px;
    margin-top: auto;
}

.seller-header {
    font-size: 14px;
    color: #999;
    margin-bottom: 15px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 1px;
}

.seller-card {
    display: flex;
    align-items: center;
    gap: 20px;
    padding: 20px;
    background: #f8f9fa;
    border-radius: 15px;
    margin-bottom: 20px;
    transition: 0.3s;
    cursor: pointer;
}

.seller-card:hover {
    background: #f0f0f0;
    transform: translateX(5px);
}

.seller-avatar {
    width: 70px;
    height: 70px;
    border-radius: 50%;
    object-fit: cover;
    box-shadow: 0 5px 15px rgba(102, 126, 234, 0.3);
}

.seller-avatar-placeholder {
    width: 70px;
    height: 70px;
    border-radius: 50%;
    background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
    color: white;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 28px;
    font-weight: bold;
    box-shadow: 0 5px 15px rgba(102, 126, 234, 0.3);
}

.seller-info {
    flex: 1;
}

.seller-name {
    font-size: 20px;
    font-weight: bold;
    color: #333;
    margin-bottom: 5px;
}

.seller-bio {
    font-size: 14px;
    color: #666;
    line-height: 1.5;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
}

.action-buttons {
    display: flex;
    gap: 12px;
}

.btn {
    flex: 1;
    padding: 16px 24px;
    border: none;
    border-radius: 15px;
    font-size: 16px;
    font-weight: bold;
    cursor: pointer;
    transition: 0.3s;
    text-decoration: none;
    text-align: center;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 10px;
}

.btn-primary {
    background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
    color: white;
}

.btn-primary:hover {
    transform: translateY(-3px);
    box-shadow: 0 10px 25px rgba(102, 126, 234, 0.4);
}

.btn-secondary {
    background: white;
    color: var(--primary);
    border: 2px solid var(--primary);
}

.btn-secondary:hover {
    background: #f0f0ff;
}

.additional-info {
    background: #fff9e6;
    padding: 20px;
    border-radius: 15px;
    margin-top: 20px;
    border-left: 4px solid var(--warning);
}

.additional-info h4 {
    color: #f57c00;
    margin-bottom: 10px;
    font-size: 14px;
}

.additional-info p {
    font-size: 13px;
    color: #666;
    line-height: 1.6;
}

@media (max-width: 968px) {
    .header-content {
        grid-template-columns: 1fr;
        gap: 15px;
    }
    
    .header-title {
        order: 2;
    }
    
    .user-menu {
        order: 3;
        justify-content: center;
        flex-wrap: wrap;
    }
    
    .product-container {
        grid-template-columns: 1fr;
    }
    
    .product-image {
        height: 400px;
    }
    
    .product-details {
        padding: 30px 25px;
    }
    
    .product-title {
        font-size: 28px;
    }
    
    .product-price {
        font-size: 32px;
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
        <div class="logo" onclick="window.location='index.php'">🛍️ SKLEP</div>
        
        <div class="header-title">📦 Szczegóły produktu</div>

        <div class="user-menu">
            <?php if ($logged_in): ?>
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
            <?php else: ?>
                <a href="index.php" class="menu-item">🏠 Strona główna</a>
                <a href="logowanie.php" class="btn-add">🔑 Zaloguj się</a>
            <?php endif; ?>
        </div>
    </div>
</div>

<div class="container">
    <div class="product-container">
        <div class="product-image-section">
            <?php if (!empty($produkt['zdjecie'])): ?>
                <img src="<?= htmlspecialchars($produkt['zdjecie']) ?>" alt="<?= htmlspecialchars($produkt['nazwa']) ?>" class="product-image" onerror="this.src='https://via.placeholder.com/800x600?text=Brak+zdjęcia'">
            <?php else: ?>
                <img src="https://via.placeholder.com/800x600?text=Brak+zdjęcia" alt="Brak zdjęcia" class="product-image">
            <?php endif; ?>
            
            <?php if (!empty($produkt['kategoria'])): ?>
                <div class="image-badge">
                    <?php echo getCategoryIcon($produkt['kategoria']); ?> 
                    <?php echo ucfirst($produkt['kategoria']); ?>
                </div>
            <?php endif; ?>
        </div>

        <div class="product-details">
            <span class="product-category">
                <?php echo getCategoryIcon($produkt['kategoria']); ?> 
                <?php echo getCategoryName($produkt['kategoria']); ?>
            </span>
            
            <h1 class="product-title"><?= htmlspecialchars($produkt['nazwa']) ?></h1>
            
            <div class="product-price"><?= number_format($produkt['cena'], 2) ?> zł</div>
            
            <div class="product-meta">
                <div class="meta-item">
                    📅 Dodano: <strong><?= date('d.m.Y', strtotime($produkt['data_dodania'])) ?></strong>
                </div>
                <div class="meta-item">
                    👁️ ID: <strong>#<?= $produkt['id'] ?></strong>
                </div>
            </div>
            
            <div class="product-description">
                <?= nl2br(htmlspecialchars($produkt['opis'])) ?>
            </div>

            <div class="seller-section">
                <div class="seller-header">👤 Sprzedawca</div>
                
                <div class="seller-card" onclick="window.location='profil.php?id=<?= $produkt['sprzedawca_id'] ?>'">
                    <?php if (!empty($produkt['profile_picture'])): ?>
                        <img src="<?= htmlspecialchars($produkt['profile_picture']) ?>" alt="Avatar" class="seller-avatar">
                    <?php else: ?>
                        <div class="seller-avatar-placeholder">
                            <?= strtoupper(substr($produkt['sprzedawca'] ?? 'U', 0, 1)) ?>
                        </div>
                    <?php endif; ?>
                    
                    <div class="seller-info">
                        <div class="seller-name"><?= htmlspecialchars($produkt['sprzedawca'] ?? 'Nieznany') ?></div>
                        <?php if (!empty($produkt['bio'])): ?>
                            <div class="seller-bio"><?= htmlspecialchars($produkt['bio']) ?></div>
                        <?php else: ?>
                            <div class="seller-bio" style="color:#999;">Kliknij, aby zobaczyć profil</div>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="action-buttons">
                    <?php if ($logged_in && $current_user_id != $produkt['sprzedawca_id']): ?>
                        <a href="czat.php?produkt_id=<?= $produkt['id'] ?>&user_id=<?= $produkt['sprzedawca_id'] ?>" class="btn btn-primary">
                            💬 Napisz do sprzedawcy
                        </a>
                        <a href="profil.php?id=<?= $produkt['sprzedawca_id'] ?>" class="btn btn-secondary">
                            👤 Zobacz profil
                        </a>
                    <?php elseif ($logged_in && $current_user_id == $produkt['sprzedawca_id']): ?>
                        <a href="profil.php?id=<?= $produkt['sprzedawca_id'] ?>" class="btn btn-primary">
                            ⚙️ Zarządzaj produktem
                        </a>
                    <?php else: ?>
                        <a href="logowanie.php" class="btn btn-primary">
                            🔑 Zaloguj się, aby napisać
                        </a>
                        <a href="profil.php?id=<?= $produkt['sprzedawca_id'] ?>" class="btn btn-secondary">
                            👤 Zobacz profil
                        </a>
                    <?php endif; ?>
                </div>

                <div class="additional-info">
                    <h4>⚠️ Bezpieczeństwo transakcji</h4>
                    <p>Pamiętaj o weryfikacji produktu przed zakupem. Zawsze spotykaj się w bezpiecznym, publicznym miejscu. Nie przesyłaj pieniędzy z góry bez zobaczenia produktu.</p>
                </div>
            </div>
        </div>
    </div>
</div>

</body>
</html>
