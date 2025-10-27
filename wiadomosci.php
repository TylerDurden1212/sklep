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

$user_id = $_SESSION['user_id'];

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
    
    // Pobierz ostatnią wiadomość
    $msg_stmt = $conn->prepare("
        SELECT message, created_at 
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
    $conv['last_message'] = $last_msg ? $last_msg['message'] : '';
    $conv['ostatnia_wiadomosc'] = $last_msg ? $last_msg['created_at'] : date('Y-m-d H:i:s');
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
<title>💬 Wiadomości</title>
<style>
* { margin: 0; padding: 0; box-sizing: border-box; }
body {
    font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    min-height: 100vh;
    padding: 20px;
}

.header {
    max-width: 1000px;
    margin: 0 auto 30px;
    background: white;
    padding: 25px 30px;
    border-radius: 20px;
    box-shadow: 0 10px 30px rgba(0,0,0,0.2);
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.header h1 {
    font-size: 32px;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
}

.back-btn {
    background: #667eea;
    color: white;
    padding: 12px 24px;
    border-radius: 25px;
    text-decoration: none;
    font-weight: bold;
    transition: 0.3s;
}

.back-btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
}

.container {
    max-width: 1000px;
    margin: 0 auto;
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
    border-left: 5px solid #667eea;
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
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
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

@keyframes pulse {
    0%, 100% { transform: scale(1); }
    50% { transform: scale(1.1); }
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
    .header {
        flex-direction: column;
        gap: 15px;
        text-align: center;
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
    <h1>💬 Twoje wiadomości</h1>
    <a href="index.php" class="back-btn">← Powrót</a>
</div>

<div class="container">
    <div class="conversation-list">
        <?php if (count($conversations_array) > 0): ?>
            <?php foreach ($conversations_array as $conv): ?>
                <div class="conversation-card <?= $conv['nieprzeczytane'] > 0 ? 'unread' : '' ?>" 
                     onclick="window.location='czat.php?produkt_id=<?= $conv['produkt_id'] ?>&user_id=<?= $conv['other_user_id'] ?>'">
                    
                    <?php if ($conv['nieprzeczytane'] > 0): ?>
                        <div class="unread-badge"><?= $conv['nieprzeczytane'] ?></div>
                    <?php endif; ?>
                    
                    <?php if (!empty($conv['produkt_zdjecie']) && file_exists($conv['produkt_zdjecie'])): ?>
                        <img src="<?= htmlspecialchars($conv['produkt_zdjecie']) ?>" class="product-image" alt="">
                    <?php else: ?>
                        <img src="https://via.placeholder.com/90?text=?" class="product-image" alt="">
                    <?php endif; ?>
                    
                    <div class="conversation-main">
                        <div class="conversation-header">
                            <div class="conversation-title">
                                <?php if (!empty($conv['other_profile_picture']) && file_exists($conv['other_profile_picture'])): ?>
                                    <img src="<?= htmlspecialchars($conv['other_profile_picture']) ?>" class="user-avatar" alt="">
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

</body>
</html>
<?php $conn->close(); ?>