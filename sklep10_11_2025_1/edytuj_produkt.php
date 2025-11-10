<?php
session_start();
require_once 'config.php';

requireLogin();

$conn = getDBConnection();
$product_id = intval($_GET['id'] ?? 0);
$user_id = $_SESSION['user_id'];

if ($product_id <= 0) {
    header("Location: profil.php");
    exit;
}

// Pobierz produkt
$stmt = $conn->prepare("SELECT * FROM produkty WHERE id = ? AND id_sprzedawcy = ?");
$stmt->bind_param("ii", $product_id, $user_id);
$stmt->execute();
$product = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$product) {
    header("Location: profil.php");
    exit;
}

// Sprawdź czy produkt jest sprzedany
if ($product['is_sold'] == 1) {
    setFlashMessage('error', 'Nie możesz edytować sprzedanego produktu');
    header("Location: produkt.php?id=$product_id");
    exit;
}

$msg = "";
$msgType = "";
$unread_count = getUnreadCount($user_id, $conn);
$search = '';

// Dekoduj zdjęcia
$existingImages = [];
if (!empty($product['zdjecie'])) {
    $decoded = json_decode($product['zdjecie'], true);
    $existingImages = is_array($decoded) ? $decoded : [$product['zdjecie']];
}

// Obsługa formularza
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nazwa = trim($_POST['nazwa'] ?? '');
    $opis = trim($_POST['opis'] ?? '');
    $cena = floatval($_POST['cena'] ?? 0);
    $kategoria = $_POST['kategoria'] ?? 'inne';
    
    // Walidacja
    if (empty($nazwa) || empty($opis) || $cena <= 0) {
        $msg = "Wszystkie pola muszą być wypełnione poprawnie.";
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
        // Obsługa usuwania zdjęć
        $deletedImages = $_POST['deleted_images'] ?? [];
        if (!empty($deletedImages) && is_array($deletedImages)) {
            foreach ($deletedImages as $imgPath) {
                $fullPath = __DIR__ . "/" . $imgPath;
                if (file_exists($fullPath)) {
                    unlink($fullPath);
                }
                // Usuń ze списку istniejących
                $existingImages = array_diff($existingImages, [$imgPath]);
            }
        }
        
        // Obsługa nowych zdjęć
        $newImages = [];
        if (isset($_FILES['new_images']) && is_array($_FILES['new_images']['name'])) {
            $fileCount = count($_FILES['new_images']['name']);
            $totalImages = count($existingImages) + $fileCount;
            
            if ($totalImages > 5) {
                $msg = "Możesz mieć maksymalnie 5 zdjęć łącznie.";
                $msgType = "error";
            } else {
                for ($i = 0; $i < $fileCount; $i++) {
                    if ($_FILES['new_images']['error'][$i] === UPLOAD_ERR_OK) {
                        $fileArray = [
                            'name' => $_FILES['new_images']['name'][$i],
                            'type' => $_FILES['new_images']['type'][$i],
                            'tmp_name' => $_FILES['new_images']['tmp_name'][$i],
                            'error' => $_FILES['new_images']['error'][$i],
                            'size' => $_FILES['new_images']['size'][$i]
                        ];
                        
                        $uploadResult = uploadImage($fileArray, 'product');
                        if ($uploadResult['success']) {
                            $newImages[] = $uploadResult['path'];
                        } else {
                            $msg = "Błąd przesyłania zdjęcia: " . $uploadResult['error'];
                            $msgType = "error";
                            break;
                        }
                    }
                }
            }
        }
        
        if (empty($msg)) {
            // Połącz stare i nowe zdjęcia
            $allImages = array_merge(array_values($existingImages), $newImages);
            $zdjeciaJson = !empty($allImages) ? json_encode(array_values($allImages)) : null;
            
            // Aktualizuj produkt
            $stmt = $conn->prepare("UPDATE produkty SET nazwa=?, opis=?, cena=?, zdjecie=?, kategoria=? WHERE id=? AND id_sprzedawcy=?");
            $stmt->bind_param("ssdssi", $nazwa, $opis, $cena, $zdjeciaJson, $kategoria, $product_id, $user_id);
            
            if ($stmt->execute()) {
                $msg = "Produkt został zaktualizowany!";
                $msgType = "success";
                
                // Odśwież dane produktu
                $stmt = $conn->prepare("SELECT * FROM produkty WHERE id = ?");
                $stmt->bind_param("i", $product_id);
                $stmt->execute();
                $product = $stmt->get_result()->fetch_assoc();
                $stmt->close();
                
                $existingImages = [];
                if (!empty($product['zdjecie'])) {
                    $decoded = json_decode($product['zdjecie'], true);
                    $existingImages = is_array($decoded) ? $decoded : [$product['zdjecie']];
                }
                
                header("refresh:2;url=produkt.php?id=$product_id");
            } else {
                $msg = "Błąd podczas aktualizacji: " . $stmt->error;
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
<title>✏️ Edytuj produkt - <?= htmlspecialchars($product['nazwa']) ?></title>
<link rel="icon" href="./images/logo_strona.png">
<style>
* { margin: 0; padding: 0; box-sizing: border-box; }

:root {
    --primary: #ff8c42;
    --secondary: #ff6b35;
    --success: #10b981;
    --danger: #ef4444;
    --dark: #2c3e50;
    --light: #fff5f0;
}

body {
    font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
    background: linear-gradient(135deg, #ff8c42 0%, #ff6b35 100%);
    min-height: 100vh;
}

/* Reuse header styles from dodaj_produkt.php */
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
    from { opacity: 0; transform: translateY(30px); }
    to { opacity: 1; transform: translateY(0); }
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

.alert {
    padding: 18px 24px;
    border-radius: 15px;
    margin-bottom: 25px;
    text-align: center;
    font-weight: 500;
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
}

.char-count {
    text-align: right;
    font-size: 13px;
    color: #999;
    margin-top: 8px;
}

.existing-images {
    display: flex;
    flex-wrap: wrap;
    gap: 15px;
    margin-top: 15px;
}

.existing-image-item {
    position: relative;
    width: calc(33.333% - 10px);
    height: 150px;
}

.existing-image {
    width: 100%;
    height: 100%;
    object-fit: cover;
    border-radius: 12px;
    box-shadow: 0 5px 15px rgba(0,0,0,0.1);
}

.delete-image {
    position: absolute;
    top: 5px;
    right: 5px;
    background: var(--danger);
    color: white;
    border: none;
    border-radius: 50%;
    width: 35px;
    height: 35px;
    cursor: pointer;
    font-size: 20px;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: 0.3s;
}

.delete-image:hover {
    transform: scale(1.1);
}

.file-upload {
    margin-top: 20px;
}

.file-label {
    display: block;
    padding: 30px;
    background: var(--light);
    border: 3px dashed #ccc;
    border-radius: 15px;
    text-align: center;
    cursor: pointer;
    transition: 0.3s;
}

.file-label:hover {
    border-color: var(--primary);
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

button[type="submit"]:hover {
    transform: translateY(-3px);
    box-shadow: 0 10px 25px rgba(255, 140, 66, 0.4);
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

@media (max-width: 768px) {
    .header-content {
        grid-template-columns: 1fr;
    }
    
    .form-card {
        padding: 30px 20px;
    }
    
    .existing-image-item {
        width: calc(50% - 7.5px);
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
        </div>
    </div>
</div>

<div class="container">
    <div class="form-card">
        <a href="produkt.php?id=<?= $product_id ?>" class="back-link">← Powrót do produktu</a>
        
        <div class="form-header">
            <h2>✏️ Edytuj produkt</h2>
            <div class="subtitle">Zaktualizuj informacje o swoim produkcie</div>
        </div>

        <?php if ($msg): ?>
            <div class="alert alert-<?= $msgType ?>">
                <?= $msgType === 'success' ? '✅' : '⚠️' ?> <?= htmlspecialchars($msg) ?>
            </div>
        <?php endif; ?>

        <form method="post" enctype="multipart/form-data" id="editForm">
            <div class="form-group">
                <label>Nazwa produktu <span class="required">*</span></label>
                <input type="text" name="nazwa" maxlength="100" required 
                       value="<?= htmlspecialchars($product['nazwa']) ?>" 
                       placeholder="np. iPhone 13 Pro 256GB">
            </div>

            <div class="form-group">
                <label>Kategoria <span class="required">*</span></label>
                <select name="kategoria" required>
                    <option value="elektronika" <?= $product['kategoria'] == 'elektronika' ? 'selected' : '' ?>>📱 Elektronika</option>
                    <option value="odziez" <?= $product['kategoria'] == 'odziez' ? 'selected' : '' ?>>👕 Odzież</option>
                    <option value="dom" <?= $product['kategoria'] == 'dom' ? 'selected' : '' ?>>🏠 Dom i Ogród</option>
                    <option value="sport" <?= $product['kategoria'] == 'sport' ? 'selected' : '' ?>>⚽ Sport</option>
                    <option value="inne" <?= $product['kategoria'] == 'inne' ? 'selected' : '' ?>>📦 Inne</option>
                </select>
            </div>

            <div class="form-group">
                <label>Opis <span class="required">*</span></label>
                <textarea name="opis" maxlength="<?= MAX_DESCRIPTION_LENGTH ?>" id="opisField" required><?= htmlspecialchars($product['opis']) ?></textarea>
                <div class="char-count" id="charCount"><?= strlen($product['opis']) ?> / <?= MAX_DESCRIPTION_LENGTH ?></div>
            </div>

            <div class="form-group">
                <label>Cena (max <?= number_format(MAX_PRODUCT_PRICE, 2) ?> zł) <span class="required">*</span></label>
                <input type="number" step="0.01" name="cena" min="0.01" max="<?= MAX_PRODUCT_PRICE ?>" required 
                       value="<?= $product['cena'] ?>">
            </div>

            <div class="form-group">
                <label>Obecne zdjęcia</label>
                <?php if (!empty($existingImages)): ?>
                    <div class="existing-images">
                        <?php foreach ($existingImages as $index => $img): ?>
                            <div class="existing-image-item" id="img-<?= $index ?>">
                                <img src="<?= htmlspecialchars($img) ?>" class="existing-image" alt="Zdjęcie <?= $index + 1 ?>">
                                <button type="button" class="delete-image" onclick="markForDeletion('<?= htmlspecialchars($img) ?>', <?= $index ?>)">
                                    ×
                                </button>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <p style="color: #999;">Brak zdjęć</p>
                <?php endif; ?>
                
                <div class="file-upload">
                    <input type="file" name="new_images[]" accept="image/*" id="newImages" multiple hidden>
                    <label for="newImages" class="file-label">
                        📷 Dodaj nowe zdjęcia (max <?= 5 - count($existingImages) ?>)
                    </label>
                </div>
            </div>

            <button type="submit">💾 Zapisz zmiany</button>
        </form>
    </div>
</div>

<script>
// Licznik znaków
const opisField = document.getElementById('opisField');
const charCount = document.getElementById('charCount');
const maxLength = <?= MAX_DESCRIPTION_LENGTH ?>;

opisField.addEventListener('input', function() {
    charCount.textContent = this.value.length + ' / ' + maxLength;
});

// System usuwania zdjęć
const deletedImages = [];

function markForDeletion(imgPath, index) {
    if (confirm('Czy na pewno chcesz usunąć to zdjęcie?')) {
        deletedImages.push(imgPath);
        document.getElementById('img-' + index).remove();
        
        // Dodaj hidden input z listą usuniętych
        const form = document.getElementById('editForm');
        const input = document.createElement('input');
        input.type = 'hidden';
        input.name = 'deleted_images[]';
        input.value = imgPath;
        form.appendChild(input);
    }
}

// Walidacja liczby zdjęć
document.getElementById('newImages').addEventListener('change', function() {
    const existingCount = document.querySelectorAll('.existing-image-item').length;
    const newCount = this.files.length;
    const total = existingCount + newCount;
    
    if (total > 5) {
        alert(`Możesz mieć maksymalnie 5 zdjęć łącznie. Obecnie masz ${existingCount}, wybrano ${newCount}.`);
        this.value = '';
    }
});
</script>

</body>
</html>