<?php
session_start();
if (empty($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$host = "localhost";
$user = "root";
$pass = "";
$dbname = "sklep";

$conn = new mysqli($host, $user, $pass, $dbname);
$conn->set_charset("utf8mb4");

$from_id = $_SESSION['user_id'];
$produkt_id = intval($_GET['produkt_id'] ?? 0);
$other_user_id = intval($_GET['user_id'] ?? 0);

// Jeśli brak produkt_id, spróbuj znaleźć istniejącą konwersację
if (!$produkt_id && $other_user_id) {
    $stmt = $conn->prepare("
        SELECT DISTINCT produkt_id 
        FROM chats 
        WHERE (user_from = ? AND user_to = ?) OR (user_from = ? AND user_to = ?)
        ORDER BY created_at DESC 
        LIMIT 1
    ");
    $stmt->bind_param("iiii", $from_id, $other_user_id, $other_user_id, $from_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        $produkt_id = $row['produkt_id'];
    }
    $stmt->close();
}

if (!$produkt_id) {
    echo '<!DOCTYPE html>
    <html lang="pl">
    <head>
        <meta charset="utf-8">
        <title>Błąd</title>
        <style>
            body {
                font-family: Arial;
                display: flex;
                justify-content: center;
                align-items: center;
                height: 100vh;
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                color: white;
                text-align: center;
                padding: 20px;
            }
            .error-box {
                background: white;
                color: #333;
                padding: 40px;
                border-radius: 20px;
                box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            }
            .error-box h1 { margin-bottom: 20px; color: #ef4444; }
            .error-box a {
                display: inline-block;
                margin-top: 20px;
                padding: 12px 24px;
                background: #667eea;
                color: white;
                text-decoration: none;
                border-radius: 25px;
                font-weight: bold;
            }
        </style>
    </head>
    <body>
        <div class="error-box">
            <h1>❌ Błąd</h1>
            <p>Nie można otworzyć czatu. Brak informacji o produkcie.</p>
            <p>Rozpocznij rozmowę ze strony produktu.</p>
            <a href="index.php">← Powrót do sklepu</a>
            <a href="wiadomosci.php">💬 Moje wiadomości</a>
        </div>
    </body>
    </html>';
    exit;
}

// Pobierz informacje o produkcie i sprzedawcy
$stmt = $conn->prepare("
    SELECT p.*, l.username AS sprzedawca, l.id AS sprzedawca_id
    FROM produkty p
    LEFT JOIN logi l ON p.id_sprzedawcy = l.id
    WHERE p.id = ?
");
$stmt->bind_param("i", $produkt_id);
$stmt->execute();
$produkt = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$produkt) {
    die("Nie znaleziono produktu");
}

// Ustal z kim rozmawiamy
if ($other_user_id == 0) {
    // Jeśli nie podano user_id, rozmawiamy ze sprzedawcą
    $to_id = $produkt['sprzedawca_id'];
} else {
    $to_id = $other_user_id;
}

// Pobierz dane rozmówcy
$stmt = $conn->prepare("SELECT username, bio FROM logi WHERE id = ?");
$stmt->bind_param("i", $to_id);
$stmt->execute();
$other_user = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$other_user) {
    die("Nie znaleziono użytkownika");
}

// Oznacz wiadomości jako przeczytane
$stmt = $conn->prepare("UPDATE chats SET read_status=1 WHERE produkt_id=? AND user_to=? AND user_from=?");
$stmt->bind_param("iii", $produkt_id, $from_id, $to_id);
$stmt->execute();
$stmt->close();

// Wysyłanie wiadomości
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['message'])) {
    $msg = trim($_POST['message']);
    if ($msg !== '' && strlen($msg) <= 1000) {
        $stmt = $conn->prepare("INSERT INTO chats (user_from, user_to, produkt_id, message, read_status) VALUES (?, ?, ?, ?, 0)");
        $stmt->bind_param("iiis", $from_id, $to_id, $produkt_id, $msg);
        $stmt->execute();
        $stmt->close();
    }
    if (!empty($_SERVER['HTTP_X_REQUESTED_WITH'])) {
        header('Content-Type: application/json');
        echo json_encode(['status' => 'ok', 'time' => date('Y-m-d H:i:s')]);
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
    $stmt->bind_param("iiiii", $produkt_id, $from_id, $to_id, $to_id, $from_id);
    $stmt->execute();
    $messages = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    
    // Oznacz jako przeczytane
    $stmt = $conn->prepare("UPDATE chats SET read_status=1 WHERE produkt_id=? AND user_to=? AND user_from=?");
    $stmt->bind_param("iii", $produkt_id, $from_id, $to_id);
    $stmt->execute();
    $stmt->close();
    
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($messages, JSON_UNESCAPED_UNICODE);
    exit;
}

// Sprawdź czy użytkownik jest online (ostatnia aktywność < 5 min)
$stmt = $conn->prepare("SELECT last_activity FROM logi WHERE id = ?");
$stmt->bind_param("i", $to_id);
$stmt->execute();
$activity = $stmt->get_result()->fetch_assoc();
$stmt->close();

$is_online = false;
if ($activity && isset($activity['last_activity'])) {
    $last_time = strtotime($activity['last_activity']);
    $is_online = (time() - $last_time) < 300; // 5 minut
}

// Aktualizuj swoją aktywność
$stmt = $conn->prepare("UPDATE logi SET last_activity = NOW() WHERE id = ?");
$stmt->bind_param("i", $from_id);
$stmt->execute();
$stmt->close();
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
body {
    font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    min-height: 100vh;
    padding: 20px;
    overflow: hidden;
}

.container {
    max-width: 900px;
    margin: 0 auto;
    background: white;
    border-radius: 20px;
    overflow: hidden;
    box-shadow: 0 10px 40px rgba(0,0,0,0.3);
    display: flex;
    flex-direction: column;
    height: calc(100vh - 40px);
}

.chat-header {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
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
    transition: 0.3s;
    display: flex;
    align-items: center;
    gap: 5px;
}

.back-btn:hover {
    background: rgba(255,255,255,0.3);
    transform: translateX(-3px);
}

.header-info {
    flex: 1;
}

.chat-title {
    font-size: 18px;
    font-weight: bold;
    margin-bottom: 5px;
    display: flex;
    align-items: center;
    gap: 10px;
}

.online-status {
    width: 10px;
    height: 10px;
    border-radius: 50%;
    background: #4ade80;
    animation: pulse 2s infinite;
}

.offline-status {
    width: 10px;
    height: 10px;
    border-radius: 50%;
    background: #94a3b8;
}

@keyframes pulse {
    0%, 100% { opacity: 1; }
    50% { opacity: 0.5; }
}

.chat-subtitle {
    font-size: 14px;
    opacity: 0.9;
}

.product-mini {
    display: flex;
    align-items: center;
    gap: 10px;
    background: rgba(255,255,255,0.15);
    padding: 8px 12px;
    border-radius: 10px;
    cursor: pointer;
    transition: 0.3s;
}

.product-mini:hover {
    background: rgba(255,255,255,0.25);
}

.product-mini img {
    width: 40px;
    height: 40px;
    border-radius: 8px;
    object-fit: cover;
}

.product-mini-text {
    font-size: 13px;
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

.messages-container::-webkit-scrollbar {
    width: 8px;
}

.messages-container::-webkit-scrollbar-track {
    background: #f1f1f1;
}

.messages-container::-webkit-scrollbar-thumb {
    background: #667eea;
    border-radius: 4px;
}

.date-divider {
    text-align: center;
    margin: 15px 0;
    position: relative;
}

.date-divider span {
    background: #f8f9fa;
    padding: 5px 15px;
    border-radius: 20px;
    font-size: 12px;
    color: #999;
    position: relative;
    z-index: 1;
}

.date-divider::before {
    content: '';
    position: absolute;
    left: 0;
    right: 0;
    top: 50%;
    height: 1px;
    background: #e0e0e0;
}

.message {
    max-width: 70%;
    padding: 12px 16px;
    border-radius: 18px;
    word-wrap: break-word;
    animation: slideIn 0.3s;
    position: relative;
}

@keyframes slideIn {
    from {
        opacity: 0;
        transform: translateY(10px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.message.from-me {
    align-self: flex-end;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    border-bottom-right-radius: 4px;
}

.message.from-other {
    align-self: flex-start;
    background: white;
    color: #333;
    border-bottom-left-radius: 4px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
}

.message-sender {
    font-weight: bold;
    font-size: 12px;
    margin-bottom: 4px;
    opacity: 0.8;
    color: #667eea;
}

.message.from-me .message-sender {
    color: rgba(255,255,255,0.9);
}

.message-text {
    font-size: 15px;
    line-height: 1.5;
    white-space: pre-wrap;
}

.message-time {
    font-size: 10px;
    margin-top: 5px;
    opacity: 0.7;
    text-align: right;
}

.typing-indicator {
    display: none;
    align-self: flex-start;
    background: white;
    padding: 15px 20px;
    border-radius: 18px;
    border-bottom-left-radius: 4px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
}

.typing-indicator.active {
    display: flex;
    align-items: center;
    gap: 5px;
}

.typing-dot {
    width: 8px;
    height: 8px;
    border-radius: 50%;
    background: #94a3b8;
    animation: typing 1.4s infinite;
}

.typing-dot:nth-child(2) {
    animation-delay: 0.2s;
}

.typing-dot:nth-child(3) {
    animation-delay: 0.4s;
}

@keyframes typing {
    0%, 60%, 100% { transform: translateY(0); }
    30% { transform: translateY(-10px); }
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
    align-items: flex-end;
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
    max-height: 120px;
    transition: 0.3s;
}

.chat-input:focus {
    outline: none;
    border-color: #667eea;
    box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
}

.char-counter {
    position: absolute;
    right: 15px;
    bottom: 12px;
    font-size: 11px;
    color: #999;
    pointer-events: none;
}

.send-btn {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    border: none;
    padding: 12px 24px;
    border-radius: 25px;
    font-size: 15px;
    font-weight: bold;
    cursor: pointer;
    transition: 0.3s;
    white-space: nowrap;
}

.send-btn:hover:not(:disabled) {
    transform: translateY(-2px);
    box-shadow: 0 5px 20px rgba(102, 126, 234, 0.4);
}

.send-btn:disabled {
    opacity: 0.6;
    cursor: not-allowed;
}

.empty-chat {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    height: 100%;
    text-align: center;
    padding: 40px;
    color: #999;
}

.empty-chat-icon {
    font-size: 80px;
    margin-bottom: 20px;
}

.empty-chat h3 {
    font-size: 24px;
    margin-bottom: 10px;
    color: #666;
}

.connection-status {
    position: fixed;
    top: 20px;
    left: 50%;
    transform: translateX(-50%);
    background: #ef4444;
    color: white;
    padding: 12px 24px;
    border-radius: 25px;
    box-shadow: 0 5px 20px rgba(0,0,0,0.2);
    display: none;
    align-items: center;
    gap: 10px;
    z-index: 1000;
    animation: slideDown 0.3s;
}

.connection-status.show {
    display: flex;
}

@keyframes slideDown {
    from {
        opacity: 0;
        transform: translateX(-50%) translateY(-20px);
    }
    to {
        opacity: 1;
        transform: translateX(-50%) translateY(0);
    }
}

@media (max-width: 768px) {
    body {
        padding: 0;
    }
    
    .container {
        height: 100vh;
        border-radius: 0;
    }
    
    .message {
        max-width: 85%;
    }
    
    .chat-form {
        flex-direction: column;
        gap: 10px;
    }
    
    .send-btn {
        width: 100%;
    }
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
}
</style>
</head>
<body>

<div class="connection-status" id="connectionStatus">
    <span>⚠️</span>
    <span>Utracono połączenie. Próba ponownego połączenia...</span>
</div>
<div class="header">
    <div class="header-content">
        <div class="logo-section" onclick="window.location='index.php'">
            <div class="logo-icon"><img src = "./images/logo.png" height = "50px" width = "50px"></div>
            <div class="logo-text">
                <div class="logo-main">GórkaSklep.pl</div>
                <div class="logo-subtitle">Szkolny Sklep Internetowy</div>
                <a href="https://lo2rabka.nowotarski.edu.pl" target="_blank" class="school-link" onclick="event.stopPropagation()">
                     Przejdź na nasza stronę szkoły! 🏫
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
<div class="container">
    <div class="chat-header">
        <button class="back-btn" onclick="window.location='wiadomosci.php'">
            ← Wróć
        </button>
        
        <div class="header-info">
            <div class="chat-title">
                💬 <?= htmlspecialchars($other_user['username']) ?>
                <?php if ($is_online): ?>
                    <div class="online-status" title="Online"></div>
                <?php else: ?>
                    <div class="offline-status" title="Offline"></div>
                <?php endif; ?>
            </div>
            <div class="chat-subtitle">
                <?php if (!empty($other_user['bio'])): ?>
                    <?= htmlspecialchars(mb_substr($other_user['bio'], 0, 50)) ?>
                <?php else: ?>
                    Kliknij produkt po prawej, aby zobaczyć szczegóły
                <?php endif; ?>
            </div>
        </div>
        
        <div class="product-mini" onclick="window.location='produkt.php?id=<?= $produkt['id'] ?>'">
            <?php if (!empty($produkt['zdjecie'])): ?>
                <img src="<?= htmlspecialchars($produkt['zdjecie']) ?>" alt="">
            <?php else: ?>
                <div style="width:40px;height:40px;background:#f0f0f0;border-radius:8px;"></div>
            <?php endif; ?>
            <div class="product-mini-text">
                <div><strong><?= number_format($produkt['cena'], 2) ?> zł</strong></div>
                <div style="font-size:11px;opacity:0.8;">Zobacz produkt</div>
            </div>
        </div>
    </div>

    <div class="messages-container" id="messages">
        <div class="empty-chat">
            <div class="empty-chat-icon">💬</div>
            <h3>Rozpocznij rozmowę</h3>
            <p>Wyślij pierwszą wiadomość</p>
        </div>
    </div>
    
    <div class="typing-indicator" id="typingIndicator">
        <div class="typing-dot"></div>
        <div class="typing-dot"></div>
        <div class="typing-dot"></div>
    </div>

    <div class="chat-input-container">
        <form class="chat-form" id="chatForm">
            <div class="input-wrapper">
                <textarea 
                    class="chat-input" 
                    id="messageInput" 
                    name="message" 
                    placeholder="Wpisz wiadomość..." 
                    required 
                    autocomplete="off"
                    rows="1"
                    maxlength="1000"></textarea>
                <span class="char-counter" id="charCounter">0/1000</span>
            </div>
            <button type="submit" class="send-btn" id="sendBtn">Wyślij 📤</button>
        </form>
    </div>
</div>

<script>
const form = document.getElementById('chatForm');
const input = document.getElementById('messageInput');
const messagesDiv = document.getElementById('messages');
const sendBtn = document.getElementById('sendBtn');
const charCounter = document.getElementById('charCounter');
const connectionStatus = document.getElementById('connectionStatus');
const typingIndicator = document.getElementById('typingIndicator');
const fromId = <?= $from_id ?>;
const toId = <?= $to_id ?>;
const produktId = <?= $produkt_id ?>;

let lastMessageCount = 0;
let isConnected = true;
let typingTimeout;

// Auto-resize textarea
input.addEventListener('input', function() {
    this.style.height = 'auto';
    this.style.height = (this.scrollHeight) + 'px';
    charCounter.textContent = this.value.length + '/1000';
    
    // Pokaż wskaźnik pisania
    clearTimeout(typingTimeout);
    // W prawdziwej aplikacji wysłałbyś tu event do serwera
    typingTimeout = setTimeout(() => {
        // Przestań pokazywać wskaźnik
    }, 1000);
});

async function fetchMessages() {
    try {
        const resp = await fetch(`czat.php?produkt_id=${produktId}&user_id=${toId}&fetch=1`, {
            cache: 'no-store'
        });
        
        if (!resp.ok) throw new Error('Network error');
        
        if (!isConnected) {
            isConnected = true;
            connectionStatus.classList.remove('show');
        }
        
        const msgs = await resp.json();
        
        if (msgs.length === 0) {
            if (lastMessageCount === 0) {
                messagesDiv.innerHTML = `
                    <div class="empty-chat">
                        <div class="empty-chat-icon">💬</div>
                        <h3>Rozpocznij rozmowę</h3>
                        <p>Wyślij pierwszą wiadomość do <?= htmlspecialchars($other_user['username']) ?></p>
                    </div>
                `;
            }
            return;
        }
        
        const wasAtBottom = messagesDiv.scrollHeight - messagesDiv.scrollTop <= messagesDiv.clientHeight + 100;
        const isNewMessage = msgs.length > lastMessageCount;
        lastMessageCount = msgs.length;
        
        messagesDiv.innerHTML = '';
        
        let lastDate = '';
        
        for (const m of msgs) {
            const msgDate = new Date(m.created_at);
            const dateStr = msgDate.toLocaleDateString('pl-PL', { 
                year: 'numeric', 
                month: 'long', 
                day: 'numeric' 
            });
            
            if (dateStr !== lastDate) {
                const divider = document.createElement('div');
                divider.className = 'date-divider';
                const today = new Date().toLocaleDateString('pl-PL', { 
                    year: 'numeric', 
                    month: 'long', 
                    day: 'numeric' 
                });
                const yesterday = new Date(Date.now() - 86400000).toLocaleDateString('pl-PL', { 
                    year: 'numeric', 
                    month: 'long', 
                    day: 'numeric' 
                });
                
                let displayDate = dateStr;
                if (dateStr === today) displayDate = 'Dziś';
                else if (dateStr === yesterday) displayDate = 'Wczoraj';
                
                divider.innerHTML = `<span>${displayDate}</span>`;
                messagesDiv.appendChild(divider);
                lastDate = dateStr;
            }
            
            const div = document.createElement('div');
            // NAPRAWIONE: Używam parseInt() i === dla pewności poprawnego porównania
            const isMyMessage = parseInt(m.user_from) === parseInt(fromId);
            div.className = 'message ' + (isMyMessage ? 'from-me' : 'from-other');
            
            // Pokaż nazwę nadawcy tylko dla wiadomości od innych
            if (!isMyMessage) {
                const sender = document.createElement('div');
                sender.className = 'message-sender';
                sender.textContent = m.from_name;
                div.appendChild(sender);
            }
            
            const text = document.createElement('div');
            text.className = 'message-text';
            text.textContent = m.message;
            div.appendChild(text);
            
            const time = document.createElement('div');
            time.className = 'message-time';
            time.textContent = msgDate.toLocaleTimeString('pl-PL', {
                hour: '2-digit', 
                minute: '2-digit'
            });
            div.appendChild(time);
            
            messagesDiv.appendChild(div);
        }
        
        if (wasAtBottom || isNewMessage) {
            messagesDiv.scrollTop = messagesDiv.scrollHeight;
        }
        
    } catch(e) {
        console.error('Błąd pobierania wiadomości:', e);
        if (isConnected) {
            isConnected = false;
            connectionStatus.classList.add('show');
        }
    }
}

form.addEventListener('submit', async function(e) {
    e.preventDefault();
    
    const message = input.value.trim();
    if (!message || message.length > 1000) return;
    
    sendBtn.disabled = true;
    sendBtn.textContent = 'Wysyłanie...';
    
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
        console.error('Błąd wysyłania:', e);
        alert('Nie udało się wysłać wiadomości. Spróbuj ponownie.');
    } finally {
        sendBtn.disabled = false;
        sendBtn.textContent = 'Wyślij 📤';
    }
});

// Enter wysyła, Shift+Enter nowa linia
input.addEventListener('keydown', function(e) {
    if (e.key === 'Enter' && !e.shiftKey) {
        e.preventDefault();
        if (this.value.trim()) {
            form.dispatchEvent(new Event('submit'));
        }
    }
});

// Początkowe pobranie i cykliczne odświeżanie
fetchMessages();
const refreshInterval = setInterval(fetchMessages, 2000);

// Wyczyść interval przy opuszczeniu strony
window.addEventListener('beforeunload', () => {
    clearInterval(refreshInterval);
});

// Focus na input po załadowaniu
window.addEventListener('load', () => {
    input.focus();
});
</script>

</body>
</html>
<?php $conn->close(); ?>