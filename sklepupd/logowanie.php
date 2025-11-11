<?php
session_start();

// Jeśli już zalogowany, przekieruj
if (!empty($_SESSION['user_id'])) {
    header("Location: index.php");
    exit;
}

$host = "192.168.1.202";
$user = "sklepuser";
$pass = "twojehaslo";
$dbname = "sklep";

$conn = new mysqli($host, $user, $pass, $dbname);
if ($conn->connect_error) {
    die("Błąd połączenia: " . $conn->connect_error);
}
$conn->set_charset("utf8mb4");

$errors = [];
$success = $_GET['success'] ?? '';
$search = '';
$unread_count = 0;

// Logowanie
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
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
                $_SESSION['user_id'] = (int)$row['id'];
                $_SESSION['username'] = $row['username'];
                $_SESSION['email'] = $row['email'];
                
                $updateStmt = $conn->prepare("UPDATE logi SET last_activity = NOW() WHERE id = ?");
                $updateStmt->bind_param("i", $row['id']);
                $updateStmt->execute();
                $updateStmt->close();
                
                header("Location: index.php");
                exit;
            } else {
                $errors[] = "Nieprawidłowe hasło.";
            }
        } else {
            $errors[] = "Nie znaleziono użytkownika.";
        }
        $stmt->close();
    }
}
?>
<!doctype html>
<html lang="pl">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Logowanie - GórkaSklep.pl</title>
<link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><text y='.9em' font-size='90'>🏔️</text></svg>">
<style>
* { margin: 0; padding: 0; box-sizing: border-box; }

:root {
    --primary: #ff8c42;
    --secondary: #ff6b35;
    --accent: #ffa500;
    --dark: #2c3e50;
    --light: #fff5f0;
    --white: #ffffff;
}

