<?php
session_start();

$host = "192.168.1.202";
$user = "sklepuser";
$pass = "twojehaslo";
$dbname = "sklep";

$conn = new mysqli($host, $user, $pass, $dbname);
if ($conn->connect_error) {
    die("Błąd połączenia: " . $conn->connect_error);
}
$conn->set_charset("utf8mb4");

// Obsługa wyszukiwania
$search = $_GET['search'] ?? '';
$category = $_GET['category'] ?? '';
$sort = $_GET['sort'] ?? 'date_desc';

$query = "SELECT p.*, l.username AS sprzedawca, l.id AS seller_id, l.profile_picture 
          FROM produkty p
          LEFT JOIN logi l ON p.id_sprzedawcy = l.id
          WHERE p.is_sold = 0"; // DODAJ TĘ LINIĘ - pokaż tylko niesprzedane

if ($search !== '') {
    $search_safe = $conn->real_escape_string($search);
    $query .= " AND (p.nazwa LIKE '%$search_safe%' OR p.opis LIKE '%$search_safe%')";
}

if ($category !== '') {
    $cat_safe = $conn->real_escape_string($category);
    $query .= " AND p.kategoria = '$cat_safe'";
}
// Sortowanie
switch($sort) {
    case 'price_asc': $query .= " ORDER BY p.cena ASC"; break;
    case 'price_desc': $query .= " ORDER BY p.cena DESC"; break;
    case 'name_asc': $query .= " ORDER BY p.nazwa ASC"; break;
    default: $query .= " ORDER BY p.data_dodania DESC";
}

$result = $conn->query($query);

// Pobieranie liczby nieprzeczytanych wiadomości
$unread_count = 0;
if (!empty($_SESSION['user_id'])) {
    $uid = $_SESSION['user_id'];
    $unread_res = $conn->query("SELECT COUNT(*) as cnt FROM chats WHERE user_to=$uid AND read_status=0");
    $unread_count = $unread_res->fetch_assoc()['cnt'];
}

// Statystyki
$total_products = $result->num_rows;
$total_users = $conn->query("SELECT COUNT(*) as cnt FROM logi")->fetch_assoc()['cnt'];

// Funkcja do pobierania pierwszego zdjęcia
function getFirstImage($zdjecie) {
    if (empty($zdjecie)) {
        return null;
    }
    
    // Sprawdź czy to JSON (nowy format)
    $decoded = json_decode($zdjecie, true);
    if (is_array($decoded) && !empty($decoded)) {
        return $decoded[0]; // Pierwsze zdjęcie z tablicy
    }
    
    // Stary format - pojedyncze zdjęcie
    return $zdjecie;
}
?>
<!doctype html>
<html lang="pl">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>GórkaSklep.pl - Szkolny Sklep Internetowy</title>
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

.menu-item.messages {
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

/* Stats Banner */
.stats-banner {
    max-width: 1400px;
    margin: 30px auto 20px;
    padding: 0 20px;
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 20px;
}

.stat-card {
    background: white;
    padding: 25px;
    border-radius: 20px;
    box-shadow: 0 5px 20px rgba(0,0,0,0.1);
    display: flex;
    align-items: center;
    gap: 20px;
    transition: 0.3s;
}

.stat-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 10px 30px rgba(0,0,0,0.15);
}

.stat-icon {
    font-size: 48px;
    width: 70px;
    height: 70px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 50%;
    background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
}

.stat-info h3 {
    font-size: 32px;
    background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
}

.stat-info p {
    color: #666;
    font-size: 14px;
    margin-top: 5px;
}

/* Filters */
.filters {
    max-width: 1400px;
    margin: 20px auto;
    padding: 0 20px;
    display: flex;
    gap: 15px;
    flex-wrap: wrap;
    align-items: center;
}

.filter-label {
    color: white;
    font-weight: 600;
    font-size: 15px;
}

.filters select {
    padding: 12px 20px;
    border: none;
    border-radius: 25px;
    background: white;
    box-shadow: 0 3px 15px rgba(0,0,0,0.1);
    cursor: pointer;
    font-weight: 600;
    color: var(--dark);
    transition: 0.3s;
}

.filters select:hover {
    transform: translateY(-2px);
    box-shadow: 0 5px 20px rgba(0,0,0,0.15);
}

/* Products Grid */
.container {
    max-width: 1400px;
    margin: 0 auto;
    padding: 20px;
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
    gap: 30px;
}

.card {
    background: white;
    border-radius: 20px;
    overflow: hidden;
    box-shadow: 0 8px 25px rgba(0,0,0,0.15);
    transition: 0.3s;
    cursor: pointer;
    display: flex;
    flex-direction: column;
}

