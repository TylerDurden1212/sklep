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

$profile_id = intval($_GET['id'] ?? $_SESSION['user_id']);

$msg = '';
$msgType = '';

// Aktualizacja profilu
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $_SESSION['user_id'] == $profile_id) {
    $bio = $_POST['bio'] ?? '';
    $ig = $_POST['ig'] ?? '';
    
    // Obsługa zdjęcia profilowego
    $profilePicturePath = null;
    if (isset($_FILES['profile_picture']) && $_FILES['profile_picture']['error'] === UPLOAD_ERR_OK) {
        $uploadDir = __DIR__ . "/uploads/profiles/";
        if (!file_exists($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }
        
        $ext = strtolower(pathinfo($_FILES['profile_picture']['name'], PATHINFO_EXTENSION));
        if (in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp'])) {
            $fileName = "profile_" . $profile_id . "_" . uniqid() . "." . $ext;
            $target = $uploadDir . $fileName;
            
            if (move_uploaded_file($_FILES['profile_picture']['tmp_name'], $target)) {
                $profilePicturePath = "uploads/profiles/" . $fileName;
                
                // Usuń stare zdjęcie
                $stmt = $conn->prepare("SELECT profile_picture FROM logi WHERE id=?");
                $stmt->bind_param("i", $profile_id);
                $stmt->execute();
                $oldPic = $stmt->get_result()->fetch_assoc();
                $stmt->close();
                
                if ($oldPic && !empty($oldPic['profile_picture'])) {
                    $oldPath = __DIR__ . "/" . $oldPic['profile_picture'];
                    if (file_exists($oldPath)) {
                        unlink($oldPath);
                    }
                }
                
                $stmt = $conn->prepare("UPDATE logi SET profile_picture=? WHERE id=?");
                $stmt->bind_param("si", $profilePicturePath, $profile_id);
                $stmt->execute();
                $stmt->close();
            }
        }
    }
    
    $stmt = $conn->prepare("UPDATE logi SET bio=?, ig_link=? WHERE id=?");
    $stmt->bind_param("ssi", $bio, $ig, $profile_id);
    $stmt->execute();
    $stmt->close();
    
    $msg = "Profil zaktualizowany pomyślnie!";
    $msgType = "success";
}

// Usuwanie produktu
if (isset($_GET['delete']) && $_SESSION['user_id'] == $profile_id) {
    $delete_id = intval($_GET['delete']);
    
    // Pobierz ścieżkę zdjęcia przed usunięciem
    $stmt = $conn->prepare("SELECT zdjecie FROM produkty WHERE id=? AND id_sprzedawcy=?");
    $stmt->bind_param("ii", $delete_id, $profile_id);
    $stmt->execute();
    $prodData = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    
    if ($prodData) {
        // Usuń produkt
        $stmt = $conn->prepare("DELETE FROM produkty WHERE id=? AND id_sprzedawcy=?");
        $stmt->bind_param("ii", $delete_id, $profile_id);
        $stmt->execute();
        $stmt->close();
        
        // Usuń zdjęcie z serwera
        if (!empty($prodData['zdjecie'])) {
            $imgPath = __DIR__ . "/" . $prodData['zdjecie'];
            if (file_exists($imgPath)) {
                unlink($imgPath);
            }
        }
        
        $msg = "Produkt został usunięty!";
        $msgType = "success";
    }
}

$stmt = $conn->prepare("SELECT * FROM logi WHERE id=?");
$stmt->bind_param("i", $profile_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$user) {
    die("Nie znaleziono użytkownika");
}

$stmt = $conn->prepare("SELECT * FROM produkty WHERE id_sprzedawcy=? ORDER BY data_dodania DESC");
$stmt->bind_param("i", $profile_id);
$stmt->execute();
$products = $stmt->get_result();
$stmt->close();
?>
<!doctype html>
<html lang="pl">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= htmlspecialchars($user['username']) ?> - Profil</title>
<style>
* { margin: 0; padding: 0; box-sizing: border-box; }
body {
    font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    min-height: 100vh;
    padding: 20px;
}

.container {
    max-width: 1200px;
    margin: 0 auto;
}

.back-link {
    display: inline-block;
    background: white;
    padding: 10px 20px;
    border-radius: 25px;
    text-decoration: none;
    color: #667eea;
    font-weight: bold;
    margin-bottom: 20px;
    transition: 0.3s;
}

.back-link:hover {
    transform: translateY(-2px);
    box-shadow: 0 5px 15px rgba(255,255,255,0.3);
}

.profile-header {
    background: white;
    border-radius: 20px;
    padding: 40px;
    box-shadow: 0 10px 40px rgba(0,0,0,0.2);
    margin-bottom: 30px;
}

.profile-top {
    display: flex;
    align-items: flex-start;
    gap: 30px;
    margin-bottom: 30px;
}

.avatar-section {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 15px;
}

.avatar {
    width: 150px;
    height: 150px;
    border-radius: 50%;
    object-fit: cover;
    box-shadow: 0 10px 30px rgba(102, 126, 234, 0.3);
    border: 5px solid white;
}

.avatar-placeholder {
    width: 150px;
    height: 150px;
    border-radius: 50%;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 60px;
    font-weight: bold;
    box-shadow: 0 10px 30px rgba(102, 126, 234, 0.3);
    border: 5px solid white;
}

.change-avatar-btn {
    background: #667eea;
    color: white;
    padding: 8px 16px;
    border-radius: 20px;
    border: none;
    cursor: pointer;
    font-size: 13px;
    transition: 0.3s;
}

.change-avatar-btn:hover {
    background: #5568d3;
}

.profile-info {
    flex: 1;
}

.profile-info h1 {
    font-size: 36px;
    color: #333;
    margin-bottom: 10px;
}

.profile-email {
    color: #999;
    font-size: 14px;
    margin-bottom: 20px;
}

.profile-stats {
    display: flex;
    gap: 30px;
    margin-top: 15px;
}

.stat {
    text-align: center;
    padding: 15px 25px;
    background: #f8f9fa;
    border-radius: 12px;
}

.stat-value {
    font-size: 28px;
    font-weight: bold;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
}

.stat-label {
    font-size: 13px;
    color: #999;
    margin-top: 5px;
}

.profile-edit-form {
    display: grid;
    gap: 20px;
}

.form-group {
    display: flex;
    flex-direction: column;
    gap: 8px;
}

.form-group label {
    font-weight: 600;
    color: #555;
    font-size: 14px;
}

.form-group textarea,
.form-group input[type="text"] {
    width: 100%;
    padding: 12px;
    border: 2px solid #e0e0e0;
    border-radius: 10px;
    font-family: inherit;
    font-size: 14px;
    transition: 0.3s;
}

.form-group textarea {
    resize: vertical;
    min-height: 100px;
}

.form-group textarea:focus,
.form-group input:focus {
    outline: none;
    border-color: #667eea;
}

.file-upload-wrapper {
    position: relative;
}

.file-upload-input {
    display: none;
}

.file-upload-label {
    display: block;
    padding: 12px;
    background: #f8f9fa;
    border: 2px dashed #ccc;
    border-radius: 10px;
    text-align: center;
    cursor: pointer;
    transition: 0.3s;
}

.file-upload-label:hover {
    border-color: #667eea;
    background: #f0f0ff;
}

.file-name {
    margin-top: 8px;
    font-size: 13px;
    color: #666;
}

.btn {
    padding: 14px 28px;
    border: none;
    border-radius: 12px;
    font-weight: bold;
    cursor: pointer;
    transition: 0.3s;
    font-size: 15px;
}

.btn-primary {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
}

.btn-primary:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 20px rgba(102, 126, 234, 0.4);
}

