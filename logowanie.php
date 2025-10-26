<?php
// logowanie.php
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

$errors = [];
$success = '';

// Rejestracja
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'register') {
    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $password2 = $_POST['password2'] ?? '';

    if ($username === '' || $email === '' || $password === '') {
        $errors[] = "Wszystkie pola są wymagane.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Nieprawidłowy adres e-mail.";
    } elseif ($password !== $password2) {
        $errors[] = "Hasła nie są takie same.";
    } else {
        // Sprawdź unikalność username/email
        $stmt = $conn->prepare("SELECT id FROM logi WHERE username = ? OR email = ? LIMIT 1");
        $stmt->bind_param("ss", $username, $email);
        $stmt->execute();
        $stmt->store_result();
        if ($stmt->num_rows > 0) {
            $errors[] = "Nazwa użytkownika lub adres e-mail już istnieje.";
        } else {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $ins = $conn->prepare("INSERT INTO logi (username, password, email) VALUES (?, ?, ?)");
            $ins->bind_param("sss", $username, $hash, $email);
            if ($ins->execute()) {
                $success = "Konto utworzone. Możesz się teraz zalogować.";
            } else {
                $errors[] = "Błąd przy tworzeniu konta.";
            }
            $ins->close();
        }
        $stmt->close();
    }
}

// Logowanie
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'login') {
    $usernameOrEmail = trim($_POST['username_or_email'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($usernameOrEmail === '' || $password === '') {
        $errors[] = "Podaj login (lub e-mail) i hasło.";
    } else {
        $stmt = $conn->prepare("SELECT id, username, password, email FROM logi WHERE username = ? OR email = ? LIMIT 1");
        $stmt->bind_param("ss", $usernameOrEmail, $usernameOrEmail);
        $stmt->execute();
        $res = $stmt->get_result();
        if ($row = $res->fetch_assoc()) {
            if (password_verify($password, $row['password'])) {
                // zaloguj
                $_SESSION['user_id'] = (int)$row['id'];
                $_SESSION['username'] = $row['username'];
                $_SESSION['email'] = $row['email'];
                // przekierowanie np. do panelu lub sklepu
                header("Location: sklep.php");
                exit;
            } else {
                $errors[] = "Nieprawidłowe hasło.";
            }
        } else {
            $errors[] = "Brak takiego użytkownika.";
        }
        $stmt->close();
    }
}

?>
<!doctype html>
<html lang="pl">
<head>
<meta charset="utf-8">
<title>Logowanie / Rejestracja</title>
<style>
body{font-family:Arial,Helvetica,sans-serif;background:#f3f4f6;padding:30px}
.wrap{max-width:920px;margin:0 auto;display:grid;grid-template-columns:1fr 1fr;gap:20px}
.card{background:#fff;padding:20px;border-radius:8px;box-shadow:0 3px 10px rgba(0,0,0,0.08)}
h2{margin-top:0}
.alert{padding:10px;border-radius:6px;margin-bottom:10px}
.err{background:#ffefef;color:#900}
.ok{background:#e6ffed;color:#085}
label{display:block;font-size:14px;margin-top:8px}
input[type=text],input[type=email],input[type=password]{width:100%;padding:8px;border:1px solid #ccc;border-radius:6px}
button{margin-top:12px;padding:10px 14px;border:none;border-radius:6px;background:#2563eb;color:#fff;cursor:pointer}
.topbar{margin-bottom:18px;text-align:center}
.logged{background:#fff;padding:12px;border-radius:8px;text-align:center}
a.link{color:#2563eb;text-decoration:none}
</style>
</head>
<body>
<div class="topbar">
  <?php if (!empty($_SESSION['user_id'])): ?>
    <div class="logged">
      Zalogowany jako <strong><?=htmlspecialchars($_SESSION['username'])?></strong> (<?=htmlspecialchars($_SESSION['email'])?>)
      <br><a class="link" href="dodaj_produkt.php">➕ Dodaj produkt</a> • <a class="link" href="logout.php">Wyloguj</a>
    </div>
  <?php else: ?>
    <h1>Logowanie / Rejestracja</h1>
  <?php endif; ?>
</div>

<div class="wrap">
  <!-- Rejestracja -->
  <div class="card">
    <h2>Rejestracja</h2>
    <?php if ($success): ?><div class="alert ok"><?=htmlspecialchars($success)?></div><?php endif; ?>
    <?php foreach ($errors as $e): ?>
      <div class="alert err"><?=htmlspecialchars($e)?></div>
    <?php endforeach; ?>
    <form method="post" action="">
      <input type="hidden" name="action" value="register">
      <label>Nazwa użytkownika
        <input type="text" name="username" maxlength="100" required>
      </label>
      <label>E-mail
        <input type="email" name="email" maxlength="255" required>
      </label>
      <label>Hasło
        <input type="password" name="password" required>
      </label>
      <label>Powtórz hasło
        <input type="password" name="password2" required>
      </label>
      <button type="submit">Zarejestruj się</button>
    </form>
  </div>

  <!-- Logowanie -->
  <div class="card">
    <h2>Logowanie</h2>
    <form method="post" action="">
      <input type="hidden" name="action" value="login">
      <label>Nazwa użytkownika lub e-mail
        <input type="text" name="username_or_email" maxlength="255" required>
      </label>
      <label>Hasło
        <input type="password" name="password" required>
      </label>
      <button type="submit">Zaloguj się</button>
    </form>
  </div>
</div>
</body>
</html>