.card:hover {
    transform: translateY(-10px);
    box-shadow: 0 20px 40px rgba(0,0,0,0.25);
}

.card-image-wrapper {
    position: relative;
    overflow: hidden;
    height: 280px;
}

.card-image {
    width: 100%;
    height: 100%;
    object-fit: cover;
    transition: 0.5s;
}

.card:hover .card-image {
    transform: scale(1.1);
}

.card-badge {
    position: absolute;
    top: 15px;
    right: 15px;
    background: rgba(255,255,255,0.95);
    padding: 8px 15px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: bold;
    color: var(--primary);
    box-shadow: 0 3px 10px rgba(0,0,0,0.2);
}

.card-content {
    padding: 20px;
    flex: 1;
    display: flex;
    flex-direction: column;
}

.card-title {
    font-size: 20px;
    color: var(--dark);
    margin-bottom: 10px;
    font-weight: 700;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
}

.card-description {
    font-size: 14px;
    color: #666;
    line-height: 1.6;
    flex: 1;
    margin-bottom: 15px;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
}

.card-footer {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding-top: 15px;
    border-top: 2px solid var(--light);
}

.price {
    font-size: 26px;
    font-weight: bold;
    background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
}

.seller-mini {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 8px 12px;
    background: var(--light);
    border-radius: 20px;
    transition: 0.3s;
}

.seller-mini:hover {
    background: #ffe0cc;
}

.seller-avatar-mini {
    width: 35px;
    height: 35px;
    border-radius: 50%;
    object-fit: cover;
    border: 2px solid white;
}

.seller-avatar-placeholder-mini {
    width: 35px;
    height: 35px;
    border-radius: 50%;
    background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
    color: white;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: bold;
    font-size: 14px;
    border: 2px solid white;
}

.seller-name-mini {
    font-size: 13px;
    color: #666;
    font-weight: 600;
}

.empty-state {
    grid-column: 1/-1;
    text-align: center;
    padding: 100px 20px;
    color: white;
}

.empty-state-icon {
    font-size: 100px;
    margin-bottom: 20px;
    animation: float 3s ease-in-out infinite;
}

@keyframes float {
    0%, 100% { transform: translateY(0); }
    50% { transform: translateY(-20px); }
}

.empty-state h2 {
    font-size: 36px;
    margin-bottom: 15px;
}

.empty-state p {
    font-size: 18px;
    opacity: 0.9;
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
    
    .container {
        grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
        gap: 20px;
    }
    
    .stats-banner {
        grid-template-columns: 1fr;
    }
    
    .logo-icon {
        font-size: 36px;
    }
    
    .logo-main {
        font-size: 22px;
    }
}
</style>
</head>
<body>

<div class="header">
    <div class="header-content">
        <div class="logo-section" onclick="window.location='index.php'">
            <div class="logo-icon"><img src = "./images/logo.png" height = "50px" width = "50px"></div>
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
                <a href="wiadomosci.php" class="menu-item messages">
                    💬 Wiadomości
                    <?php if ($unread_count > 0): ?>
                        <span class="badge"><?= $unread_count ?></span>
                    <?php endif; ?>
                </a>
                <a href="profil.php" class="menu-item">👤 Profil</a>
                <a href="dodaj_produkt.php" class="btn-add">+ Dodaj</a>
                <a href="logout.php" class="menu-item">Wyloguj</a>
            <?php else: ?>
                <a href="logowanie.php" class="btn-add">🔑 Zaloguj się</a>
            <?php endif; ?>
        </div>
    </div>
</div>

<div class="stats-banner">
    <div class="stat-card">
        <div class="stat-icon">📦</div>
        <div class="stat-info">
            <h3><?= $total_products ?></h3>
            <p>Dostępnych produktów</p>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon">👥</div>
        <div class="stat-info">
            <h3><?= $total_users ?></h3>
            <p>Aktywnych użytkowników</p>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon">🏫</div>
        <div class="stat-info">
            <h3>100%</h3>
            <p>Szkolna społeczność</p>
        </div>
    </div>
</div>

