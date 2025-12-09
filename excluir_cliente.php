<?php
include "connection.php";

header('Content-Type: application/json; charset=utf-8');

// Excluir cliente e histórico associado
$id = isset($_POST['id']) ? intval($_POST['id']) : 0;
if($id <= 0){
    http_response_code(400);
    echo json_encode(["error" => "ID inválido"]);
    exit;
}

// Remover histórico primeiro
$stmt = $conn->prepare("DELETE FROM historico_cliente WHERE cliente_id = ?");
if($stmt){
    $stmt->bind_param("i", $id);
    $stmt->execute();
}

// Remover cliente
$stmt2 = $conn->prepare("DELETE FROM clientes WHERE id = ?");
if(!$stmt2){
    http_response_code(500);
    echo json_encode(["error" => $conn->error]);
    exit;
}
$stmt2->bind_param("i", $id);
if(!$stmt2->execute()){
    http_response_code(500);
    echo json_encode(["error" => $stmt2->error]);
    exit;
}

echo json_encode(["success" => true]);
?>