.alert {
    padding: 15px 20px;
    border-radius: 12px;
    margin-bottom: 20px;
    border-left: 4px solid;
    font-weight: 500;
}

.alert-success {
    background: #d4edda;
    color: #155724;
    border-color: #28a745;
}

.alert-error {
    background: #f8d7da;
    color: #721c24;
    border-color: #dc3545;
}

.bio-display {
    background: #f8f9fa;
    padding: 20px;
    border-radius: 12px;
    color: #666;
    line-height: 1.8;
    margin-bottom: 15px;
}

.ig-link {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    color: #667eea;
    text-decoration: none;
    font-weight: 600;
    padding: 10px 20px;
    background: #f0f0ff;
    border-radius: 25px;
    transition: 0.3s;
}

.ig-link:hover {
    background: #e0e0ff;
    transform: translateX(3px);
}

.products-section h2 {
    color: white;
    font-size: 28px;
    margin-bottom: 20px;
    text-align: center;
    text-shadow: 0 2px 10px rgba(0,0,0,0.2);
}

.products-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
    gap: 25px;
}

.product-card {
    background: white;
    border-radius: 15px;
    overflow: hidden;
    box-shadow: 0 5px 15px rgba(0,0,0,0.2);
    transition: 0.3s;
    position: relative;
}