body {
    font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
    background: linear-gradient(135deg, #ff8c42 0%, #ff6b35 100%);
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

/* Login Container */
.login-wrapper {
    display: flex;
    justify-content: center;
    align-items: center;
    padding: 40px 20px;
    min-height: calc(100vh - 100px);
}

.login-container {
    display: grid;
    grid-template-columns: 1fr 1fr;
    max-width: 1000px;
    width: 100%;
    background: white;
    border-radius: 25px;
    overflow: hidden;
    box-shadow: 0 20px 60px rgba(0,0,0,0.3);
    animation: slideUp 0.5s;
}

@keyframes slideUp {
    from {
        opacity: 0;
        transform: translateY(50px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.left-side {
    background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
    color: white;
    padding: 60px 40px;
    display: flex;
    flex-direction: column;
    justify-content: center;
}

.logo-section {
    text-align: center;
    margin-bottom: 30px;
}

.logo-icon {
    font-size: 80px;
    margin-bottom: 15px;
}

.logo-text {
    font-size: 36px;
    font-weight: 900;
    margin-bottom: 10px;
}

.logo-subtitle {
    font-size: 16px;
    opacity: 0.9;
}

.left-side .subtitle {
    font-size: 18px;
    opacity: 0.9;
    line-height: 1.6;
    margin-bottom: 40px;
    text-align: center;
}

.feature {
    display: flex;
    align-items: center;
    gap: 15px;
    margin-bottom: 25px;
}

.feature-icon {
    width: 60px;
    height: 60px;
    background: rgba(255,255,255,0.2);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 28px;
    flex-shrink: 0;
}

.feature-text h3 {
    font-size: 18px;
    margin-bottom: 5px;
}

.feature-text p {
    font-size: 14px;
    opacity: 0.8;
}

.right-side {
    padding: 60px 50px;
    display: flex;
    flex-direction: column;
    justify-content: center;
}

.login-header {
    text-align: center;
    margin-bottom: 40px;
}

.login-header h2 {
    font-size: 36px;
    color: #333;
    margin-bottom: 10px;
}

.login-header p {
    color: #666;
    font-size: 15px;
}

.alert {
    padding: 15px 20px;
    border-radius: 12px;
    margin-bottom: 20px;
    font-size: 14px;
}

.alert-error {
    background: #fee;
    color: #c33;
    border-left: 4px solid #c33;
}

.alert-success {
    background: #efe;
    color: #3c3;
    border-left: 4px solid #3c3;
}

.form-group {
    margin-bottom: 25px;
}

label {
    display: block;
    margin-bottom: 8px;
    color: #333;
    font-weight: 600;
    font-size: 14px;
}

input[type=text], input[type=password] {
    width: 100%;
    padding: 14px 18px;
    border: 2px solid #e0e0e0;
    border-radius: 12px;
    font-size: 15px;
    transition: 0.3s;
}

input:focus {
    outline: none;
    border-color: var(--primary);
    box-shadow: 0 0 0 3px rgba(255, 140, 66, 0.1);
}

button {
    width: 100%;
    padding: 16px;
    background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
    color: white;
    border: none;
    border-radius: 12px;
    font-size: 16px;
    font-weight: bold;
    cursor: pointer;
    transition: 0.3s;
}

button:hover {
    transform: translateY(-2px);
    box-shadow: 0 10px 25px rgba(255, 140, 66, 0.4);
}

.divider {
    text-align: center;
    margin: 30px 0;
    color: #999;
    font-size: 14px;
    position: relative;
}

.divider::before,
.divider::after {
    content: '';
    position: absolute;
    top: 50%;
    width: 40%;
    height: 1px;
    background: #e0e0e0;
}

.divider::before { left: 0; }
.divider::after { right: 0; }

.register-link {
    text-align: center;
    margin-top: 20px;
}

.register-link a {
    color: var(--primary);
    text-decoration: none;
    font-weight: 600;
    font-size: 15px;
}

.register-link a:hover {
    text-decoration: underline;
}

.back-link {
    text-align: center;
    margin-top: 20px;
}

.back-link a {
    color: #999;
    text-decoration: none;
    font-size: 14px;
}

.back-link a:hover {
    color: var(--primary);
}

.password-toggle {
    position: relative;
}

.password-toggle input {
    padding-right: 50px;
}

.toggle-icon {
    position: absolute;
    right: 15px;
    top: 50%;
    transform: translateY(-50%);
    cursor: pointer;
    font-size: 20px;
    user-select: none;
}

@media (max-width: 768px) {
    .login-container {
        grid-template-columns: 1fr;
    }
    
    .left-side {
        padding: 40px 30px;
    }
    
    .right-side {
        padding: 40px 30px;
    }
    
    .logo-text {
        font-size: 28px;
    }
}

@media (max-width: 968px) {
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
    
    .logo-icon {
        font-size: 36px;
    }
    
    .logo-main {
        font-size: 22px;
    }
    
    .login-container {
        grid-template-columns: 1fr;
    }
    
    .left-side {
        padding: 40px 30px;
    }
    
    .right-side {
        padding: 40px 30px;
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
            <a href="rejestracja.php" class="btn-add">📝 Zarejestruj się</a>
        </div>
    </div>
</div>

<div class="login-wrapper">
<div class="login-container">
    <div class="left-side">
        <div class="logo-section">
            <div class="logo-icon"><img src = "./images/logo_strona.png" height = "100px" width = "100px"></div>
            <div class="logo-text">GórkaSklep.pl</div>
            <div class="logo-subtitle">Szkolny Sklep Internetowy</div>
        </div>
        <p class="subtitle">Zaloguj się, aby kontynuować kupowanie i sprzedawanie w naszej szkolnej społeczności.</p>
        
        <div class="feature">
            <div class="feature-icon">🚀</div>
            <div class="feature-text">
                <h3>Szybkie transakcje</h3>
                <p>Kupuj i sprzedawaj w kilka kliknięć</p>
            </div>
        </div>
        
        <div class="feature">
            <div class="feature-icon">💬</div>
            <div class="feature-text">
                <h3>Czat w czasie rzeczywistym</h3>
                <p>Komunikuj się bezpośrednio ze sprzedawcami</p>
            </div>
        </div>
        
        <div class="feature">
            <div class="feature-icon">🏫</div>
            <div class="feature-text">
                <h3>Szkolna społeczność</h3>
                <p>Handluj ze swoimi kolegami z LO II Rabka-Zdrój</p>
            </div>
        </div>
    </div>

    <div class="right-side">
        <div class="login-header">
            <h2>Zaloguj się</h2>
            <p>Wprowadź swoje dane logowania</p>
        </div>

        <?php if ($success): ?>
            <div class="alert alert-success">
                ✅ <?= htmlspecialchars($success) ?>
            </div>
        <?php endif; ?>
        
        <?php foreach ($errors as $error): ?>
            <div class="alert alert-error">
                ⚠️ <?= htmlspecialchars($error) ?>
            </div>
        <?php endforeach; ?>

        <form method="post">
            <div class="form-group">
                <label>Nazwa użytkownika lub e-mail</label>
                <input type="text" 
                       name="username_or_email" 
                       placeholder="Wpisz swoją nazwę lub email"
                       value="<?= htmlspecialchars($_POST['username_or_email'] ?? '') ?>"
                       required 
                       autofocus>
            </div>
            
            <div class="form-group">
                <label>Hasło</label>
                <div class="password-toggle">
                    <input type="password" 
                           name="password" 
                           id="password"
                           placeholder="Wpisz swoje hasło"
                           required>
                    <span class="toggle-icon" onclick="togglePassword()">👁️</span>
                </div>
            </div>
            
            <button type="submit">🔑 Zaloguj się</button>
        </form>

        <div class="divider">lub</div>

        <div class="register-link">
            Nie masz konta? <a href="rejestracja.php">Zarejestruj się tutaj</a>
        </div>

        <div class="back-link">
            <a href="index.php">← Powrót do sklepu</a>
        </div>
    </div>
</div>
</div>

<script>
function togglePassword() {
    const passwordInput = document.getElementById('password');
    const toggleIcon = document.querySelector('.toggle-icon');
    
    if (passwordInput.type === 'password') {
        passwordInput.type = 'text';
        toggleIcon.textContent = '🙈';
    } else {
        passwordInput.type = 'password';
        toggleIcon.textContent = '👁️';
    }
}
</script>
<script>
// Zabezpieczenie przed DevTools i Console
(function() {
    'use strict';
    
    // Wykryj czy DevTools jest otwarty
    const devtools = {
        isOpen: false,
        orientation: null
    };
    
    const threshold = 160;
    
    const emitEvent = (isOpen, orientation) => {
        if (devtools.isOpen !== isOpen || devtools.orientation !== orientation) {
            if (isOpen) {
                // DevTools został otwarty - przekieruj
                window.location.href = 'index.php';
            }
        }
        devtools.isOpen = isOpen;
        devtools.orientation = orientation;
    };
    
    setInterval(() => {
        const widthThreshold = window.outerWidth - window.innerWidth > threshold;
        const heightThreshold = window.outerHeight - window.innerHeight > threshold;
        const orientation = widthThreshold ? 'vertical' : 'horizontal';
        
        if (heightThreshold || widthThreshold) {
            emitEvent(true, orientation);
        } else {
            emitEvent(false, null);
        }
    }, 500);
    
    // Wyłącz prawy przycisk myszy
    document.addEventListener('contextmenu', e => e.preventDefault());
    
    // Wyłącz F12, Ctrl+Shift+I, Ctrl+Shift+C, Ctrl+Shift+J, Ctrl+U
    document.addEventListener('keydown', e => {
        if (
            e.keyCode === 123 || // F12
            (e.ctrlKey && e.shiftKey && e.keyCode === 73) || // Ctrl+Shift+I
            (e.ctrlKey && e.shiftKey && e.keyCode === 67) || // Ctrl+Shift+C
            (e.ctrlKey && e.shiftKey && e.keyCode === 74) || // Ctrl+Shift+J
            (e.ctrlKey && e.keyCode === 85) // Ctrl+U
        ) {
            e.preventDefault();
            return false;
        }
    });
    
    // Nadpisz console
    if (window.location.hostname !== 'localhost' && window.location.hostname !== '127.0.0.1') {
        console.log = function() {};
        console.warn = function() {};
        console.error = function() {};
        console.info = function() {};
        console.debug = function() {};
    }
})();

// Zabezpieczenie przed nieskończonymi pętlami fetch
(function() {
    const originalFetch = window.fetch;
    let fetchCount = 0;
    let lastReset = Date.now();
    const LIMIT = 50; // Max 50 requestów
    const WINDOW = 10000; // W ciągu 10 sekund
    
    window.fetch = function(...args) {
        const now = Date.now();
        
        // Reset licznika co 10 sekund
        if (now - lastReset > WINDOW) {
            fetchCount = 0;
            lastReset = now;
        }
        
        fetchCount++;
        
        // Blokada jeśli przekroczono limit
        if (fetchCount > LIMIT) {
            console.warn('Zbyt wiele żądań - blokada fetch()');
            return Promise.reject(new Error('Rate limit exceeded'));
        }
        
        return originalFetch.apply(this, args);
    };
})();
</script>
</body>
</html>
<?php $conn->close(); ?>