<form class="filters" method="get">
    <span class="filter-label">Filtruj:</span>
    
    <select name="category" onchange="this.form.submit()">
        <option value="">📂 Wszystkie kategorie</option>
        <option value="elektronika" <?= $category === 'elektronika' ? 'selected' : '' ?>>📱 Elektronika</option>
        <option value="odziez" <?= $category === 'odziez' ? 'selected' : '' ?>>👕 Odzież</option>
        <option value="dom" <?= $category === 'dom' ? 'selected' : '' ?>>🏠 Dom i Ogród</option>
        <option value="sport" <?= $category === 'sport' ? 'selected' : '' ?>>⚽ Sport</option>
        <option value="inne" <?= $category === 'inne' ? 'selected' : '' ?>>📦 Inne</option>
    </select>
    
    <select name="sort" onchange="this.form.submit()">
        <option value="date_desc" <?= $sort === 'date_desc' ? 'selected' : '' ?>>🕒 Najnowsze</option>
        <option value="price_asc" <?= $sort === 'price_asc' ? 'selected' : '' ?>>💰 Cena: rosnąco</option>
        <option value="price_desc" <?= $sort === 'price_desc' ? 'selected' : '' ?>>💎 Cena: malejąco</option>
        <option value="name_asc" <?= $sort === 'name_asc' ? 'selected' : '' ?>>🔤 Nazwa: A-Z</option>
    </select>
    
    <input type="hidden" name="search" value="<?= htmlspecialchars($search) ?>">
</form>

<div class="container">
    <?php if ($result->num_rows > 0): ?>
        <?php while ($row = $result->fetch_assoc()): 
            $firstImage = getFirstImage($row['zdjecie']);
        ?>
            <div class="card" onclick="window.location='produkt.php?id=<?= $row['id'] ?>'">
                <div class="card-image-wrapper">
                    <?php if ($firstImage): ?>
                        <img src="<?= htmlspecialchars($firstImage) ?>" alt="<?= htmlspecialchars($row['nazwa']) ?>" class="card-image" onerror="this.src='https://via.placeholder.com/300x280/ff8c42/ffffff?text=Brak+zdjęcia'">
                    <?php else: ?>
                        <img src="https://via.placeholder.com/300x280/ff8c42/ffffff?text=Brak+zdjęcia" alt="" class="card-image">
                    <?php endif; ?>
                    
                    <?php if (!empty($row['kategoria'])): ?>
                        <div class="card-badge">
                            <?php
                            $icons = [
                                'elektronika' => '📱',
                                'odziez' => '👕',
                                'dom' => '🏠',
                                'sport' => '⚽',
                                'inne' => '📦'
                            ];
                            echo $icons[$row['kategoria']] ?? '📦';
                            ?>
                        </div>
                    <?php endif; ?>
                </div>
                
                <div class="card-content">
                    <h3 class="card-title"><?= htmlspecialchars($row['nazwa']) ?></h3>
                    <div class="card-description"><?= htmlspecialchars($row['opis']) ?></div>
                    
                    <div class="card-footer">
                        <div class="price"><?= number_format($row['cena'], 2) ?> zł</div>
                        
                        <div class="seller-mini" onclick="event.stopPropagation(); window.location='profil.php?id=<?= $row['seller_id'] ?>'">
                            <?php if (!empty($row['profile_picture'])): ?>
                                <img src="<?= htmlspecialchars($row['profile_picture']) ?>" alt="" class="seller-avatar-mini" onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                                <div class="seller-avatar-placeholder-mini" style="display:none;">
                                    <?= strtoupper(substr($row['sprzedawca'] ?? 'U', 0, 1)) ?>
                                </div>
                            <?php else: ?>
                                <div class="seller-avatar-placeholder-mini">
                                    <?= strtoupper(substr($row['sprzedawca'] ?? 'U', 0, 1)) ?>
                                </div>
                            <?php endif; ?>
                            <div class="seller-name-mini"><?= htmlspecialchars($row['sprzedawca'] ?? 'Nieznany') ?></div>
                        </div>
                    </div>
                </div>
            </div>
        <?php endwhile; ?>
    <?php else: ?>
        <div class="empty-state">
            <div class="empty-state-icon">🔍</div>
            <h2>Brak wyników</h2>
            <p>Nie znaleziono produktów spełniających kryteria wyszukiwania</p>
        </div>
    <?php endif; ?>
</div>

<script>
// Auto-refresh licznika wiadomości
<?php if (!empty($_SESSION['user_id'])): ?>
setInterval(async () => {
    try {
        const resp = await fetch('check_messages.php', {cache: 'no-store'});
        const data = await resp.json();
        const badge = document.querySelector('.messages .badge');
        
        if (data.count > 0) {
            if (!badge) {
                const newBadge = document.createElement('span');
                newBadge.className = 'badge';
                newBadge.textContent = data.count;
                document.querySelector('.messages').appendChild(newBadge);
            } else {
                badge.textContent = data.count;
            }
        } else if (badge) {
            badge.remove();
        }
    } catch(e) {
        console.error('Błąd sprawdzania wiadomości:', e);
    }
}, 10000);
<?php endif; ?>
</script>
<script>
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