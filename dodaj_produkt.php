<?php
// dodaj_produkt.php

session_start();

// Sprawdzenie czy użytkownik jest zalogowany
if (empty($_SESSION['user_id'])) {
    header("Location: logowanie.php");
    exit;
}

$host = "localhost";
$user = "root";
$pass = "";
$dbname = "sklep";

$conn = new mysqli($host, $user, $pass, $dbname);
if ($conn->connect_error) {
    die("Błąd połączenia z bazą: " . $conn->connect_error);
}

$msg = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $nazwa = trim($_POST["nazwa"]);
    $opis = trim($_POST["opis"]);
    $cena = floatval($_POST["cena"]);

    // Walidacja
    if (strlen($opis) > 300) {
        $msg = "⚠️ Opis nie może mieć więcej niż 300 znaków.";
    } elseif ($cena > 1000) {
        $msg = "⚠️ Cena nie może przekraczać 1000 zł.";
    } elseif ($nazwa === "" || $opis === "" || $cena <= 0) {
        $msg = "⚠️ Wszystkie pola muszą być wypełnione poprawnie.";
    } else {
        // Obsługa zdjęcia
        $uploadDir = __DIR__ . "/uploads/";
        if (!file_exists($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }
        $zdjeciePath = null;

        if (isset($_FILES["zdjecie"]) && $_FILES["zdjecie"]["error"] === UPLOAD_ERR_OK) {
            $ext = strtolower(pathinfo($_FILES["zdjecie"]["name"], PATHINFO_EXTENSION));
            if (in_array($ext, ["jpg", "jpeg", "png", "gif"])) {
                $fileName = uniqid("foto_") . "." . $ext;
                $target = $uploadDir . $fileName;
                if (move_uploaded_file($_FILES["zdjecie"]["tmp_name"], $target)) {
                    $zdjeciePath = "uploads/" . $fileName;
                } else {
                    $msg = "❌ Błąd zapisu zdjęcia.";
                }
            } else {
                $msg = "⚠️ Dozwolone formaty: JPG, PNG, GIF.";
            }
        }

        if ($msg === "") {
            $stmt = $conn->prepare("INSERT INTO produkty (nazwa, opis, cena, zdjecie, id_sprzedawcy, data_dodania) VALUES (?, ?, ?, ?, ?, NOW())");
            $userId = (int)$_SESSION['user_id'];
            $stmt->bind_param("ssdsi", $nazwa, $opis, $cena, $zdjeciePath, $userId);
            if ($stmt->execute()) {
                $msg = "✅ Produkt został dodany przez " . htmlspecialchars($_SESSION['username']) . "!";
            } else {
                $msg = "❌ Błąd podczas dodawania: " . $stmt->error;
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
<title>Dodaj produkt</title>
<style>
body {
  font-family: Arial, sans-serif;
  background: #f3f4f6;
  display: flex;
  justify-content: center;
  align-items: center;
  height: 100vh;
}
form {
  background: white;
  padding: 25px 35px;
  border-radius: 12px;
  box-shadow: 0 3px 10px rgba(0,0,0,0.1);
  width: 400px;
}
h2 { text-align: center; color: #333; }
label { display:block; margin-top:12px; color:#444; }
input[type=text], textarea, input[type=number], input[type=file] {
  width: 100%; padding: 8px; border: 1px solid #ccc;
  border-radius: 6px; margin-top: 4px;
}
button {
  margin-top: 15px; width: 100%; padding: 10px;
  background: #2563eb; color: white; border: none;
  border-radius: 6px; cursor: pointer; font-weight: bold;
}
button:hover { background: #1e40af; }
.msg { margin-top: 15px; text-align:center; color:#333; font-weight:bold; }
a { display:block; text-align:center; margin-top:10px; color:#2563eb; text-decoration:none; }
</style>
</head>
<body>

<form method="post" enctype="multipart/form-data">
  <h2>Dodaj produkt (<?= htmlspecialchars($_SESSION['username']) ?>)</h2>

  <label>Nazwa produktu:</label>
  <input type="text" name="nazwa" maxlength="100" required>

  <label>Opis (max 300 znaków):</label>
  <textarea name="opis" maxlength="300" rows="4" required></textarea>

  <label>Cena (max 1000 zł):</label>
  <input type="number" step="0.01" name="cena" min="0" max="1000" required>

  <label>Zdjęcie:</label>
  <input type="file" name="zdjecie" accept="image/*">

  <button type="submit">Dodaj produkt</button>

  <a href="sklep.php">🛒 Zobacz sklep</a>

  <?php if ($msg): ?>
    <div class="msg"><?= htmlspecialchars($msg) ?></div>
  <?php endif; ?>
</form>

</body>
</html>
