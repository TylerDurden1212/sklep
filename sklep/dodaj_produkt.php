<?php
session_start();
require_once 'config.php';

requireLogin();

$conn = getDBConnection();
$msg = "";
$msgType = "";

$todayCount = getTodayProductCount($_SESSION['user_id'], $conn);
$remainingToday = MAX_PRODUCTS_PER_DAY - $todayCount;
$unread_count = getUnreadCount($_SESSION['user_id'], $conn);
$search = '';

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $nazwa = trim($_POST["nazwa"] ?? '');
    $opis = trim($_POST["opis"] ?? '');
    $cena = floatval($_POST["cena"] ?? 0);
    $kategoria = $_POST["kategoria"] ?? 'inne';

    if (empty($nazwa) || empty($opis) || $cena <= 0) {
        $msg = "Wszystkie pola muszą być wypełnione poprawnie.";
        $msgType = "error";
    } elseif (!canAddProduct($_SESSION['user_id'], $conn)) {
        $msg = "Osiągnąłeś dzienny limit " . MAX_PRODUCTS_PER_DAY . " ogłoszeń. Spróbuj ponownie jutro.";
        $msgType = "error";
    } elseif (!filterProfanity($nazwa)) {
        $msg = "Nazwa produktu zawiera niedozwolone słowa.";
        $msgType = "error";
    } elseif (!filterProfanity($opis)) {
        $msg = "Opis produktu zawiera niedozwolone słowa.";
        $msgType = "error";
    } elseif (strlen($opis) > MAX_DESCRIPTION_LENGTH) {
        $msg = "Opis nie może mieć więcej niż " . MAX_DESCRIPTION_LENGTH . " znaków.";
        $msgType = "error";
    } elseif ($cena > MAX_PRODUCT_PRICE) {
        $msg = "Cena nie może przekraczać " . number_format(MAX_PRODUCT_PRICE, 2) . " zł.";
        $msgType = "error";
    } else {
        $zdjeciaPaths = [];
        $uploadError = false;
        
        // Obsługa wielu zdjęć (max 5)
        if (isset($_FILES["zdjecia"]) && is_array($_FILES["zdjecia"]["name"])) {
            $fileCount = count($_FILES["zdjecia"]["name"]);
            
            if ($fileCount > 5) {
                $msg = "Możesz dodać maksymalnie 5 zdjęć.";
                $msgType = "error";
                $uploadError = true;
            } else {
                for ($i = 0; $i < $fileCount; $i++) {
                    if ($_FILES["zdjecia"]["error"][$i] === UPLOAD_ERR_OK) {
                        // Przygotuj tablicę pliku w odpowiednim formacie dla funkcji uploadImage
                        $fileArray = [
                            'name' => $_FILES["zdjecia"]["name"][$i],
                            'type' => $_FILES["zdjecia"]["type"][$i],
                            'tmp_name' => $_FILES["zdjecia"]["tmp_name"][$i],
                            'error' => $_FILES["zdjecia"]["error"][$i],
                            'size' => $_FILES["zdjecia"]["size"][$i]
                        ];
                        
                        $uploadResult = uploadImage($fileArray, 'product');
                        if ($uploadResult['success']) {
                            $zdjeciaPaths[] = $uploadResult['path'];
                        } else {
                            $msg = "Błąd przesyłania zdjęcia " . ($i + 1) . ": " . $uploadResult['error'];
                            $msgType = "error";
                            $uploadError = true;
                            break;
                        }
                    }
                }
            }
        }

        if (empty($msg) && !$uploadError) {
            // Konwertuj tablicę ścieżek do JSON
            $zdjeciaJson = !empty($zdjeciaPaths) ? json_encode($zdjeciaPaths) : null;
            
            $stmt = $conn->prepare("INSERT INTO produkty (nazwa, opis, cena, zdjecie, id_sprzedawcy, kategoria, data_dodania) VALUES (?, ?, ?, ?, ?, ?, NOW())");
            $userId = $_SESSION['user_id'];
            $stmt->bind_param("ssdsds", $nazwa, $opis, $cena, $zdjeciaJson, $userId, $kategoria);
            
            if ($stmt->execute()) {
                $productId = $stmt->insert_id;
                $msg = "Produkt został dodany pomyślnie!";
                $msgType = "success";
                header("refresh:2;url=produkt.php?id=$productId");
            } else {
                $msg = "Błąd podczas dodawania: " . $stmt->error;
                $msgType = "error";
            }
            $stmt->close();
        }
    }
}
$conn->close();
?>
<!doctype html>
<html lang="pl">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>➕ Dodaj produkt - GórkaSklep.pl</title>
<link rel="icon" href="./images/logo_strona.png">
<style>
* { margin: 0; padding: 0; box-sizing: border-box; }