.product-card:hover {
    transform: translateY(-8px);
    box-shadow: 0 15px 35px rgba(0,0,0,0.3);
}

.product-image {
    width: 100%;
    height: 220px;
    object-fit: cover;
    background: #f0f0f0;
    cursor: pointer;
}

.product-content {
    padding: 20px;
}

.product-name {
    font-size: 18px;
    font-weight: bold;
    color: #333;
    margin-bottom: 10px;
    cursor: pointer;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
}

.product-name:hover {
    color: #667eea;
}

.product-price {
    font-size: 22px;
    font-weight: bold;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    margin-bottom: 12px;
}

.product-meta {
    display: flex;
    justify-content: space-between;
    align-items: center;
    font-size: 12px;
    color: #999;
    margin-bottom: 15px;
}

.product-actions {
    display: flex;
    gap: 10px;
}

.btn-small {
    flex: 1;
    padding: 10px;
    border: none;
    border-radius: 8px;
    font-size: 13px;
    cursor: pointer;
    transition: 0.3s;
    text-decoration: none;
    text-align: center;
    font-weight: 600;
}

.btn-view {
    background: #667eea;
    color: white;
}

.btn-view:hover {
    background: #5568d3;
}

.btn-delete {
    background: #dc3545;
    color: white;
}

.btn-delete:hover {
    background: #c82333;
}

.empty-state {
    grid-column: 1/-1;
    text-align: center;
    color: white;
    padding: 60px 20px;
    background: rgba(255,255,255,0.1);
    border-radius: 15px;
}

.empty-state h3 {
    font-size: 24px;
    margin-bottom: 10px;
}

@media (max-width: 768px) {
    .profile-top {
        flex-direction: column;
        align-items: center;
        text-align: center;
    }
    
    .profile-stats {
        justify-content: center;
        flex-wrap: wrap;
    }
    
    .products-grid {
        grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
        gap: 15px;
    }
}
</style>
</head>
<body>

