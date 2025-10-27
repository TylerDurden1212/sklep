<?php
session_start();

// Jeśli już zalogowany, przekieruj
if (!empty($_SESSION['user_id'])) {
    header("Location: index.php");
    exit;
}

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
$formData = [
    'username' => '',
    'email' => ''
];

// Rejestracja
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $password2 = $_POST['password2'] ?? '';
    
    // Zapisz dane formularza
    $formData['username'] = $username;
    $formData['email'] = $email;

    // Walidacja
    if ($username === '' || $email === '' || $password === '') {
        $errors[] = "Wszystkie pola są wymagane.";
    } elseif (strlen($username) < 3) {
        $errors[] = "Nazwa użytkownika musi mieć co najmniej 3 znaki.";
    } elseif (strlen($username) > 50) {
        $errors[] = "Nazwa użytkownika może mieć maksymalnie 50 znaków.";
    } elseif (!preg_match('/^[a-zA-Z0-9_]+$/', $username)) {
        $errors[] = "Nazwa użytkownika może zawierać tylko litery, cyfry i podkreślenia.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Nieprawidłowy adres e-mail.";
    } elseif (strlen($password) < 6) {
        $errors[] = "Hasło musi mieć co najmniej 6 znaków.";
    } elseif ($password !== $password2) {
        $errors[] = "Hasła nie są identyczne.";
    } else {
        // Sprawdź unikalność username i email
        $stmt = $conn->prepare("SELECT id FROM logi WHERE username = ? OR email = ? LIMIT 1");
        $stmt->bind_param("ss", $username, $email);
        $stmt->execute();
        $stmt->store_result();
        
        if ($stmt->num_rows > 0) {
            $errors[] = "Nazwa użytkownika lub adres e-mail już istnieje.";
        } else {
            // Utwórz konto
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $ins = $conn->prepare("INSERT INTO logi (username, password, email, created_at) VALUES (?, ?, ?, NOW())");
            $ins->bind_param("sss", $username, $hash, $email);
            
            if ($ins->execute()) {
                // Sukces - przekieruj do logowania
                $ins->close();
                $stmt->close();
                $conn->close();
                header("Location: login.php?success=" . urlencode("Konto utworzone pomyślnie! Możesz się teraz zalogować."));
                exit;
            } else {
                $errors[] = "Błąd przy tworzeniu konta. Spróbuj ponownie.";
            }
            $ins->close();
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
<title>Rejestracja - Sklep Online</title>
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

.register-container {
    display: grid;
    grid-template-columns: 1fr 1fr;
    max-width: 1100px;
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
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    padding: 60px 40px;
    display: flex;
    flex-direction: column;
    justify-content: center;
}

.left-side h1 {
    font-size: 42px;
    margin-bottom: 20px;
}

.left-side .subtitle {
    font-size: 18px;
    opacity: 0.9;
    line-height: 1.6;
    margin-bottom: 40px;
}

.benefit {
    display: flex;
    align-items: flex-start;
    gap: 15px;
    margin-bottom: 25px;
}

.benefit-icon {
    width: 50px;
    height: 50px;
    background: rgba(255,255,255,0.2);
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 24px;
    flex-shrink: 0;
}

.benefit-text h3 {
    font-size: 18px;
    margin-bottom: 5px;
}

.benefit-text p {
    font-size: 14px;
    opacity: 0.85;
    line-height: 1.5;
}

.right-side {
    padding: 60px 50px;
    display: flex;
    flex-direction: column;
    justify-content: center;
}

.register-header {
    text-align: center;
    margin-bottom: 35px;
}

.register-header h2 {
    font-size: 36px;
    color: #333;
    margin-bottom: 10px;
}

.register-header p {
    color: #666;
    font-size: 15px;
}

.alert {
    padding: 15px 20px;
    border-radius: 12px;
    margin-bottom: 20px;
    font-size: 14px;
    animation: shake 0.5s;
}

@keyframes shake {
    0%, 100% { transform: translateX(0); }
    25% { transform: translateX(-10px); }
    75% { transform: translateX(10px); }
}

.alert-error {
    background: #fee;
    color: #c33;
    border-left: 4px solid #c33;
}

.form-group {
    margin-bottom: 20px;
}

label {
    display: block;
    margin-bottom: 8px;
    color: #333;
    font-weight: 600;
    font-size: 14px;
}

.required {
    color: #ef4444;
}

input[type=text], input[type=email], input[type=password] {
    width: 100%;
    padding: 14px 18px;
    border: 2px solid #e0e0e0;
    border-radius: 12px;
    font-size: 15px;
    transition: 0.3s;
}

input:focus {
    outline: none;
    border-color: #667eea;
    box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
}

input.error {
    border-color: #ef4444;
}

.input-hint {
    font-size: 12px;
    color: #999;
    margin-top: 5px;
}

.password-strength {
    margin-top: 8px;
    height: 4px;
    background: #e0e0e0;
    border-radius: 2px;
    overflow: hidden;
    display: none;
}

.password-strength.show {
    display: block;
}

.strength-bar {
    height: 100%;
    transition: 0.3s;
    border-radius: 2px;
}

.strength-weak { width: 33%; background: #ef4444; }
.strength-medium { width: 66%; background: #f59e0b; }
.strength-strong { width: 100%; background: #10b981; }

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

button {
    width: 100%;
    padding: 16px;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    border: none;
    border-radius: 12px;
    font-size: 16px;
    font-weight: bold;
    cursor: pointer;
    transition: 0.3s;
    margin-top: 10px;
}

button:hover {
    transform: translateY(-2px);
    box-shadow: 0 10px 25px rgba(102, 126, 234, 0.4);
}

button:disabled {
    opacity: 0.6;
    cursor: not-allowed;
}

.terms {
    font-size: 12px;
    color: #666;
    text-align: center;
    margin-top: 15px;
    line-height: 1.6;
}

.terms a {
    color: #667eea;
    text-decoration: none;
}

.terms a:hover {
    text-decoration: underline;
}

.divider {
    text-align: center;
    margin: 25px 0;
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

.divider::before {
    left: 0;
}

.divider::after {
    right: 0;
}

.login-link {
    text-align: center;
    margin-top: 20px;
}

.login-link a {
    color: #667eea;
    text-decoration: none;
    font-weight: 600;
    font-size: 15px;
}

.login-link a:hover {
    text-decoration: underline;
}

.back-link {
    text-align: center;
    margin-top: 15px;
}

.back-link a {
    color: #999;
    text-decoration: none;
    font-size: 14px;
}

.back-link a:hover {
    color: #667eea;
}

@media (max-width: 768px) {
    .register-container {
        grid-template-columns: 1fr;
    }
    
    .left-side {
        padding: 40px 30px;
    }
    
    .right-side {
        padding: 40px 30px;
    }
    
    .left-side h1 {
        font-size: 32px;
    }
}
</style>
</head>
<body>

<div class="register-container">
    <div class="left-side">
        <h1>🎉 Dołącz do nas!</h1>
        <p class="subtitle">Stwórz konto i rozpocznij kupowanie oraz sprzedawanie produktów już dziś.</p>
        
        <div class="benefit">
            <div class="benefit-icon">✨</div>
            <div class="benefit-text">
                <h3>Darmowe konto</h3>
                <p>Rejestracja i korzystanie z platformy jest całkowicie bezpłatne</p>
            </div>
        </div>
        
        <div class="benefit">
            <div class="benefit-icon">🚀</div>
            <div class="benefit-text">
                <h3>Szybki start</h3>
                <p>Dodaj swój pierwszy produkt w mniej niż minutę</p>
            </div>
        </div>
        
        <div class="benefit">
            <div class="benefit-icon">💬</div>
            <div class="benefit-text">
                <h3>Łatwa komunikacja</h3>
                <p>Czatuj ze sprzedawcami i kupującymi w czasie rzeczywistym</p>
            </div>
        </div>
        
        <div class="benefit">
            <div class="benefit-icon">🔒</div>
            <div class="benefit-text">
                <h3>Bezpieczne dane</h3>
                <p>Twoje informacje są chronione szyfrowa</p>
            </div>
        </div>
    </div>

    <div class="right-side">
        <div class="register-header">
            <h2>Utwórz konto</h2>
            <p>Wypełnij formularz poniżej</p>
        </div>

        <?php foreach ($errors as $error): ?>
            <div class="alert alert-error">
                ⚠️ <?= htmlspecialchars($error) ?>
            </div>
        <?php endforeach; ?>

        <form method="post" id="registerForm">
            <div class="form-group">
                <label>Nazwa użytkownika <span class="required">*</span></label>
                <input type="text" 
                       name="username" 
                       id="username"
                       minlength="3" 
                       maxlength="50" 
                       value="<?= htmlspecialchars($formData['username']) ?>"
                       placeholder="np. jan_kowalski"
                       required 
                       autofocus>
                <div class="input-hint">3-50 znaków, tylko litery, cyfry i podkreślenia</div>
            </div>
            
            <div class="form-group">
                <label>Adres e-mail <span class="required">*</span></label>
                <input type="email" 
                       name="email" 
                       id="email"
                       maxlength="100" 
                       value="<?= htmlspecialchars($formData['email']) ?>"
                       placeholder="twoj@email.com"
                       required>
            </div>
            
            <div class="form-group">
                <label>Hasło <span class="required">*</span></label>
                <div class="password-toggle">
                    <input type="password" 
                           name="password" 
                           id="password"
                           minlength="6"
                           placeholder="Minimum 6 znaków"
                           required>
                    <span class="toggle-icon" onclick="togglePassword('password')">👁️</span>
                </div>
                <div class="password-strength" id="passwordStrength">
                    <div class="strength-bar" id="strengthBar"></div>
                </div>
            </div>
            
            <div class="form-group">
                <label>Powtórz hasło <span class="required">*</span></label>
                <div class="password-toggle">
                    <input type="password" 
                           name="password2" 
                           id="password2"
                           placeholder="Wpisz hasło ponownie"
                           required>
                    <span class="toggle-icon" onclick="togglePassword('password2')">👁️</span>
                </div>
            </div>
            
            <button type="submit">🎯 Utwórz konto</button>
            
            <div class="terms">
                Rejestrując się, akceptujesz nasze <a href="#">Warunki korzystania</a> i <a href="#">Politykę prywatności</a>
            </div>
        </form>

        <div class="divider">lub</div>

        <div class="login-link">
            Masz już konto? <a href="logowanie.php">Zaloguj się tutaj</a>
        </div>

        <div class="back-link">
            <a href="index.php">← Powrót do sklepu</a>
        </div>
    </div>
</div>

<script>
function togglePassword(fieldId) {
    const passwordInput = document.getElementById(fieldId);
    const toggleIcon = passwordInput.nextElementSibling;
    
    if (passwordInput.type === 'password') {
        passwordInput.type = 'text';
        toggleIcon.textContent = '🙈';
    } else {
        passwordInput.type = 'password';
        toggleIcon.textContent = '👁️';
    }
}

// Sprawdzanie siły hasła
const passwordInput = document.getElementById('password');
const strengthBar = document.getElementById('strengthBar');
const passwordStrength = document.getElementById('passwordStrength');

passwordInput.addEventListener('input', function() {
    const password = this.value;
    
    if (password.length === 0) {
        passwordStrength.classList.remove('show');
        return;
    }
    
    passwordStrength.classList.add('show');
    
    let strength = 0;
    
    // Długość
    if (password.length >= 6) strength++;
    if (password.length >= 10) strength++;
    
    // Zawiera cyfry
    if (/\d/.test(password)) strength++;
    
    // Zawiera wielkie litery
    if (/[A-Z]/.test(password)) strength++;
    
    // Zawiera znaki specjalne
    if (/[^A-Za-z0-9]/.test(password)) strength++;
    
    // Ustaw pasek siły
    strengthBar.className = 'strength-bar';
    if (strength <= 2) {
        strengthBar.classList.add('strength-weak');
    } else if (strength <= 4) {
        strengthBar.classList.add('strength-medium');
    } else {
        strengthBar.classList.add('strength-strong');
    }
});

// Walidacja formularza
const registerForm = document.getElementById('registerForm');
const password2Input = document.getElementById('password2');

registerForm.addEventListener('submit', function(e) {
    const password = passwordInput.value;
    const password2 = password2Input.value;
    
    if (password !== password2) {
        e.preventDefault();
        alert('❌ Hasła nie są identyczne!');
        password2Input.focus();
        return false;
    }
    
    if (password.length < 6) {
        e.preventDefault();
        alert('❌ Hasło musi mieć co najmniej 6 znaków!');
        passwordInput.focus();
        return false;
    }
});

// Walidacja nazwy użytkownika w czasie rzeczywistym
const usernameInput = document.getElementById('username');
usernameInput.addEventListener('input', function() {
    const username = this.value;
    const pattern = /^[a-zA-Z0-9_]+$/;
    
    if (username.length > 0 && !pattern.test(username)) {
        this.classList.add('error');
    } else {
        this.classList.remove('error');
    }
});
</script>

</body>
</html>
<?php $conn->close(); ?>