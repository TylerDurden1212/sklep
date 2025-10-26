<?php
session_start();
if(empty($_SESSION['user_id'])){
    header("Location: logowanie.php");
    exit;
}

$host="localhost"; $user="root"; $pass=""; $dbname="sklep";
$conn=new mysqli($host,$user,$pass,$dbname);
$conn->set_charset("utf8mb4");

// Kto jest właścicielem profilu
$profile_id = intval($_GET['id'] ?? $_SESSION['user_id']);

// Aktualizacja bio/IG jeśli właściciel edytuje
$msg='';
if($_SERVER['REQUEST_METHOD']==='POST' && $_SESSION['user_id']==$profile_id){
    $bio=$_POST['bio'] ?? '';
    $ig=$_POST['ig'] ?? '';
    $stmt=$conn->prepare("UPDATE logi SET bio=?, ig_link=? WHERE id=?");
    $stmt->bind_param("ssi",$bio,$ig,$profile_id);
    $stmt->execute();
    $stmt->close();
    $msg="✅ Profil zaktualizowany!";
}

// Pobranie danych użytkownika
$stmt=$conn->prepare("SELECT * FROM logi WHERE id=?");
$stmt->bind_param("i",$profile_id);
$stmt->execute();
$user=$stmt->get_result()->fetch_assoc();
$stmt->close();

// Pobranie produktów użytkownika
$stmt=$conn->prepare("SELECT * FROM produkty WHERE id_sprzedawcy=? ORDER BY data_dodania DESC");
$stmt->bind_param("i",$profile_id);
$stmt->execute();
$products=$stmt->get_result();
$stmt->close();
?>
<!doctype html>
<html lang="pl">
<head>
<meta charset="utf-8">
<title><?=htmlspecialchars($user['username'])?></title>
<style>
body{font-family:Arial;background:#f3f4f6;margin:0;padding:20px;}
.profile{max-width:800px;margin:0 auto;}
.header{display:flex;align-items:center;justify-content:space-between;background:white;padding:15px;border-radius:8px;box-shadow:0 3px 10px rgba(0,0,0,0.1);}
.header h2{margin:0;}
.header .chat-btn{width:40px;height:40px;border-radius:50%;background:#2563eb;color:white;display:flex;align-items:center;justify-content:center;font-size:20px;cursor:pointer;position:relative;}
.header .chat-btn .badge{position:absolute;top:-5px;right:-5px;width:16px;height:16px;background:red;color:white;border-radius:50%;font-size:10px;text-align:center;line-height:16px;display:flex;justify-content:center;align-items:center;}
.bio, .ig-link{margin:10px 0;}
.products{display:grid;grid-template-columns:repeat(3,1fr);gap:15px;margin-top:20px;}
.card{background:white;border-radius:8px;padding:10px;box-shadow:0 2px 6px rgba(0,0,0,0.1);cursor:pointer;transition:0.2s;}
.card img{width:100%;height:120px;object-fit:cover;border-radius:6px;}
.card h4{margin:5px 0;font-size:14px;}
.card .price{font-weight:bold;color:#1e40af;}
button{padding:8px 10px;background:#2563eb;color:white;border:none;border-radius:6px;cursor:pointer;margin-top:8px;}
</style>
</head>
<body>

<div class="profile">
    <div class="header">
        <h2><?=htmlspecialchars($user['username'])?></h2>
        <div class="chat-btn" id="chatBtn">
            💬<span class="badge" id="notifCount">0</span>
        </div>
    </div>

    <?php if($msg): ?>
        <p style="color:green;font-weight:bold;"><?=htmlspecialchars($msg)?></p>
    <?php endif; ?>

    <?php if($_SESSION['user_id']==$profile_id): ?>
    <form method="post">
        <div class="bio"><label>Opis/Bio:</label><br>
            <textarea name="bio" rows="3" style="width:100%;"><?=htmlspecialchars($user['bio'])?></textarea>
        </div>
        <div class="ig-link"><label>Link IG:</label><br>
            <input type="text" name="ig" value="<?=htmlspecialchars($user['ig_link'])?>" style="width:100%;">
        </div>
        <button type="submit">Zapisz</button>
    </form>
    <?php else: ?>
        <p class="bio"><?=htmlspecialchars($user['bio'])?></p>
        <?php if(!empty($user['ig_link'])): ?>
            <p class="ig-link"><a href="<?=htmlspecialchars($user['ig_link'])?>" target="_blank">Instagram</a></p>
        <?php endif; ?>
    <?php endif; ?>

    <h3>Produkty użytkownika:</h3>
    <div class="products">
        <?php while($p=$products->fetch_assoc()): ?>
            <div class="card" onclick="window.location='produkt.php?id=<?=$p['id']?>'">
                <?php if(!empty($p['zdjecie'])): ?>
                    <img src="<?=htmlspecialchars($p['zdjecie'])?>" alt="">
                <?php else: ?>
                    <img src="https://via.placeholder.com/150x120?text=Brak+zdjęcia" alt="">
                <?php endif; ?>
                <h4><?=htmlspecialchars($p['nazwa'])?></h4>
                <div class="price"><?=number_format($p['cena'],2)?> zł</div>
            </div>
        <?php endwhile; ?>
    </div>
</div>

<script>
const notifCount = document.getElementById('notifCount');
const chatBtn = document.getElementById('chatBtn');

async function checkMessages(){
    try {
        const resp = await fetch('check_messages.php', {cache:'no-store'});
        if(!resp.ok) return;
        const data = await resp.json();
        notifCount.textContent = data.count;
        notifCount.style.display = data.count > 0 ? 'flex' : 'none';
    } catch(e){}
}

// Odśwież co 5 sekund
setInterval(checkMessages, 5000);
checkMessages(); // pierwsze sprawdzenie

chatBtn.addEventListener('click', ()=>{
    // Przekierowanie do czatu z właścicielem profilu
    window.location.href = 'czat.php?to=<?= $profile_id ?>';
});
</script>

</body>
</html>
