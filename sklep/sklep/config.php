<?php
// Konfiguracja bazy danych
define('DB_HOST', '192.168.1.202');
define('DB_USER', 'sklepuser');
define('DB_PASS', 'twojehaslo');
define('DB_NAME', 'sklep');

// Funkcja połączenia z bazą
function getDBConnection() {
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    if ($conn->connect_error) {
        die("Błąd połączenia z bazą danych: " . $conn->connect_error);
    }
    $conn->set_charset("utf8mb4");
    return $conn;
}

// Funkcja escape HTML
function h($str) {
    return htmlspecialchars($str ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

// Funkcja sprawdzająca zalogowanie
function requireLogin() {
    if (empty($_SESSION['user_id'])) {
        header("Location: logowanie.php");
        exit;
    }
}

// Funkcja formatowania czasu
function timeAgo($timestamp) {
    $time = strtotime($timestamp);
    $diff = time() - $time;
    
    if ($diff < 60) return "przed chwilą";
    if ($diff < 3600) return floor($diff/60) . " min temu";
    if ($diff < 86400) return floor($diff/3600) . " godz. temu";
    if ($diff < 172800) return "wczoraj";
    return date('d.m.Y', $time);
}

// Funkcja sprawdzająca istnienie pliku
function fileExists($path) {
    if (empty($path)) return false;
    return file_exists(__DIR__ . '/' . $path);
}

// Funkcja bezpiecznego wyświetlania zdjęcia
function getImageSrc($path, $placeholder = 'https://via.placeholder.com/300x300?text=Brak+zdjęcia') {
    if (!empty($path) && file_exists(__DIR__ . '/' . $path)) {
        return h($path);
    }
    return $placeholder;
}

// Ikony kategorii
function getCategoryIcon($category) {
    $icons = [
        'elektronika' => '📱',
        'odziez' => '👕',
        'dom' => '🏠',
        'sport' => '⚽',
        'inne' => '📦'
    ];
    return $icons[$category] ?? '📦';
}

// Nazwy kategorii
function getCategoryName($category) {
    $names = [
        'elektronika' => 'Elektronika',
        'odziez' => 'Odzież',
        'dom' => 'Dom i Ogród',
        'sport' => 'Sport',
        'inne' => 'Inne'
    ];
    return $names[$category] ?? 'Inne';
}

// Limity
define('MAX_UPLOAD_SIZE', 5 * 1024 * 1024); // 5MB
define('ALLOWED_IMAGE_TYPES', ['jpg', 'jpeg', 'png', 'gif', 'webp']);
define('MAX_PRODUCT_PRICE', 10000);
define('MAX_DESCRIPTION_LENGTH', 1000);
define('MAX_MESSAGE_LENGTH', 1000);
define('MAX_PRODUCTS_PER_DAY', 5); // Nowy limit
define('UPLOAD_DIR', __DIR__ . '/uploads/');
define('PROFILE_UPLOAD_DIR', __DIR__ . '/uploads/profiles/');

// Utwórz foldery jeśli nie istnieją
if (!file_exists(UPLOAD_DIR)) {
    mkdir(UPLOAD_DIR, 0777, true);
}
if (!file_exists(PROFILE_UPLOAD_DIR)) {
    mkdir(PROFILE_UPLOAD_DIR, 0777, true);
}

// Funkcja bezpiecznego uploadu zdjęcia
function uploadImage($file, $prefix = 'img', $uploadDir = UPLOAD_DIR) {
    if (!isset($file) || $file['error'] !== UPLOAD_ERR_OK) {
        return ['success' => false, 'error' => 'Błąd przesyłania pliku'];
    }
    
    if ($file['size'] > MAX_UPLOAD_SIZE) {
        return ['success' => false, 'error' => 'Plik jest za duży (max 5MB)'];
    }
    
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, ALLOWED_IMAGE_TYPES)) {
        return ['success' => false, 'error' => 'Nieprawidłowy format pliku'];
    }
    
    $fileName = $prefix . '_' . uniqid() . '_' . time() . '.' . $ext;
    $targetPath = $uploadDir . $fileName;
    
    if (move_uploaded_file($file['tmp_name'], $targetPath)) {
        $relativePath = str_replace(__DIR__ . '/', '', $targetPath);
        return ['success' => true, 'path' => $relativePath];
    }
    
    return ['success' => false, 'error' => 'Nie udało się zapisać pliku'];
}

// Funkcja usuwania starego pliku
function deleteOldFile($path) {
    if (!empty($path) && file_exists(__DIR__ . '/' . $path)) {
        unlink(__DIR__ . '/' . $path);
    }
}

// Wiadomości flash
function setFlashMessage($type, $message) {
    $_SESSION['flash_message'] = ['type' => $type, 'message' => $message];
}

