<?php
session_start();
if(empty($_SESSION['user_id'])){ header("Location: logowanie.php"); exit; }

$host="localhost"; $user="root"; $pass=""; $dbname="sklep";
$conn = new mysqli($host,$user,$pass,$dbname);
$conn->set_charset("utf8mb4");

$from_id = $_SESSION['user_id'];
$produkt_id = intval($_GET['produkt_id'] ?? 0);

// Pobranie produktu i sprzedawcy
$stmt = $conn->prepare("SELECT p.*, l.username AS sprzedawca FROM produkty p LEFT JOIN logi l ON p.id_sprzedawcy=l.id WHERE p.id=?");
$stmt->bind_param("i",$produkt_id);
$stmt->execute();
$produkt = $stmt->get_result()->fetch_assoc();
$stmt->close();

if(!$produkt){ die("Nie znaleziono produktu."); }

$to_id = $produkt['id_sprzedawcy'];

// Wysyłanie wiadomości
if($_SERVER['REQUEST_METHOD']==='POST' && !empty($_POST['message'])){
    $msg = trim($_POST['message']);
    if($msg!==''){
        $stmt=$conn->prepare("INSERT INTO chats (user_from,user_to,produkt_id,message) VALUES (?,?,?,?)");
        $stmt->bind_param("iiis",$from_id,$to_id,$produkt_id,$msg);
        $stmt->execute();
        $stmt->close();
    }
    if(!empty($_SERVER['HTTP_X_REQUESTED_WITH'])){ // AJAX
        header('Content-Type: application/json'); echo json_encode(['status'=>'ok']); exit;
    } else {
        header("Location: czat.php?produkt_id=$produkt_id"); exit;
    }
}

// Pobieranie wiadomości AJAX
if(isset($_GET['fetch']) && $_GET['fetch']==1){
    $stmt=$conn->prepare("
        SELECT c.*, l.username AS from_name
        FROM chats c
        LEFT JOIN logi l ON c.user_from=l.id
        WHERE c.produkt_id=? AND ((c.user_from=? AND c.user_to=?) OR (c.user_from=? AND c.user_to=?))
        ORDER BY c.created_at ASC
    ");
    $stmt->bind_param("iiiii",$produkt_id,$from_id,$to_id,$to_id,$from_id);
    $stmt->execute();
    $messages=$stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($messages,JSON_UNESCAPED_UNICODE);
    exit;
}

function h($s){ return htmlspecialchars($s,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8'); }
?>
<!doctype html>
<html lang="pl">
<head>
<meta charset="utf-8">
<title>Czat: <?=h($produkt['sprzedawca'])?> – <?=h($produkt['nazwa'])?></title>
<style>
body{font-family:Arial;padding:20px;background:#f3f4f6;}
.chat-box{max-width:600px;margin:0 auto;background:white;padding:15px;border-radius:8px;box-shadow:0 3px 10px rgba(0,0,0,0.1);}
.messages{height:400px;overflow:auto;padding:10px;border-radius:6px;border:1px solid #ccc;background:#fafafb;}
.msg{margin:6px 0;padding:8px;border-radius:6px;max-width:80%;}
.from{background:#e6f0ff;text-align:left;}
.to{background:#d1ffd1;text-align:right;margin-left:auto;}
form{margin-top:10px;display:flex;gap:6px;}
form input[type=text]{flex:1;padding:8px;border-radius:6px;border:1px solid #ccc;}
form button{padding:8px 12px;background:#2563eb;color:white;border:none;border-radius:6px;cursor:pointer;}
</style>
</head>
<body>

<div class="chat-box">
<h2>Czat: <?=h($produkt['sprzedawca'])?> – <?=h($produkt['nazwa'])?></h2>
<div class="messages" id="messages"></div>

<form id="chatForm">
<input type="text" name="message" id="messageInput" placeholder="Napisz wiadomość..." required>
<button type="submit">Wyślij</button>
</form>
</div>

<script>
const form=document.getElementById('chatForm');
const input=document.getElementById('messageInput');
const messagesDiv=document.getElementById('messages');
const fromId=<?=$from_id?>;
const toId=<?=$to_id?>;
const produktId=<?=$produkt_id?>;

async function fetchMessages(){
    const resp=await fetch('czat.php?produkt_id='+produktId+'&fetch=1',{cache:'no-store'});
    if(!resp.ok) return;
    const msgs=await resp.json();
    messagesDiv.innerHTML='';
    for(const m of msgs){
        const div=document.createElement('div');
        div.className='msg '+(m.user_from==fromId?'to':'from');
        const strong=document.createElement('strong'); strong.textContent=m.from_name+': ';
        const span=document.createElement('span'); span.innerHTML=m.message.replace(/\n/g,'<br>');
        div.appendChild(strong); div.appendChild(span);
        const time=document.createElement('div'); time.style.fontSize='10px'; time.textContent=m.created_at;
        div.appendChild(time);
        messagesDiv.appendChild(div);
    }
    messagesDiv.scrollTop=messagesDiv.scrollHeight;
}

form.addEventListener('submit',async function(e){
    e.preventDefault();
    const data=new FormData(form);
    await fetch('czat.php?produkt_id='+produktId,{
        method:'POST', body:data, headers:{'X-Requested-With':'XMLHttpRequest'}
    });
    input.value=''; fetchMessages();
});

fetchMessages(); setInterval(fetchMessages,2000);
</script>

</body>
</html>
