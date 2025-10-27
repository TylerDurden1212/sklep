<?php
// Konfiguracja bazy danych
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
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
        header("Location: login.php");
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
?>