function getFlashMessage() {
    if (isset($_SESSION['flash_message'])) {
        $flash = $_SESSION['flash_message'];
        unset($_SESSION['flash_message']);
        return $flash;
    }
    return null;
}

// NOWE FUNKCJE - Filtrowanie wulgaryzmów
function filterProfanity($text) {
    $profanity = [
        'kurwa', 'kurde', 'chuj', 'huj', 'kutas', 'jebać', 'jebac', 'pierdol', 
        'pierdolić', 'pierdolic', 'szmata', 'dziwka', 'skurwysyn', 'skurwiel',
        'zajebisty', 'wpierdol', 'wypierdalaj', 'spierdalaj', 'gówno', 'gowno',
        'srać', 'srac', 'dupa', 'dupek', 'cipka', 'pizda', 'fiut',
        'fuck', 'shit', 'bitch', 'ass', 'dick', 'pussy', 'cunt', 'cock',
        'damn', 'bastard', 'whore', 'slut', 'nazi', 'hitler', 'nigger', 'spierdalaj', 'japierodle','kutas', 'niger'
    ];
    
    $text_lower = mb_strtolower($text, 'UTF-8');
    
    foreach ($profanity as $word) {
        if (mb_strpos($text_lower, $word) !== false) {
            return false;
        }
    }
    
    return true;
}

// Sprawdź czy użytkownik może dodać ogłoszenie (max 5 dziennie)
function canAddProduct($user_id, $conn) {
    $today = date('Y-m-d');
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM produkty WHERE id_sprzedawcy = ? AND DATE(data_dodania) = ?");
    $stmt->bind_param("is", $user_id, $today);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    
    return $result['count'] < MAX_PRODUCTS_PER_DAY;
}

// Pobierz liczbę ogłoszeń użytkownika dzisiaj
function getTodayProductCount($user_id, $conn) {
    $today = date('Y-m-d');
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM produkty WHERE id_sprzedawcy = ? AND DATE(data_dodania) = ?");
    $stmt->bind_param("is", $user_id, $today);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    
    return $result['count'];
}

