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
$conn->set_charset("utf8mb4");

$user_id = $_SESSION['user_id'];
$search = '';

// Funkcja do pobierania pierwszego zdjęcia
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

// Pobierz liczbę nieprzeczytanych wiadomości
$unread_count = 0;
$unread_res = $conn->query("SELECT COUNT(*) as cnt FROM chats WHERE user_to=$user_id AND read_status=0");
if ($unread_res) {
    $unread_count = $unread_res->fetch_assoc()['cnt'];
}

// Pobierz wszystkie unikalne konwersacje
$query = "
    SELECT DISTINCT
        c.produkt_id,
        p.nazwa AS produkt_nazwa,
        p.zdjecie AS produkt_zdjecie,
        IF(c.user_from = ?, c.user_to, c.user_from) AS other_user_id
    FROM chats c
    LEFT JOIN produkty p ON c.produkt_id = p.id
    WHERE c.user_from = ? OR c.user_to = ?
    GROUP BY c.produkt_id, other_user_id
";

$stmt = $conn->prepare($query);
if (!$stmt) {
    die("Błąd przygotowania zapytania: " . $conn->error);
}
$stmt->bind_param("iii", $user_id, $user_id, $user_id);
$stmt->execute();
$conversations = $stmt->get_result();

// Pobierz szczegóły dla każdej konwersacji
$conversations_array = [];
while ($conv = $conversations->fetch_assoc()) {
    $other_user_id = $conv['other_user_id'];
    $produkt_id = $conv['produkt_id'];
    
    // Pobierz dane drugiego użytkownika
    $user_stmt = $conn->prepare("SELECT username, profile_picture FROM logi WHERE id = ?");
    $user_stmt->bind_param("i", $other_user_id);
    $user_stmt->execute();
    $user_result = $user_stmt->get_result();
    $user_data = $user_result->fetch_assoc();
    $conv['other_username'] = $user_data['username'] ?? 'Nieznany użytkownik';
    $conv['other_profile_picture'] = $user_data['profile_picture'] ?? '';
    $user_stmt->close();
    
    // Pobierz ostatnią wiadomość (pomijając systemowe JSON)
    $msg_stmt = $conn->prepare("
        SELECT message, created_at, is_system
        FROM chats 
        WHERE produkt_id = ? 
        AND ((user_from = ? AND user_to = ?) OR (user_from = ? AND user_to = ?))
        ORDER BY created_at DESC 
        LIMIT 1
    ");
    $msg_stmt->bind_param("iiiii", $produkt_id, $user_id, $other_user_id, $other_user_id, $user_id);
    $msg_stmt->execute();
    $msg_result = $msg_stmt->get_result();
    $last_msg = $msg_result->fetch_assoc();
    
    if ($last_msg) {
        // Jeśli to wiadomość systemowa (JSON), sparsuj ją dla wyświetlenia
        if ($last_msg['is_system'] == 1) {
            $msgData = json_decode($last_msg['message'], true);
            if ($msgData) {
                switch($msgData['type']) {
                    case 'price_proposal':
                        $conv['last_message'] = '💰 Propozycja ceny: ' . number_format($msgData['price'], 2) . ' zł';
                        break;
                    case 'price_accepted':
                        $conv['last_message'] = '✅ Cena zaakceptowana: ' . number_format($msgData['price'], 2) . ' zł';
                        break;
                    case 'price_rejected':
                        $conv['last_message'] = '❌ Propozycja odrzucona';
                        break;
                    default:
                        $conv['last_message'] = 'Wiadomość systemowa';
                }
            } else {
                $conv['last_message'] = 'Wiadomość systemowa';
            }
        } else {
            $conv['last_message'] = $last_msg['message'];
        }
        $conv['ostatnia_wiadomosc'] = $last_msg['created_at'];
    } else {
        $conv['last_message'] = '';
        $conv['ostatnia_wiadomosc'] = date('Y-m-d H:i:s');
    }
    $msg_stmt->close();
    
    // Policz nieprzeczytane wiadomości
    $unread_stmt = $conn->prepare("
        SELECT COUNT(*) as count 
        FROM chats 
        WHERE produkt_id = ? 
        AND user_from = ? 
        AND user_to = ? 
        AND read_status = 0
    ");
    $unread_stmt->bind_param("iii", $produkt_id, $other_user_id, $user_id);
    $unread_stmt->execute();
    $unread_result = $unread_stmt->get_result();
    $unread_data = $unread_result->fetch_assoc();
    $conv['nieprzeczytane'] = $unread_data['count'];
    $unread_stmt->close();
    
    // Pobierz pierwsze zdjęcie produktu
    $conv['first_image'] = getFirstImage($conv['produkt_zdjecie']);
    
    $conversations_array[] = $conv;
}

// Sortuj konwersacje po dacie ostatniej wiadomości
usort($conversations_array, function($a, $b) {
    return strtotime($b['ostatnia_wiadomosc']) - strtotime($a['ostatnia_wiadomosc']);
});
?>
<!doctype html>
<html lang="pl">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>💬 Wiadomości - GórkaSklep.pl</title>
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

/* Container */
.container {
    max-width: 1000px;
    margin: 30px auto;
    padding: 0 20px;
}

.page-title {
    background: white;
    padding: 30px;
    border-radius: 20px;
    box-shadow: 0 8px 25px rgba(0,0,0,0.15);
    margin-bottom: 30px;
    text-align: center;
}

.page-title h1 {
    font-size: 32px;
    background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    margin-bottom: 10px;
}

.page-title p {
    color: #666;
    font-size: 15px;
}

.conversation-list {
    display: flex;
    flex-direction: column;
    gap: 15px;
}

.conversation-card {
    background: white;
    border-radius: 20px;
    padding: 20px;
    display: flex;
    gap: 20px;
    cursor: pointer;
    transition: 0.3s;
    box-shadow: 0 5px 15px rgba(0,0,0,0.1);
    position: relative;
    align-items: center;
}

.conversation-card:hover {
    transform: translateX(5px);
    box-shadow: 0 8px 25px rgba(0,0,0,0.15);
}

.conversation-card.unread {
    border-left: 5px solid var(--primary);
}

.product-image {
    width: 90px;
    height: 90px;
    border-radius: 15px;
    object-fit: cover;
    background: #f0f0f0;
    flex-shrink: 0;
}

.conversation-main {
    flex: 1;
    min-width: 0;
}

.conversation-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 10px;
}

.conversation-title {
    display: flex;
    align-items: center;
    gap: 12px;
}

.user-avatar {
    width: 45px;
    height: 45px;
    border-radius: 50%;
    object-fit: cover;
    border: 3px solid white;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
}

.user-avatar-placeholder {
    width: 45px;
    height: 45px;
    border-radius: 50%;
    background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
    color: white;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: bold;
    font-size: 18px;
    border: 3px solid white;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
}

.conversation-info h3 {
    font-size: 18px;
    color: #333;
    margin-bottom: 3px;
}

.conversation-info .product-name {
    font-size: 13px;
    color: #999;
}

.timestamp {
    font-size: 12px;
    color: #999;
    white-space: nowrap;
}

.last-message {
    font-size: 14px;
    color: #666;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
    line-height: 1.5;
}

.unread-badge {
    position: absolute;
    top: 15px;
    right: 15px;
    background: #ef4444;
    color: white;
    border-radius: 50%;
    width: 28px;
    height: 28px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 12px;
    font-weight: bold;
    animation: pulse 2s infinite;
}

.empty-state {
    background: white;
    border-radius: 20px;
    padding: 80px 20px;
    text-align: center;
    box-shadow: 0 10px 30px rgba(0,0,0,0.1);
}

.empty-state-icon {
    font-size: 80px;
    margin-bottom: 20px;
}

.empty-state h2 {
    font-size: 28px;
    color: #333;
    margin-bottom: 15px;
}

.empty-state p {
    color: #666;
    font-size: 16px;
    line-height: 1.6;
}

@media (max-width: 768px) {
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
    
    .conversation-card {
        padding: 15px;
        flex-direction: column;
        align-items: flex-start;
    }
    
    .product-image {
        width: 100%;
        height: 150px;
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
            <a href="index.php" class="menu-item">🏠 Strona główna</a>
            <a href="wiadomosci.php" class="menu-item messages">
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
    <div class="page-title">
        <h1>💬 Twoje wiadomości</h1>
        <p>Zarządzaj swoimi rozmowami z innymi użytkownikami</p>
    </div>

    <div class="conversation-list">
        <?php if (count($conversations_array) > 0): ?>
            <?php foreach ($conversations_array as $conv): ?>
                <div class="conversation-card <?= $conv['nieprzeczytane'] > 0 ? 'unread' : '' ?>" 
                     onclick="window.location='czat.php?produkt_id=<?= $conv['produkt_id'] ?>&user_id=<?= $conv['other_user_id'] ?>'">
                    
                    <?php if ($conv['nieprzeczytane'] > 0): ?>
                        <div class="unread-badge"><?= $conv['nieprzeczytane'] ?></div>
                    <?php endif; ?>
                    
                    <?php if ($conv['first_image']): ?>
                        <img src="<?= htmlspecialchars($conv['first_image']) ?>" 
                             class="product-image" 
                             alt="<?= htmlspecialchars($conv['produkt_nazwa']) ?>"
                             onerror="this.src='https://via.placeholder.com/90?text=?'">
                    <?php else: ?>
                        <img src="https://via.placeholder.com/90?text=?" class="product-image" alt="">
                    <?php endif; ?>
                    
                    <div class="conversation-main">
                        <div class="conversation-header">
                            <div class="conversation-title">
                                <?php if (!empty($conv['other_profile_picture'])): ?>
                                    <img src="<?= htmlspecialchars($conv['other_profile_picture']) ?>" 
                                         class="user-avatar" 
                                         alt="<?= htmlspecialchars($conv['other_username']) ?>"
                                         onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                                    <div class="user-avatar-placeholder" style="display:none;">
                                        <?= strtoupper(substr($conv['other_username'] ?? 'U', 0, 1)) ?>
                                    </div>
                                <?php else: ?>
                                    <div class="user-avatar-placeholder">
                                        <?= strtoupper(substr($conv['other_username'] ?? 'U', 0, 1)) ?>
                                    </div>
                                <?php endif; ?>
                                
                                <div class="conversation-info">
                                    <h3><?= htmlspecialchars($conv['other_username']) ?></h3>
                                    <div class="product-name">📦 <?= htmlspecialchars($conv['produkt_nazwa']) ?></div>
                                </div>
                            </div>
                            
                            <div class="timestamp">
                                <?php
                                $time = strtotime($conv['ostatnia_wiadomosc']);
                                $diff = time() - $time;
                                if ($diff < 60) echo "przed chwilą";
                                elseif ($diff < 3600) echo floor($diff/60) . " min temu";
                                elseif ($diff < 86400) echo floor($diff/3600) . " godz. temu";
                                elseif ($diff < 172800) echo "wczoraj";
                                else echo date('d.m.Y', $time);
                                ?>
                            </div>
                        </div>
                        
                        <?php if (!empty($conv['last_message'])): ?>
                            <div class="last-message">
                                <?= htmlspecialchars($conv['last_message']) ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="empty-state">
                <div class="empty-state-icon">🔭</div>
                <h2>Brak wiadomości</h2>
                <p>Rozpocznij rozmowę ze sprzedawcą, klikając "Napisz do sprzedawcy" na stronie produktu</p>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
// Auto-refresh co 30 sekund
setInterval(() => {
    location.reload();
}, 30000);
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