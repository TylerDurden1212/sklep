<?php
session_start();
if(empty($_SESSION['user_id'])) exit;

$host="192.168.1.202"; $user="sklepuser"; $pass="twojehaslo"; $dbname="sklep";
$conn=new mysqli($host,$user,$pass,$dbname);
$conn->set_charset("utf8mb4");

$user_id=$_SESSION['user_id'];
$stmt=$conn->prepare("SELECT COUNT(*) AS cnt FROM chats WHERE user_to=? AND read_status=0");
$stmt->bind_param("i",$user_id);
$stmt->execute();
$res=$stmt->get_result()->fetch_assoc();
$stmt->close();
$conn->close();

header('Content-Type: application/json; charset=utf-8');
echo json_encode(['count'=>intval($res['cnt'])]);