// Pobierz liczbę nieprzeczytanych wiadomości
function getUnreadCount($user_id, $conn) {
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM chats WHERE user_to = ? AND read_status = 0");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $result['count'];
}
function getRatingHTML($seller_id, $can_rate = false, $product_id = null) {
    $conn = getDBConnection();
    
    // Pobierz statystyki
    $stmt = $conn->prepare("SELECT rating_avg, rating_count FROM logi WHERE id = ?");
    $stmt->bind_param("i", $seller_id);
    $stmt->execute();
    $stats = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    
    $avg = $stats['rating_avg'] ? floatval($stats['rating_avg']) : 0;
    $count = $stats['rating_count'] ?? 0;
    
    // Pobierz ostatnie 3 opinie (skrócona wersja)
    $stmt = $conn->prepare("
        SELECT r.*, l.username, l.profile_picture 
        FROM ratings r
        LEFT JOIN logi l ON r.buyer_id = l.id
        WHERE r.seller_id = ?
        ORDER BY r.created_at DESC
        LIMIT 3
    ");
    $stmt->bind_param("i", $seller_id);
    $stmt->execute();
    $reviews = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    
    $conn->close();
    
    ob_start();
    ?>
    
    <div class="rating-section">
        <div class="rating-header">
            <div class="rating-summary">
                <?php if ($count > 0): ?>
                    <div class="rating-value"><?= number_format($avg, 1) ?></div>
                    <div class="rating-details">
                        <div class="rating-stars">
                            <?php 
                            // Wyświetl gwiazdki z półgwiazdkami
                            for ($i = 1; $i <= 5; $i++): 
                                if ($i <= floor($avg)): ?>
                                    <span class="star filled">⭐</span>
                                <?php elseif ($i <= ceil($avg) && $avg - floor($avg) >= 0.5): ?>
                                    <span class="star half-filled">⭐</span>
                                <?php else: ?>
                                    <span class="star">☆</span>
                                <?php endif;
                            endfor; ?>
                        </div>
                        <div class="rating-count"><?= $count ?> <?= $count == 1 ? 'ocena' : 'ocen' ?></div>
                    </div>
                <?php else: ?>
                    <div class="rating-value">-</div>
                    <div class="rating-details">
                        <div class="rating-stars">
                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                <span class="star">☆</span>
                            <?php endfor; ?>
                        </div>
                        <div class="rating-count">Brak ocen</div>
                    </div>
                <?php endif; ?>
            </div>
            
            <?php if ($can_rate && $product_id): ?>
                <button class="rating-btn" onclick="toggleRatingForm()">
                    ⭐ Wystaw ocenę
                </button>
            <?php endif; ?>
        </div>
        
        <?php if ($can_rate && $product_id): ?>
        <div class="rating-form" id="ratingForm">
            <div class="form-group">
                <label>Twoja ocena:</label>
                <div class="rating-stars interactive" id="userRating">
                    <?php for ($i = 1; $i <= 5; $i++): ?>
                        <span class="star" data-rating="<?= $i ?>" 
                              onmouseover="previewRating(<?= $i ?>)" 
                              onmouseout="resetPreview()"
                              onclick="selectRating(<?= $i ?>)">☆</span>
                    <?php endfor; ?>
                </div>
            </div>
            
            <div class="form-group">
                <label>Komentarz (opcjonalnie):</label>
                <textarea id="ratingComment" placeholder="Podziel się swoją opinią..." maxlength="500"></textarea>
                <div style="font-size: 12px; color: #999; text-align: right;" id="commentCounter">0/500</div>
            </div>
            
            <button class="rating-btn" onclick="submitRating(<?= $seller_id ?>, <?= $product_id ?>)">
                Wyślij ocenę
            </button>
        </div>
        <?php endif; ?>
        
        <?php if (!empty($reviews)): ?>
        <div class="reviews-list">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
                <h3>📝 Ostatnie opinie</h3>
                <?php if ($count > 3): ?>
                    <a href="opinie.php?user_id=<?= $seller_id ?>" class="view-all-link">
                        Zobacz wszystkie (<?= $count ?>) →
                    </a>
                <?php endif; ?>
            </div>
            <?php foreach ($reviews as $review): ?>
                <div class="review-item">
                    <div class="review-header">
                        <div class="review-user">
                            <div class="review-avatar">
                                <?= strtoupper(substr($review['username'] ?? 'U', 0, 1)) ?>
                            </div>
                            <strong><?= htmlspecialchars($review['username']) ?></strong>
                        </div>
                        <div class="review-stars">
                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                <span class="star <?= $i <= $review['rating'] ? 'filled' : '' ?>">
                                    <?= $i <= $review['rating'] ? '⭐' : '☆' ?>
                                </span>
                            <?php endfor; ?>
                        </div>
                    </div>
                    <?php if (!empty($review['comment'])): ?>
                        <div class="review-comment"><?= htmlspecialchars($review['comment']) ?></div>
                    <?php endif; ?>
                    <div class="review-date">
                        <?= date('d.m.Y H:i', strtotime($review['created_at'])) ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
    
    <script>
    let selectedRating = 0;
    let previewActive = false;
    
    function toggleRatingForm() {
        const form = document.getElementById('ratingForm');
        form.classList.toggle('show');
    }
    
    function previewRating(rating) {
        if (selectedRating === 0) {
            previewActive = true;
            updateStars(rating, 'userRating', true);
        }
    }
    
    function resetPreview() {
        if (previewActive && selectedRating === 0) {
            updateStars(0, 'userRating', false);
        }
        previewActive = false;
    }
    
    function selectRating(rating) {
        selectedRating = rating;
        previewActive = false;
        updateStars(rating, 'userRating', true);
    }
    
    function updateStars(rating, containerId, filled) {
        const stars = document.querySelectorAll(`#${containerId} .star`);
        stars.forEach((star, index) => {
            if (index < rating) {
                star.textContent = '⭐';
                star.classList.add('filled');
            } else {
                star.textContent = '☆';
                star.classList.remove('filled');
            }
        });
    }
    
    // Licznik znaków w komentarzu
    const commentField = document.getElementById('ratingComment');
    const commentCounter = document.getElementById('commentCounter');
    if (commentField && commentCounter) {
        commentField.addEventListener('input', function() {
            commentCounter.textContent = this.value.length + '/500';
        });
    }
    
    async function submitRating(sellerId, productId) {
        if (selectedRating === 0) {
            if (typeof showToast === 'function') {
                showToast('warning', 'Uwaga', 'Wybierz ocenę gwiazdkową');
            } else {
                alert('Wybierz ocenę gwiazdkową');
            }
            return;
        }
        
        const comment = document.getElementById('ratingComment').value;
        
        try {
            const formData = new FormData();
            formData.append('seller_id', sellerId);
            formData.append('produkt_id', productId);
            formData.append('rating', selectedRating);
            formData.append('comment', comment);
            
            const resp = await fetch('rate_seller.php', {
                method: 'POST',
                body: formData
            });
            
            const data = await resp.json();
            
            if (data.success) {
                if (typeof showToast === 'function') {
                    showToast('success', 'Sukces!', 'Twoja ocena została zapisana');
                } else {
                    alert('Twoja ocena została zapisana');
                }
                setTimeout(() => location.reload(), 1500);
            } else {
                if (typeof showToast === 'function') {
                    showToast('error', 'Błąd', data.error || 'Nie udało się zapisać oceny');
                } else {
                    alert(data.error || 'Nie udało się zapisać oceny');
                }
            }
        } catch(e) {
            console.error('Error:', e);
            if (typeof showToast === 'function') {
                showToast('error', 'Błąd', 'Wystąpił błąd podczas zapisywania oceny');
            } else {
                alert('Wystąpił błąd podczas zapisywania oceny');
            }
        }
    }
    </script>
    
    <style>
    .rating-section {
        background: var(--card-bg, white);
        padding: 25px;
        border-radius: 20px;
        border: 2px solid var(--border-color, #e0e0e0);
        margin: 20px 0;
    }
    
    .rating-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 15px;
        margin-bottom: 20px;
        flex-wrap: wrap;
    }
    
    .rating-stars {
        display: flex;
        gap: 5px;
        font-size: 28px;
    }
    
    .rating-stars.interactive .star {
        cursor: pointer;
        transition: 0.2s;
        font-size: 36px;
    }
    
    .rating-stars.interactive .star:hover {
        transform: scale(1.2);
    }
    
    .star {
        color: #ddd;
        transition: 0.2s;
    }
    
    .star.filled {
        color: #fbbf24;
        animation: starPop 0.3s ease-out;
    }
    
    .star.half-filled {
        color: #fbbf24;
        opacity: 0.6;
    }
    
    @keyframes starPop {
        0%, 100% { transform: scale(1); }
        50% { transform: scale(1.2); }
    }
    
    .rating-summary {
        display: flex;
        align-items: center;
        gap: 15px;
    }
    
    .rating-value {
        font-size: 48px;
        font-weight: bold;
        background: linear-gradient(135deg, #ff8c42 0%, #ff6b35 100%);
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
    }
    
    .rating-details {
        display: flex;
        flex-direction: column;
    }
    
    .rating-count {
        font-size: 14px;
        color: #999;
        margin-top: 5px;
    }
    
    .rating-form {
        display: none;
        margin-top: 20px;
        padding: 20px;
        background: var(--hover-bg, #fff5f0);
        border-radius: 15px;
    }
    
    .rating-form.show {
        display: block;
        animation: slideDown 0.3s;
    }
    
    @keyframes slideDown {
        from { opacity: 0; transform: translateY(-10px); }
        to { opacity: 1; transform: translateY(0); }
    }
    
    .rating-form .form-group {
        margin-bottom: 15px;
    }
    
    .rating-form .form-group label {
        display: block;
        margin-bottom: 8px;
        font-weight: 600;
        color: var(--text-color, #333);
    }
    
    .rating-form .form-group textarea {
        width: 100%;
        padding: 12px;
        border: 2px solid var(--border-color, #e0e0e0);
        border-radius: 10px;
        resize: vertical;
        min-height: 80px;
        font-family: inherit;
        background: var(--card-bg, white);
        color: var(--text-color, #333);
    }
    
    .rating-btn {
        padding: 12px 24px;
        border: none;
        border-radius: 12px;
        font-weight: bold;
        cursor: pointer;
        transition: 0.3s;
        background: linear-gradient(135deg, #ff8c42 0%, #ff6b35 100%);
        color: white;
    }
    
    .rating-btn:hover {
        transform: translateY(-2px);
        box-shadow: 0 5px 15px rgba(255, 140, 66, 0.4);
    }
    
    .reviews-list {
        margin-top: 30px;
    }
    
    .view-all-link {
        color: var(--primary, #ff8c42);
        text-decoration: none;
        font-weight: 600;
        font-size: 14px;
        transition: 0.3s;
    }
    
    .view-all-link:hover {
        color: var(--secondary, #ff6b35);
        text-decoration: underline;
    }
    
    .review-item {
        background: var(--hover-bg, #fff5f0);
        padding: 20px;
        border-radius: 15px;
        margin-bottom: 15px;
    }
    
    .review-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 10px;
        flex-wrap: wrap;
        gap: 10px;
    }
    
    .review-user {
        display: flex;
        align-items: center;
        gap: 10px;
    }
    
    .review-avatar {
        width: 40px;
        height: 40px;
        border-radius: 50%;
        background: linear-gradient(135deg, #ff8c42 0%, #ff6b35 100%);
        color: white;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: bold;
    }
    
    .review-stars {
        font-size: 16px;
    }
    
    .review-comment {
        color: var(--text-color, #333);
        line-height: 1.6;
        margin-top: 10px;
    }
    
    .review-date {
        font-size: 12px;
        color: #999;
        margin-top: 8px;
    }
    
    @media (max-width: 768px) {
        .rating-header {
            flex-direction: column;
            align-items: flex-start;
        }
        
        .rating-value {
            font-size: 36px;
        }
        
        .rating-stars {
            font-size: 24px;
        }
        
        .rating-stars.interactive .star {
            font-size: 28px;
        }
    }
    </style>
      <?php
    return ob_get_clean();
}
?>