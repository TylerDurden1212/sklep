<?php
session_start();

$host = "localhost";
$user = "root";
$pass = "";
$dbname = "sklep";

$conn = new mysqli($host, $user, $pass, $dbname);
if ($conn->connect_error) {
    die("Błąd połączenia: " . $conn->connect_error);
}
$conn->set_charset("utf8mb4");

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
$conn->close();
?>
<!doctype html>
<html lang="pl">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= htmlspecialchars($produkt['nazwa']) ?> - Sklep</title>
<style>
* { margin: 0; padding: 0; box-sizing: border-box; }
body {
    font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    min-height: 100vh;
    padding: 20px;
}

.container {
    max-width: 1200px;
    margin: 0 auto;
}

.back-link {
    display: inline-block;
    background: white;
    padding: 12px 24px;
    border-radius: 25px;
    text-decoration: none;
    color: #667eea;
    font-weight: bold;
    margin-bottom: 20px;
    transition: 0.3s;
    box-shadow: 0 3px 10px rgba(0,0,0,0.1);
}

.back-link:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 20px rgba(255,255,255,0.3);
}

.product-container {
    background: white;
    border-radius: 25px;
    overflow: hidden;
    box-shadow: 0 20px 60px rgba(0,0,0,0.3);
    display: grid;
    grid-template-columns: 1.2fr 1fr;
    gap: 0;
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
    color: #667eea;
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
    color: #667eea;
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
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
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
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
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
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
}

.btn-primary:hover {
    transform: translateY(-3px);
    box-shadow: 0 10px 25px rgba(102, 126, 234, 0.4);
}

.btn-secondary {
    background: white;
    color: #667eea;
    border: 2px solid #667eea;
}

.btn-secondary:hover {
    background: #f0f0ff;
}

.additional-info {
    background: #fff9e6;
    padding: 20px;
    border-radius: 15px;
    margin-top: 20px;
    border-left: 4px solid #ffc107;
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

<div class="container">
    <a href="index.php" class="back-link">← Powrót do sklepu</a>

    <div class="product-container">
        <div class="product-image-section">
            <?php if (!empty($produkt['zdjecie'])): ?>
                <img src="<?= htmlspecialchars($produkt['zdjecie']) ?>" alt="<?= htmlspecialchars($produkt['nazwa']) ?>" class="product-image" onerror="this.src='https://via.placeholder.com/800x600?text=Brak+zdjęcia'">
            <?php else: ?>
                <img src="https://via.placeholder.com/800x600?text=Brak+zdjęcia" alt="Brak zdjęcia" class="product-image">
            <?php endif; ?>
            
            <?php if (!empty($produkt['kategoria'])): ?>
                <div class="image-badge">
                    <?php
                    $icons = [
                        'elektronika' => '📱',
                        'odziez' => '👕',
                        'dom' => '🏠',
                        'sport' => '⚽',
                        'inne' => '📦'
                    ];
                    echo $icons[$produkt['kategoria']] ?? '📦';
                    ?> <?= ucfirst($produkt['kategoria']) ?>
                </div>
            <?php endif; ?>
        </div>

        <div class="product-details">
            <span class="product-category">
                <?php
                $categoryNames = [
                    'elektronika' => '📱 Elektronika',
                    'odziez' => '👕 Odzież',
                    'dom' => '🏠 Dom i Ogród',
                    'sport' => '⚽ Sport',
                    'inne' => '📦 Inne'
                ];
                echo $categoryNames[$produkt['kategoria']] ?? '📦 Inne';
                ?>
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
                    <?php if (!empty($_SESSION['user_id']) && $_SESSION['user_id'] != $produkt['sprzedawca_id']): ?>
                        <a href="czat.php?produkt_id=<?= $produkt['id'] ?>&user_id=<?= $produkt['sprzedawca_id'] ?>" class="btn btn-primary">
                            💬 Napisz do sprzedawcy
                        </a>
                        <a href="profil.php?id=<?= $produkt['sprzedawca_id'] ?>" class="btn btn-secondary">
                            👤 Zobacz profil
                        </a>
                    <?php elseif ($_SESSION['user_id'] == $produkt['sprzedawca_id']): ?>
                        <a href="profil.php?id=<?= $produkt['sprzedawca_id'] ?>" class="btn btn-primary">
                            ⚙️ Zarządzaj produktem
                        </a>
                    <?php else: ?>
                        <a href="login.php" class="btn btn-primary">
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