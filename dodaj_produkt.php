<?php
session_start();
require_once 'config.php';

requireLogin();

$conn = getDBConnection();
$msg = "";
$msgType = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $nazwa = trim($_POST["nazwa"] ?? '');
    $opis = trim($_POST["opis"] ?? '');
    $cena = floatval($_POST["cena"] ?? 0);
    $kategoria = $_POST["kategoria"] ?? 'inne';

    // Walidacja
    if (empty($nazwa) || empty($opis) || $cena <= 0) {
        $msg = "Wszystkie pola muszą być wypełnione poprawnie.";
        $msgType = "error";
    } elseif (strlen($opis) > MAX_DESCRIPTION_LENGTH) {
        $msg = "Opis nie może mieć więcej niż " . MAX_DESCRIPTION_LENGTH . " znaków.";
        $msgType = "error";
    } elseif ($cena > MAX_PRODUCT_PRICE) {
        $msg = "Cena nie może przekraczać " . number_format(MAX_PRODUCT_PRICE, 2) . " zł.";
        $msgType = "error";
    } else {
        $zdjeciePath = null;
        
        // Upload zdjęcia
        if (isset($_FILES["zdjecie"]) && $_FILES["zdjecie"]["error"] === UPLOAD_ERR_OK) {
            $uploadResult = uploadImage($_FILES["zdjecie"], 'product');
            if ($uploadResult['success']) {
                $zdjeciePath = $uploadResult['path'];
            } else {
                $msg = $uploadResult['error'];
                $msgType = "error";
            }
        }

        if (empty($msg)) {
            $stmt = $conn->prepare("INSERT INTO produkty (nazwa, opis, cena, zdjecie, id_sprzedawcy, kategoria, data_dodania) VALUES (?, ?, ?, ?, ?, ?, NOW())");
            $userId = $_SESSION['user_id'];
            $stmt->bind_param("ssdsds", $nazwa, $opis, $cena, $zdjeciePath, $userId, $kategoria);
            
            if ($stmt->execute()) {
                $productId = $stmt->insert_id;
                $msg = "Produkt został dodany pomyślnie!";
                $msgType = "success";
                
                // Przekieruj po 2 sekundach
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
<title>Dodaj produkt - Sklep</title>
<style>
* { margin: 0; padding: 0; box-sizing: border-box; }
body {
    font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    min-height: 100vh;
    display: flex;
    justify-content: center;
    align-items: center;
    padding: 20px;
}

.container {
    background: white;
    padding: 50px;
    border-radius: 25px;
    box-shadow: 0 20px 60px rgba(0,0,0,0.3);
    max-width: 700px;
    width: 100%;
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

.header {
    text-align: center;
    margin-bottom: 40px;
}

.header h2 {
    font-size: 36px;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    margin-bottom: 10px;
}

.subtitle {
    color: #666;
    font-size: 15px;
}

.user-info {
    background: #f8f9fa;
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
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
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

@keyframes slideDown {
    from {
        opacity: 0;
        transform: translateY(-20px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
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
    color: #ef4444;
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
    border-color: #667eea;
    box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
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
    color: #f59e0b;
}

.char-count.danger {
    color: #ef4444;
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
    background: #f8f9fa;
    border: 3px dashed #ccc;
    border-radius: 15px;
    text-align: center;
    cursor: pointer;
    transition: 0.3s;
}

.file-label:hover {
    border-color: #667eea;
    background: #f0f0ff;
}

.file-label.has-file {
    border-color: #10b981;
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

.file-name {
    margin-top: 12px;
    font-size: 14px;
    color: #10b981;
    font-weight: 600;
}

.preview-container {
    margin-top: 15px;
    display: none;
}

.preview-container.show {
    display: block;
}

.preview-image {
    width: 100%;
    max-height: 300px;
    object-fit: cover;
    border-radius: 12px;
    box-shadow: 0 5px 15px rgba(0,0,0,0.1);
}

button {
    width: 100%;
    padding: 16px;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    border: none;
    border-radius: 15px;
    font-size: 17px;
    font-weight: bold;
    cursor: pointer;
    transition: 0.3s;
    margin-top: 10px;
}

button:hover {
    transform: translateY(-3px);
    box-shadow: 0 10px 25px rgba(102, 126, 234, 0.4);
}

button:active {
    transform: translateY(0);
}

.links {
    display: flex;
    justify-content: center;
    gap: 20px;
    margin-top: 25px;
    flex-wrap: wrap;
}

.links a {
    color: #667eea;
    text-decoration: none;
    font-weight: 600;
    padding: 8px 16px;
    border-radius: 20px;
    transition: 0.3s;
}

.links a:hover {
    background: #f0f0ff;
}

.info-box {
    background: #fff9e6;
    border-left: 4px solid #f59e0b;
    padding: 15px 20px;
    border-radius: 10px;
    margin-bottom: 25px;
    font-size: 14px;
    color: #92400e;
    line-height: 1.6;
}

@media (max-width: 768px) {
    .container {
        padding: 30px 20px;
    }
    
    .header h2 {
        font-size: 28px;
    }
}
</style>
</head>
<body>

<div class="container">
    <div class="header">
        <h2>➕ Dodaj nowy produkt</h2>
        <div class="subtitle">Wypełnij formularz, aby dodać ogłoszenie</div>
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
        • Dodaj wyraźne zdjęcie produktu<br>
        • Opisz szczegółowo stan i właściwości<br>
        • Ustal uczciwą cenę
    </div>

    <form method="post" enctype="multipart/form-data" id="productForm">
        <div class="form-group">
            <label>Nazwa produktu <span class="required">*</span></label>
            <input type="text" name="nazwa" maxlength="100" required placeholder="np. iPhone 13 Pro 256GB">
        </div>

        <div class="form-group">
            <label>Kategoria <span class="required">*</span></label>
            <select name="kategoria" required>
                <option value="elektronika">📱 Elektronika</option>
                <option value="odziez">👕 Odzież</option>
                <option value="dom">🏠 Dom i Ogród</option>
                <option value="sport">⚽ Sport</option>
                <option value="inne" selected>📦 Inne</option>
            </select>
        </div>

        <div class="form-group">
            <label>Opis <span class="required">*</span></label>
            <textarea name="opis" maxlength="<?= MAX_DESCRIPTION_LENGTH ?>" id="opisField" required placeholder="Opisz swój produkt jak najdokładniej..."></textarea>
            <div class="char-count" id="charCount">0 / <?= MAX_DESCRIPTION_LENGTH ?></div>
        </div>

        <div class="form-group">
            <label>Cena (max <?= number_format(MAX_PRODUCT_PRICE, 2) ?> zł) <span class="required">*</span></label>
            <input type="number" step="0.01" name="cena" min="0.01" max="<?= MAX_PRODUCT_PRICE ?>" required placeholder="0.00">
        </div>

        <div class="form-group">
            <label>Zdjęcie produktu</label>
            <div class="file-upload">
                <input type="file" name="zdjecie" accept="image/*" id="fileInput" class="file-input">
                <label for="fileInput" class="file-label" id="fileLabel">
                    <div class="file-icon">📷</div>
                    <div class="file-text">Kliknij lub przeciągnij zdjęcie tutaj</div>
                    <div class="file-name" id="fileName"></div>
                </label>
            </div>
            <div class="preview-container" id="previewContainer">
                <img id="imagePreview" class="preview-image" alt="Podgląd">
            </div>
        </div>

        <button type="submit">📤 Dodaj produkt</button>
    </form>

    <div class="links">
        <a href="index.php">← Powrót do sklepu</a>
        <a href="profil.php">👤 Mój profil</a>
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

// Obsługa pliku
const fileInput = document.getElementById('fileInput');
const fileLabel = document.getElementById('fileLabel');
const fileName = document.getElementById('fileName');
const previewContainer = document.getElementById('previewContainer');
const imagePreview = document.getElementById('imagePreview');

fileInput.addEventListener('change', function() {
    if (this.files && this.files[0]) {
        const file = this.files[0];
        fileName.textContent = '✓ ' + file.name;
        fileLabel.classList.add('has-file');
        
        // Podgląd
        const reader = new FileReader();
        reader.onload = function(e) {
            imagePreview.src = e.target.result;
            previewContainer.classList.add('show');
        };
        reader.readAsDataURL(file);
    } else {
        fileName.textContent = '';
        fileLabel.classList.remove('has-file');
        previewContainer.classList.remove('show');
    }
});

// Drag & Drop
fileLabel.addEventListener('dragover', function(e) {
    e.preventDefault();
    this.style.borderColor = '#667eea';
    this.style.background = '#f0f0ff';
});

fileLabel.addEventListener('dragleave', function() {
    this.style.borderColor = '#ccc';
    this.style.background = '#f8f9fa';
});

fileLabel.addEventListener('drop', function(e) {
    e.preventDefault();
    this.style.borderColor = '#ccc';
    this.style.background = '#f8f9fa';
    
    const files = e.dataTransfer.files;
    if (files.length > 0) {
        fileInput.files = files;
        fileInput.dispatchEvent(new Event('change'));
    }
});
</script>

</body>
</html>