:root {
    --primary: #ff8c42;
    --secondary: #ff6b35;
    --success: #10b981;
    --danger: #ef4444;
    --warning: #f59e0b;
    --dark: #2c3e50;
    --light: #fff5f0;
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

/* Main Content */
.container {
    max-width: 800px;
    margin: 30px auto;
    padding: 0 20px;
}

.form-card {
    background: white;
    padding: 50px;
    border-radius: 25px;
    box-shadow: 0 20px 60px rgba(0,0,0,0.3);
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

.form-header {
    text-align: center;
    margin-bottom: 30px;
}

.form-header h2 {
    font-size: 32px;
    background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    margin-bottom: 10px;
}

.subtitle {
    color: #666;
    font-size: 15px;
}

.limit-info {
    background: <?= $remainingToday > 0 ? '#e0f2fe' : '#fee2e2' ?>;
    padding: 15px 20px;
    border-radius: 15px;
    margin-bottom: 25px;
    display: flex;
    align-items: center;
    gap: 12px;
    border-left: 4px solid <?= $remainingToday > 0 ? '#0ea5e9' : 'var(--danger)' ?>;
}

.limit-info-icon {
    font-size: 28px;
}

.limit-info-text {
    flex: 1;
}

.limit-info-text strong {
    display: block;
    font-size: 16px;
    margin-bottom: 3px;
    color: <?= $remainingToday > 0 ? '#0369a1' : '#991b1b' ?>;
}

.limit-info-text small {
    color: #666;
    font-size: 13px;
}

.user-info {
    background: var(--light);
    padding: 15px 20px;
    border-radius: 15px;
    margin-bottom: 30px;
    display: flex;
    align-items: center;
    gap: 12px;
}

.user-icon {
    width: 45px;
    height: 45px;
    border-radius: 50%;
    background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
    color: white;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 20px;
    font-weight: bold;
}

.alert {
    padding: 18px 24px;
    border-radius: 15px;
    margin-bottom: 25px;
    text-align: center;
    font-weight: 500;
    animation: slideDown 0.3s;
}

.alert-success {
    background: #d4edda;
    color: #155724;
    border-left: 5px solid #28a745;
}

.alert-error {
    background: #f8d7da;
    color: #721c24;
    border-left: 5px solid #dc3545;
}

.form-group {
    margin-bottom: 25px;
}

label {
    display: block;
    margin-bottom: 10px;
    color: #333;
    font-weight: 600;
    font-size: 15px;
}

.required {
    color: var(--danger);
}

input[type=text], textarea, input[type=number], select {
    width: 100%;
    padding: 14px 18px;
    border: 2px solid #e0e0e0;
    border-radius: 12px;
    font-size: 15px;
    transition: 0.3s;
    font-family: inherit;
}

input:focus, textarea:focus, select:focus {
    outline: none;
    border-color: var(--primary);
    box-shadow: 0 0 0 3px rgba(255, 140, 66, 0.1);
}

textarea {
    resize: vertical;
    min-height: 120px;
    line-height: 1.6;
}

.char-count {
    text-align: right;
    font-size: 13px;
    color: #999;
    margin-top: 8px;
}

.char-count.warning {
    color: var(--warning);
}

.char-count.danger {
    color: var(--danger);
    font-weight: bold;
}

.file-upload {
    position: relative;
    margin-top: 10px;
}

.file-input {
    display: none;
}

.file-label {
    display: block;
    padding: 40px 20px;
    background: var(--light);
    border: 3px dashed #ccc;
    border-radius: 15px;
    text-align: center;
    cursor: pointer;
    transition: 0.3s;
}

.file-label:hover {
    border-color: var(--primary);
    background: #ffe0cc;
}

.file-label.has-file {
    border-color: var(--success);
    background: #ecfdf5;
}

.file-icon {
    font-size: 48px;
    margin-bottom: 15px;
}

.file-text {
    color: #666;
    font-size: 14px;
}

.file-count {
    margin-top: 12px;
    font-size: 14px;
    color: var(--success);
    font-weight: 600;
}

.preview-container {
    margin-top: 15px;
    display: none;
    gap: 10px;
    flex-wrap: wrap;
}

.preview-container.show {
    display: flex;
}

.preview-item {
    position: relative;
    width: calc(33.333% - 7px);
    height: 150px;
}

.preview-image {
    width: 100%;
    height: 100%;
    object-fit: cover;
    border-radius: 12px;
    box-shadow: 0 5px 15px rgba(0,0,0,0.1);
}

.remove-image {
    position: absolute;
    top: 5px;
    right: 5px;
    background: var(--danger);
    color: white;
    border: none;
    border-radius: 50%;
    width: 30px;
    height: 30px;
    cursor: pointer;
    font-size: 18px;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: 0.3s;
}

.remove-image:hover {
    transform: scale(1.1);
}

button[type="submit"] {
    width: 100%;
    padding: 16px;
    background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
    color: white;
    border: none;
    border-radius: 15px;
    font-size: 17px;
    font-weight: bold;
    cursor: pointer;
    transition: 0.3s;
    margin-top: 10px;
}

button[type="submit"]:hover:not(:disabled) {
    transform: translateY(-3px);
    box-shadow: 0 10px 25px rgba(255, 140, 66, 0.4);
}

button[type="submit"]:disabled {
    opacity: 0.5;
    cursor: not-allowed;
}

.info-box {
    background: #fff9e6;
    border-left: 4px solid var(--warning);
    padding: 15px 20px;
    border-radius: 10px;
    margin-bottom: 25px;
    font-size: 14px;
    color: #92400e;
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
    
    .form-card {
        padding: 30px 20px;
    }
    
    .form-header h2 {
        font-size: 24px;
    }
    
    .preview-item {
        width: calc(50% - 5px);
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
            <a href="wiadomosci.php" class="menu-item">
                💬 Wiadomości
                <?php if ($unread_count > 0): ?>
                    <span class="badge"><?= $unread_count ?></span>
                <?php endif; ?>
            </a>
            <a href="profil.php" class="menu-item">👤 Profil</a>
            <a href="logout.php" class="menu-item">Wyloguj</a>
        </div>
    </div>
</div>

<div class="container">
    <div class="form-card">
        <div class="form-header">
            <h2>➕ Dodaj nowy produkt</h2>
            <div class="subtitle">Wypełnij formularz, aby dodać ogłoszenie</div>
        </div>

        <div class="limit-info">
            <div class="limit-info-icon"><?= $remainingToday > 0 ? '📊' : '⛔' ?></div>
            <div class="limit-info-text">
                <strong>Dzienny limit ogłoszeń: <?= $todayCount ?>/<?= MAX_PRODUCTS_PER_DAY ?></strong>
                <small>
                    <?php if ($remainingToday > 0): ?>
                        Możesz dodać jeszcze <?= $remainingToday ?> <?= $remainingToday == 1 ? 'ogłoszenie' : 'ogłoszenia' ?> dzisiaj
                    <?php else: ?>
                        Osiągnąłeś dzienny limit. Spróbuj ponownie jutro.
                    <?php endif; ?>
                </small>
            </div>
        </div>

        <div class="user-info">
            <div class="user-icon"><?= strtoupper(substr($_SESSION['username'], 0, 1)) ?></div>
            <div>
                <div style="font-weight:600;">Zalogowany jako:</div>
                <div style="color:#666;font-size:14px;"><?= h($_SESSION['username']) ?></div>
            </div>
        </div>

        <?php if ($msg): ?>
            <div class="alert alert-<?= $msgType ?>">
                <?= $msgType === 'success' ? '✅' : '⚠️' ?> <?= h($msg) ?>
                <?php if ($msgType === 'success'): ?>
                    <div style="margin-top:10px;font-size:13px;">Przekierowuję do produktu...</div>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <div class="info-box">
            <strong>💡 Wskazówki:</strong><br>
            • Dodaj do 5 wyraźnych zdjęć produktu<br>
            • Opisz szczegółowo stan i właściwości<br>
            • Ustal uczciwą cenę<br>
            • Nie używaj wulgaryzmów i nieodpowiednich słów
        </div>

        <form method="post" enctype="multipart/form-data" id="productForm">
            <div class="form-group">
                <label>Nazwa produktu <span class="required">*</span></label>
                <input type="text" name="nazwa" maxlength="100" required placeholder="np. iPhone 13 Pro 256GB" <?= $remainingToday == 0 ? 'disabled' : '' ?>>
            </div>

            <div class="form-group">
                <label>Kategoria <span class="required">*</span></label>
                <select name="kategoria" required <?= $remainingToday == 0 ? 'disabled' : '' ?>>
                    <option value="elektronika">📱 Elektronika</option>
                    <option value="odziez">👕 Odzież</option>
                    <option value="dom">🏠 Dom i Ogród</option>
                    <option value="sport">⚽ Sport</option>
                    <option value="inne" selected>📦 Inne</option>
                </select>
            </div>

            <div class="form-group">
                <label>Opis <span class="required">*</span></label>
                <textarea name="opis" maxlength="<?= MAX_DESCRIPTION_LENGTH ?>" id="opisField" required placeholder="Opisz swój produkt jak najdokładniej..." <?= $remainingToday == 0 ? 'disabled' : '' ?>></textarea>
                <div class="char-count" id="charCount">0 / <?= MAX_DESCRIPTION_LENGTH ?></div>
            </div>

            <div class="form-group">
                <label>Cena (max <?= number_format(MAX_PRODUCT_PRICE, 2) ?> zł) <span class="required">*</span></label>
                <input type="number" step="0.01" name="cena" min="0.01" max="<?= MAX_PRODUCT_PRICE ?>" required placeholder="0.00" <?= $remainingToday == 0 ? 'disabled' : '' ?>>
            </div>

            <div class="form-group">
                <label>Zdjęcia produktu (max 5)</label>
                <div class="file-upload">
                    <input type="file" name="zdjecia[]" accept="image/*" id="fileInput" class="file-input" multiple <?= $remainingToday == 0 ? 'disabled' : '' ?>>
                    <label for="fileInput" class="file-label" id="fileLabel">
                        <div class="file-icon">📷</div>
                        <div class="file-text">Kliknij lub przeciągnij zdjęcia tutaj (max 5)</div>
                        <div class="file-count" id="fileCount"></div>
                    </label>
                </div>
                <div class="preview-container" id="previewContainer"></div>
            </div>

            <button type="submit" <?= $remainingToday == 0 ? 'disabled' : '' ?>>
                <?= $remainingToday > 0 ? '📤 Dodaj produkt' : '⛔ Osiągnięto limit dzienny' ?>
            </button>
        </form>
    </div>
</div>

<script>
// Licznik znaków
const opisField = document.getElementById('opisField');
const charCount = document.getElementById('charCount');
const maxLength = <?= MAX_DESCRIPTION_LENGTH ?>;

opisField.addEventListener('input', function() {
    const length = this.value.length;
    charCount.textContent = length + ' / ' + maxLength;
    
    if (length > maxLength * 0.9) {
        charCount.classList.add('warning');
    } else {
        charCount.classList.remove('warning');
    }
    
    if (length >= maxLength) {
        charCount.classList.add('danger');
    } else {
        charCount.classList.remove('danger');
    }
});

// Obsługa wielu plików
const fileInput = document.getElementById('fileInput');
const fileLabel = document.getElementById('fileLabel');
const fileCount = document.getElementById('fileCount');
const previewContainer = document.getElementById('previewContainer');
let selectedFiles = [];

fileInput.addEventListener('change', function() {
    handleFiles(this.files);
});

function handleFiles(files) {
    if (files.length > 5) {
        alert('Możesz dodać maksymalnie 5 zdjęć!');
        fileInput.value = '';
        return;
    }
    
    selectedFiles = Array.from(files);
    updatePreview();
}

function updatePreview() {
    previewContainer.innerHTML = '';
    
    if (selectedFiles.length > 0) {
        fileCount.textContent = `✓ Wybrano ${selectedFiles.length} ${selectedFiles.length === 1 ? 'zdjęcie' : 'zdjęć'}`;
        fileLabel.classList.add('has-file');
        previewContainer.classList.add('show');
        
        selectedFiles.forEach((file, index) => {
            const reader = new FileReader();
            reader.onload = function(e) {
                const previewItem = document.createElement('div');
                previewItem.className = 'preview-item';
                previewItem.innerHTML = `
                    <img src="${e.target.result}" class="preview-image" alt="Podgląd ${index + 1}">
                    <button type="button" class="remove-image" onclick="removeImage(${index})">×</button>
                `;
                previewContainer.appendChild(previewItem);
            };
            reader.readAsDataURL(file);
        });
    } else {
        fileCount.textContent = '';
        fileLabel.classList.remove('has-file');
        previewContainer.classList.remove('show');
    }
}

function removeImage(index) {
    selectedFiles.splice(index, 1);
    
    // Aktualizuj input file
    const dt = new DataTransfer();
    selectedFiles.forEach(file => dt.items.add(file));
    fileInput.files = dt.files;
    
    updatePreview(); 
}

// Drag & Drop
fileLabel.addEventListener('dragover', function(e) {
    e.preventDefault();
    this.style.borderColor = 'var(--primary)';
    this.style.background = '#ffe0cc';
});

fileLabel.addEventListener('dragleave', function() {
    this.style.borderColor = '#ccc';
    this.style.background = 'var(--light)';
});

fileLabel.addEventListener('drop', function(e) {
    e.preventDefault();
    this.style.borderColor = '#ccc';
    this.style.background = 'var(--light)';
    
    const files = e.dataTransfer.files;
    if (files.length > 0) {
        handleFiles(files);
    }
});
</script>

</body>
</html>