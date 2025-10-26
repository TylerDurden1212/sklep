<?php
// sklep.php
session_start();

$host = "localhost";
$user = "root";
$pass = "";
$dbname = "sklep";

$conn = new mysqli($host, $user, $pass, $dbname);
if ($conn->connect_error) {
    die("Błąd połączenia z bazą: " . $conn->connect_error);
}
$conn->set_charset("utf8mb4");

// Pobieramy produkty z nazwą sprzedawcy (LEFT JOIN)
$result = $conn->query("
    SELECT p.*, l.username AS sprzedawca 
    FROM produkty p
    LEFT JOIN logi l ON p.id_sprzedawcy = l.id
    ORDER BY p.data_dodania DESC
");
?>
<!doctype html>
<html lang="pl">
<head>
<meta charset="utf-8">
<title>Sklep</title>
<style>
body {
  font-family: Arial, sans-serif;
  background: #f1f2f6;
  margin: 0;
  padding: 20px;
}
h1 { text-align: center; margin-bottom: 20px; }
.topbar {
  text-align: center;
  margin-bottom: 20px;
}
.topbar a {
  display: inline-block;
  margin: 0 10px;
  padding: 8px 12px;
  background: #2563eb;
  color: white;
  text-decoration: none;
  border-radius: 6px;
}
.topbar a:hover { background: #1e40af; }

.container {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: 20px;
}

.card {
  background: white;
  border-radius: 10px;
  box-shadow: 0 3px 10px rgba(0,0,0,0.1);
  overflow: hidden;
  display: flex;
  flex-direction: column;
  cursor: pointer;
  transition: transform 0.2s, box-shadow 0.2s;
}
.card:hover {
  transform: translateY(-5px);
  box-shadow: 0 5px 15px rgba(0,0,0,0.2);
}
.card img {
  width: 100%;
  height: 380px;
  object-fit: cover;
}
.card-content {
  padding: 12px;
  height: 110px;
  overflow: hidden;
  display: flex;
  flex-direction: column;
  justify-content: space-between;
}
.card h3 {
  margin: 0;
  font-size: 18px;
  color: #222;
}
.card p {
  margin: 4px 0 0 0;
  font-size: 13px;
  color: #555;
  line-height: 1.2;
  flex-grow: 1;
  overflow: hidden;
}
.price {
  font-weight: bold;
  color: #1e40af;
  font-size: 14px;
  margin-top: 3px;
}
.seller {
  font-size: 12px;
  color: #888;
  margin-top: 2px;
}
</style>
</head>
<body>

<h1>🛍️ Sklep</h1>

<div class="topbar">
  <?php if (!empty($_SESSION['user_id'])): ?>
    Zalogowany jako <strong><?= htmlspecialchars($_SESSION['username']) ?></strong> 
    <a href="dodaj_produkt.php">➕ Dodaj produkt</a>
    <a href="logout.php">Wyloguj</a>
  <?php else: ?>
    <a href="logowanie.php">🔑 Zaloguj / Zarejestruj</a>
  <?php endif; ?>
</div>

<div class="container">
  <?php if ($result->num_rows > 0): ?>
    <?php while ($row = $result->fetch_assoc()): ?>
      <div class="card" onclick="window.location='produkt.php?id=<?= $row['id'] ?>'">
        <?php if (!empty($row['zdjecie'])): ?>
          <img src="<?= htmlspecialchars($row['zdjecie']) ?>" alt="Zdjęcie produktu">
        <?php else: ?>
          <img src="https://via.placeholder.com/400x380?text=Brak+zdjecia" alt="Brak zdjęcia">
        <?php endif; ?>
        <div class="card-content">
          <h3><?= htmlspecialchars($row['nazwa']) ?></h3>
          <p><?= htmlspecialchars($row['opis']) ?></p>
          <div class="price"><?= number_format($row['cena'], 2) ?> zł</div>
          <div class="seller">Sprzedawca: <?= htmlspecialchars($row['sprzedawca'] ?? '—') ?></div>
        </div>
      </div>
    <?php endwhile; ?>
  <?php else: ?>
    <p style="text-align:center;width:100%;">Brak produktów w sklepie.</p>
  <?php endif; ?>
</div>
<div class="seller">
    Sprzedawca: 
    <a href="profil.php?id=<?= $row['id_sprzedawcy'] ?>">
        <?= htmlspecialchars($row['sprzedawca'] ?? '—') ?>
    </a>
</div>


</body>
</html>
<?php $conn->close(); ?>