<div class="container">
    <a href="index.php" class="back-link">← Powrót do sklepu</a>

    <div class="profile-header">
        <?php if ($msg): ?>
            <div class="alert alert-<?= $msgType ?>">
                <?= htmlspecialchars($msg) ?>
            </div>
        <?php endif; ?>

        <div class="profile-top">
            <div class="avatar-section">
                <?php if (!empty($user['profile_picture'])): ?>
                    <img src="<?= htmlspecialchars($user['profile_picture']) ?>" alt="Avatar" class="avatar" id="avatarPreview" onerror="this.style.display='none'; document.getElementById('avatarPlaceholder').style.display='flex';">
                    <div class="avatar-placeholder" id="avatarPlaceholder" style="display:none;">
                        <?= strtoupper(substr($user['username'], 0, 1)) ?>
                    </div>
                <?php else: ?>
                    <div class="avatar-placeholder" id="avatarPreview">
                        <?= strtoupper(substr($user['username'], 0, 1)) ?>
                    </div>
                <?php endif; ?>
                
                <?php if ($_SESSION['user_id'] == $profile_id): ?>
                    <button class="change-avatar-btn" onclick="document.getElementById('profilePictureInput').click()">
                        📷 Zmień zdjęcie
                    </button>
                <?php endif; ?>
            </div>
            
            <div class="profile-info">
                <h1><?= htmlspecialchars($user['username']) ?></h1>
                <div class="profile-email">📧 <?= htmlspecialchars($user['email']) ?></div>
                
                <div class="profile-stats">
                    <div class="stat">
                        <div class="stat-value"><?= $products->num_rows ?></div>
                        <div class="stat-label">Produkty</div>
                    </div>
                    <div class="stat">
                        <div class="stat-value">
                            <?= !empty($user['created_at']) ? date('Y', strtotime($user['created_at'])) : date('Y') ?>
                        </div>
                        <div class="stat-label">Dołączył</div>
                    </div>
                    <div class="stat">
                        <div class="stat-value">⭐</div>
                        <div class="stat-label">Zweryfikowany</div>
                    </div>
                </div>
            </div>
        </div>

        <?php if ($_SESSION['user_id'] == $profile_id): ?>
            <form method="post" enctype="multipart/form-data" class="profile-edit-form" id="profileForm">
                <input type="file" 
                       name="profile_picture" 
                       id="profilePictureInput" 
                       accept="image/*" 
                       style="display:none;" 
                       onchange="this.form.submit()">
                
                <div class="form-group">
                    <label>📝 Opis/Bio</label>
                    <textarea name="bio" maxlength="500" placeholder="Opowiedz coś o sobie..."><?= htmlspecialchars($user['bio'] ?? '') ?></textarea>
                </div>
                
                <div class="form-group">
                    <label>📷 Link do Instagrama</label>
                    <input type="text" 
                           name="ig" 
                           value="<?= htmlspecialchars($user['ig_link'] ?? '') ?>"
                           placeholder="https://instagram.com/twoja_nazwa">
                </div>
                
                <button type="submit" class="btn btn-primary">💾 Zapisz zmiany</button>
            </form>
        <?php else: ?>
            <?php if (!empty($user['bio'])): ?>
                <div class="form-group">
                    <label>📝 O użytkowniku</label>
                    <div class="bio-display"><?= nl2br(htmlspecialchars($user['bio'])) ?></div>
                </div>
            <?php endif; ?>
            
            <?php if (!empty($user['ig_link'])): ?>
                <a href="<?= htmlspecialchars($user['ig_link']) ?>" target="_blank" class="ig-link">
                    📷 Zobacz Instagram
                </a>
            <?php endif; ?>
        <?php endif; ?>
    </div>

    <div class="products-section">
        <h2>
            <?= $_SESSION['user_id'] == $profile_id ? '🛍️ Twoje produkty' : '🛍️ Produkty użytkownika' ?>
        </h2>
        
        <div class="products-grid">
            <?php if ($products->num_rows > 0): ?>
                <?php while ($p = $products->fetch_assoc()): ?>
                    <div class="product-card">
                        <?php if (!empty($p['zdjecie'])): ?>
                            <img src="<?= htmlspecialchars($p['zdjecie']) ?>" 
                                 class="product-image" 
                                 onclick="window.location='produkt.php?id=<?= $p['id'] ?>'">
                        <?php else: ?>
                            <img src="https://via.placeholder.com/280x220?text=Brak+zdjęcia" 
                                 class="product-image"
                                 onclick="window.location='produkt.php?id=<?= $p['id'] ?>'">
                        <?php endif; ?>
                        
                        <div class="product-content">
                            <div class="product-name" onclick="window.location='produkt.php?id=<?= $p['id'] ?>'">
                                <?= htmlspecialchars($p['nazwa']) ?>
                            </div>
                            <div class="product-price"><?= number_format($p['cena'], 2) ?> zł</div>
                            
                            <div class="product-meta">
                                <span>📅 <?= date('d.m.Y', strtotime($p['data_dodania'])) ?></span>
                                <span><?= ucfirst($p['kategoria'] ?? 'inne') ?></span>
                            </div>
                            
                            <?php if ($_SESSION['user_id'] == $profile_id): ?>
                                <div class="product-actions">
                                    <a href="produkt.php?id=<?= $p['id'] ?>" class="btn-small btn-view">
                                        👁️ Zobacz
                                    </a>
                                    <a href="profil.php?id=<?= $profile_id ?>&delete=<?= $p['id'] ?>" 
                                       class="btn-small btn-delete"
                                       onclick="return confirm('Czy na pewno chcesz usunąć ten produkt?\n\n<?= htmlspecialchars($p['nazwa']) ?>')">
                                        🗑️ Usuń
                                    </a>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endwhile; ?>
            <?php else: ?>
                <div class="empty-state">
                    <h3>📦 Brak produktów</h3>
                    <p><?= $_SESSION['user_id'] == $profile_id ? 'Dodaj swój pierwszy produkt!' : 'Ten użytkownik nie ma jeszcze produktów' ?></p>
                    <?php if ($_SESSION['user_id'] == $profile_id): ?>
                        <br>
                        <a href="dodaj_produkt.php" class="btn btn-primary" style="display:inline-block;text-decoration:none;">
                            ➕ Dodaj produkt
                        </a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

</body>
</html>
<?php $conn->close(); ?>