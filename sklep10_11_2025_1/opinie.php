<?php
session_start();
require_once 'config.php';

$conn = getDBConnection();

$user_id = intval($_GET['user_id'] ?? 0);

if ($user_id <= 0) {
    header("Location: index.php");
    exit;
}

// Pobierz dane użytkownika
$stmt = $conn->prepare("SELECT username, rating_avg, rating_count FROM logi WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$user) {
    header("Location: index.php");
    exit;
}

// Pobierz wszystkie opinie
$stmt = $conn->prepare("
    SELECT r.*, l.username, l.profile_picture, p.nazwa AS produkt_nazwa
    FROM ratings r
    LEFT JOIN logi l ON r.buyer_id = l.id
    LEFT JOIN produkty p ON r.produkt_id = p.id
    WHERE r.seller_id = ?
    ORDER BY r.created_at DESC
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$reviews = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Statystyki gwiazdek
$rating_distribution = [1 => 0, 2 => 0, 3 => 0, 4 => 0, 5 => 0];
foreach ($reviews as $review) {
    $rating_distribution[$review['rating']]++;
}

$unread_count = 0;
if (!empty($_SESSION['user_id'])) {
    $unread_count = getUnreadCount($_SESSION['user_id'], $conn);
}

$search = '';
$conn->close();
?>
<!doctype html>
<html lang="pl">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>⭐ Opinie - <?= htmlspecialchars($user['username']) ?> | GórkaSklep.pl</title>
<link rel="icon" href="./images/logo_strona.png">
<style>
* { margin: 0; padding: 0; box-sizing: border-box; }

:root {
    --primary: #ff8c42;
    --secondary: #ff6b35;
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
    transition: 0.3s;
    position: relative;
}

.menu-item:hover {
    background: var(--primary);
    color: white;
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
    max-width: 1200px;
    margin: 30px auto;
    padding: 0 20px;
}

.page-header {
    background: white;
    padding: 40px;
    border-radius: 25px;
    box-shadow: 0 20px 60px rgba(0,0,0,0.3);
    margin-bottom: 30px;
    animation: slideUp 0.5s;
}

@keyframes slideUp {
    from { opacity: 0; transform: translateY(30px); }
    to { opacity: 1; transform: translateY(0); }
}

.page-header h1 {
    font-size: 36px;
    color: #333;
    margin-bottom: 10px;
    display: flex;
    align-items: center;
    gap: 15px;
}

.page-header .subtitle {
    color: #666;
    font-size: 16px;
    margin-bottom: 30px;
}

.back-link {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    color: var(--primary);
    text-decoration: none;
    font-weight: 600;
    margin-bottom: 20px;
    transition: 0.3s;
}

.back-link:hover {
    color: var(--secondary);
    transform: translateX(-5px);
}

.rating-overview {
    display: grid;
    grid-template-columns: 300px 1fr;
    gap: 40px;
    margin-bottom: 40px;
}

.rating-summary-box {
    background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
    padding: 40px;
    border-radius: 20px;
    color: white;
    text-align: center;
}

.big-rating {
    font-size: 72px;
    font-weight: 900;
    margin-bottom: 10px;
}

.rating-stars-big {
    font-size: 32px;
    margin-bottom: 10px;
}

.rating-stats {
    display: flex;
    flex-direction: column;
    gap: 15px;
}

.stat-row {
    display: flex;
    align-items: center;
    gap: 15px;
}

.stat-label {
    display: flex;
    align-items: center;
    gap: 8px;
    min-width: 100px;
    font-weight: 600;
}

.stat-bar {
    flex: 1;
    height: 20px;
    background: #e0e0e0;
    border-radius: 10px;
    overflow: hidden;
}

.stat-fill {
    height: 100%;
    background: linear-gradient(90deg, #fbbf24 0%, #f59e0b 100%);
    transition: width 0.5s ease-out;
}

.stat-count {
    min-width: 40px;
    text-align: right;
    font-weight: 600;
    color: #666;
}

.reviews-section {
    background: white;
    padding: 40px;
    border-radius: 25px;
    box-shadow: 0 10px 40px rgba(0,0,0,0.2);
}

.reviews-section h2 {
    font-size: 28px;
    margin-bottom: 30px;
    color: #333;
}

.review-card {
    background: var(--light);
    padding: 25px;
    border-radius: 20px;
    margin-bottom: 20px;
    border-left: 5px solid var(--primary);
    transition: 0.3s;
}

.review-card:hover {
    transform: translateX(5px);
    box-shadow: 0 5px 20px rgba(255, 140, 66, 0.2);
}

.review-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 15px;
    flex-wrap: wrap;
    gap: 15px;
}

.reviewer-info {
    display: flex;
    align-items: center;
    gap: 15px;
}

.reviewer-avatar {
    width: 50px;
    height: 50px;
    border-radius: 50%;
    background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
    color: white;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: bold;
    font-size: 20px;
}

.reviewer-details h3 {
    font-size: 18px;
    color: #333;
    margin-bottom: 5px;
}

.reviewer-details .product-name {
    font-size: 13px;
    color: #999;
}

.review-rating {
    display: flex;
    flex-direction: column;
    align-items: flex-end;
    gap: 5px;
}

.review-stars {
    font-size: 20px;
}

.star {
    color: #ddd;
}

.star.filled {
    color: #fbbf24;
}

.review-date {
    font-size: 12px;
    color: #999;
}

.review-comment {
    color: #333;
    line-height: 1.8;
    font-size: 15px;
    padding: 15px;
    background: white;
    border-radius: 10px;
    margin-top: 10px;
}

.empty-state {
    text-align: center;
    padding: 60px 20px;
    color: #666;
}

.empty-state-icon {
    font-size: 80px;
    margin-bottom: 20px;
}

.empty-state h3 {
    font-size: 24px;
    color: #333;
    margin-bottom: 10px;
}

@media (max-width: 968px) {
    .header-content {
        grid-template-columns: 1fr;
    }
    
    .rating-overview {
        grid-template-columns: 1fr;
    }
    
    .page-header {
        padding: 25px;
    }
    
    .reviews-section {
        padding: 25px;
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
            <?php if (!empty($_SESSION['user_id'])): ?>
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
    <div class="page-header">
        <a href="profil.php?id=<?= $user_id ?>" class="back-link">← Powrót do profilu</a>
        
        <h1>
            ⭐ Opinie o użytkowniku
            <span style="color: var(--primary);"><?= htmlspecialchars($user['username']) ?></span>
        </h1>
        <div class="subtitle">
            Wszystkie opinie wystawione przez kupujących (<?= count($reviews) ?>)
        </div>

        <div class="rating-overview">
            <div class="rating-summary-box">
                <div class="big-rating"><?= number_format($user['rating_avg'] ?? 0, 1) ?></div>
                <div class="rating-stars-big">
                    <?php 
                    $avg = floatval($user['rating_avg'] ?? 0);
                    for ($i = 1; $i <= 5; $i++): 
                        if ($i <= floor($avg)): ?>
                            <span class="star filled">⭐</span>
                        <?php elseif ($i <= ceil($avg) && $avg - floor($avg) >= 0.5): ?>
                            <span class="star filled" style="opacity: 0.6;">⭐</span>
                        <?php else: ?>
                            <span style="color: rgba(255,255,255,0.3);">☆</span>
                        <?php endif;
                    endfor; ?>
                </div>
                <div style="font-size: 18px; opacity: 0.9;">
                    <?= $user['rating_count'] ?> <?= $user['rating_count'] == 1 ? 'ocena' : 'ocen' ?>
                </div>
            </div>

            <div class="rating-stats">
                <h3 style="margin-bottom: 15px; color: #333;">Rozkład ocen</h3>
                <?php 
                $total = max(array_sum($rating_distribution), 1);
                for ($i = 5; $i >= 1; $i--): 
                    $count = $rating_distribution[$i];
                    $percentage = ($count / $total) * 100;
                ?>
                    <div class="stat-row">
                        <div class="stat-label">
                            <?php for ($j = 0; $j < $i; $j++): ?>
                                <span style="color: #fbbf24;">⭐</span>
                            <?php endfor; ?>
                        </div>
                        <div class="stat-bar">
                            <div class="stat-fill" style="width: <?= $percentage ?>%"></div>
                        </div>
                        <div class="stat-count"><?= $count ?></div>
                    </div>
                <?php endfor; ?>
            </div>
        </div>
    </div>

    <div class="reviews-section">
        <h2>📝 Wszystkie opinie (<?= count($reviews) ?>)</h2>

        <?php if (!empty($reviews)): ?>
            <?php foreach ($reviews as $review): ?>
                <div class="review-card">
                    <div class="review-header">
                        <div class="reviewer-info">
                            <div class="reviewer-avatar">
                                <?= strtoupper(substr($review['username'] ?? 'U', 0, 1)) ?>
                            </div>
                            <div class="reviewer-details">
                                <h3><?= htmlspecialchars($review['username'] ?? 'Anonimowy użytkownik') ?></h3>
                                <?php if (!empty($review['produkt_nazwa'])): ?>
                                    <div class="product-name">
                                        📦 Produkt: <?= htmlspecialchars($review['produkt_nazwa']) ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>

                        <div class="review-rating">
                            <div class="review-stars">
                                <?php for ($i = 1; $i <= 5; $i++): ?>
                                    <span class="star <?= $i <= $review['rating'] ? 'filled' : '' ?>">
                                        <?= $i <= $review['rating'] ? '⭐' : '☆' ?>
                                    </span>
                                <?php endfor; ?>
                            </div>
                            <div class="review-date">
                                <?= date('d.m.Y H:i', strtotime($review['created_at'])) ?>
                            </div>
                        </div>
                    </div>

                    <?php if (!empty($review['comment'])): ?>
                        <div class="review-comment">
                            <?= nl2br(htmlspecialchars($review['comment'])) ?>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="empty-state">
                <div class="empty-state-icon">⭐</div>
                <h3>Brak opinii</h3>
                <p>Ten użytkownik nie ma jeszcze żadnych opinii</p>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
// Animacja wypełniania pasków
document.addEventListener('DOMContentLoaded', function() {
    const statFills = document.querySelectorAll('.stat-fill');
    statFills.forEach(fill => {
        const width = fill.style.width;
        fill.style.width = '0%';
        setTimeout(() => {
            fill.style.width = width;
        }, 100);
    });
});
</script>

</body>
</html>