<?php
// produkt.php
session_start();

$host = "localhost";
$user = "root";
$pass = "";
$dbname = "sklep";

$conn = new mysqli($host, $user, $pass, $dbname);
if ($conn->connect_error) {
    die("Błąd połączenia: " . $conn->connect_error);
}
$conn->set_charset("utf8mb4");

$id = intval($_GET['id'] ?? 0);

// Pobranie produktu wraz z nazwą sprzedawcy
$result = $conn->query("
    SELECT p.*, l.username AS sprzedawca
    FROM produkty p
    LEFT JOIN logi l ON p.id_sprzedawcy = l.id
    WHERE p.id = $id
");

if ($result->num_rows === 0) {
    die("Nie znaleziono produktu.");
}

$produkt = $result->fetch_assoc();
$conn->close();
?>
<!doctype html>
<html lang="pl">
<head>
<meta charset="utf-8">
<title><?= htmlspecialchars($produkt['nazwa']) ?></title>
<style>
body {
  font-family: Arial, sans-serif;
  background: #f3f4f6;
  display: flex;
  justify-content: center;
  align-items: center;
  min-height: 100vh;
}
.card {
  background: white;
  padding: 25px;
  border-radius: 12px;
  box-shadow: 0 3px 10px rgba(0,0,0,0.2);
  max-width: 600px;
  width: 100%;
}
img {
  width: 100%;
  height: 400px;
  object-fit: cover;
  border-radius: 8px;
}
h2 { margin-top: 15px; color: #222; }
p { color: #555; }
.price {
  font-size: 20px;
  font-weight: bold;
  color: #1e40af;
  margin-top: 10px;
}
.seller {
  margin-top: 12px;
  font-size: 14px;
  color: #888;
}
.seller a {
  color: #2563eb;
  text-decoration: none;
}
.seller a:hover { text-decoration: underline; }
a.back {
  display: inline-block;
  margin-top: 20px;
  background: #2563eb;
  color: white;
  text-decoration: none;
  padding: 10px 16px;
  border-radius: 8px;
}
a.back:hover { background: #1e40af; }
</style>
</head>
<body>

<div class="card">
  <?php if (!empty($produkt['zdjecie'])): ?>
    <img src="<?= htmlspecialchars($produkt['zdjecie']) ?>" alt="Zdjęcie produktu">
  <?php else: ?>
    <img src="https://via.placeholder.com/600x400?text=Brak+zdjecia" alt="Brak zdjęcia">
  <?php endif; ?>

  <h2><?= htmlspecialchars($produkt['nazwa']) ?></h2>
  <p><?= nl2br(htmlspecialchars($produkt['opis'])) ?></p>
  <div class="price"><?= number_format($produkt['cena'], 2) ?> zł</div>

  <div class="seller">
    Sprzedawca: 
    <a href="profil.php?id=<?= $produkt['id_sprzedawcy'] ?>">
      <?= htmlspecialchars($produkt['sprzedawca'] ?? '—') ?>
    </a>
  </div>

  <a class="back" href="sklep.php">⬅️ Powrót do sklepu</a>
</div>

</body>
</html>
