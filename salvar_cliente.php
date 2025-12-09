<?php
include "connection.php";
header('Content-Type: application/json; charset=utf-8');

$nome = isset($_POST['nome']) ? $_POST['nome'] : ''; 
if(!$nome){
	http_response_code(400);
	echo json_encode(['error'=>'Nome ausente']);
	exit;
}

$stmt = $conn->prepare("INSERT INTO clientes (nome) VALUES (?)");
if(!$stmt){ http_response_code(500); echo json_encode(['error'=>$conn->error]); exit; }
$stmt->bind_param("s", $nome);
if(!$stmt->execute()){ http_response_code(500); echo json_encode(['error'=>$stmt->error]); exit; }

echo json_encode(['success'=>true,'id'=>$stmt->insert_id]);
?>
