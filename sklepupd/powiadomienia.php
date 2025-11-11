<!doctype html>
<html lang="pl">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>🔔 Powiadomienia - GórkaSklep.pl</title>
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
    max-width: 1000px;
    margin: 30px auto;
    padding: 0 20px;
}

.page-header {
    background: white;
    padding: 30px;
    border-radius: 20px;
    box-shadow: 0 8px 25px rgba(0,0,0,0.15);
    margin-bottom: 30px;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.page-header h1 {
    font-size: 32px;
    background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
}

.mark-read-btn {
    background: var(--primary);
    color: white;
    padding: 10px 20px;
    border-radius: 20px;
    border: none;
    cursor: pointer;
    font-weight: 600;
    transition: 0.3s;
}

.mark-read-btn:hover {
    background: var(--secondary);
}

.notifications-list {
    display: flex;
    flex-direction: column;
    gap: 15px;
}

.notification {
    background: white;
    border-radius: 15px;
    padding: 20px;
    box-shadow: 0 5px 15px rgba(0,0,0,0.1);
    display: flex;
    gap: 20px;
    align-items: center;
    transition: 0.3s;
    cursor: pointer;
}

.notification:hover {
    transform: translateX(5px);
    box-shadow: 0 8px 25px rgba(0,0,0,0.15);
}

.notification.unread {
    border-left: 5px solid var(--primary);
    background: #fffbf5;
}

.notification-icon {
    font-size: 48px;
    flex-shrink: 0;
}

.notification-content {
    flex: 1;
}

.notification-text {
    color: #333;
    font-size: 15px;
    margin-bottom: 8px;
    line-height: 1.6;
}

.notification-time {
    color: #999;
    font-size: 13px;
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
}

@media (max-width: 768px) {
    .header-content {
        grid-template-columns: 1fr;
    }
    
    .page-header {
        flex-direction: column;
        gap: 15px;
        text-align: center;
    }
    
    .notification {
        flex-direction: column;
        text-align: center;
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
                <input type="text" name="search" placeholder="Czego szukasz? 🔍">
                <button type="submit" class="search-btn">Szukaj</button>
            </div>
        </form>

        <div class="user-menu">
            <a href="index.php" class="menu-item">🏠 Strona główna</a>
            <a href="wiadomosci.php" class="menu-item">💬 Wiadomości</a>
            <a href="powiadomienia.php" class="menu-item">🔔 Powiadomienia</a>
            <a href="profil.php" class="menu-item">👤 Profil</a>
            <a href="dodaj_produkt.php" class="btn-add">+ Dodaj</a>
            <a href="logout.php" class="menu-item">Wyloguj</a>
        </div>
    </div>
</div>

<div class="container">
    <div class="page-header">
        <h1>🔔 Powiadomienia</h1>
        <button class="mark-read-btn" onclick="markAllRead()">✓ Oznacz wszystkie jako przeczytane</button>
    </div>

    <div class="notifications-list" id="notificationsList">
        <!-- Notifications loaded by JavaScript -->
    </div>
</div>

<script>
async function loadNotifications() {
    try {
        const resp = await fetch('get_notifications.php');
        const data = await resp.json();
        
        const container = document.getElementById('notificationsList');
        
        if (data.notifications.length === 0) {
            container.innerHTML = `
                <div class="empty-state">
                    <div class="empty-state-icon">🔕</div>
                    <h2>Brak powiadomień</h2>
                    <p>Nie masz jeszcze żadnych powiadomień</p>
                </div>
            `;
            return;
        }
        
        container.innerHTML = data.notifications.map(n => {
            const icons = {
                'like': '❤️',
                'message': '💬',
                'purchase': '🛒',
                'sale': '🎉'
            };
            
            return `
                <div class="notification ${n.is_read == 0 ? 'unread' : ''}" 
                     onclick="handleNotification(${n.id}, ${n.related_id}, '${n.type}')">
                    <div class="notification-icon">${icons[n.type] || '📩'}</div>
                    <div class="notification-content">
                        <div class="notification-text">${n.content}</div>
                        <div class="notification-time">${formatTime(n.created_at)}</div>
                    </div>
                </div>
            `;
        }).join('');
        
    } catch(e) {
        console.error('Error loading notifications:', e);
    }
}

function formatTime(timestamp) {
    const time = new Date(timestamp);
    const now = new Date();
    const diff = Math.floor((now - time) / 1000);
    
    if (diff < 60) return "przed chwilą";
    if (diff < 3600) return Math.floor(diff/60) + " min temu";
    if (diff < 86400) return Math.floor(diff/3600) + " godz. temu";
    if (diff < 172800) return "wczoraj";
    
    return time.toLocaleDateString('pl-PL');
}

async function handleNotification(id, relatedId, type) {
    // Mark as read
    await fetch('mark_notification_read.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: `id=${id}`
    });
    
    // Redirect based on type
    if (relatedId) {
        if (type === 'message') {
            window.location = `wiadomosci.php`;
        } else {
            window.location = `produkt.php?id=${relatedId}`;
        }
    } else {
        loadNotifications();
    }
}

async function markAllRead() {
    await fetch('mark_all_notifications_read.php', {method: 'POST'});
    loadNotifications();
}

loadNotifications();
setInterval(loadNotifications, 30000); // Refresh every 30s
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
</html>