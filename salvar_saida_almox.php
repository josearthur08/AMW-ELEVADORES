<?php
include "connection.php";
header('Content-Type: application/json; charset=utf-8');

$raw = file_get_contents('php://input');
$data = json_decode($raw, true);
if(!$data || !is_array($data)) $data = $_POST;

// Aceita tanto os nomes antigos quanto os novos: mapa de campos
$produto = isset($data['produto']) ? $data['produto'] : (isset($data['nome']) ? $data['nome'] : '');
$quantidade = isset($data['quantidade']) ? intval($data['quantidade']) : (isset($data['quantidade']) ? intval($data['quantidade']) : 0);
$funcionario = isset($data['funcionario']) ? $data['funcionario'] : (isset($data['func']) ? $data['func'] : '');
$data_saida = isset($data['data_saida']) ? $data['data_saida'] : (isset($data['data']) ? $data['data'] : '');

if(!$produto || $quantidade <= 0 || !$funcionario || !$data_saida){
    http_response_code(400);
    echo json_encode(['error' => 'Campos ausentes ou inválidos']);
    exit;
}

// Criar tabela se não existir com colunas compatíveis
$create = "CREATE TABLE IF NOT EXISTS saida_almoxarifado (
    id INT AUTO_INCREMENT PRIMARY KEY,
    produto VARCHAR(255) NOT NULL,
    quantidade INT NOT NULL,
    funcionario VARCHAR(255) NOT NULL,
    data_saida DATE NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
if(!$conn->query($create)){
    http_response_code(500);
    echo json_encode(['error' => 'Erro ao criar tabela: ' . $conn->error]);
    exit;
}

$stmt = $conn->prepare("INSERT INTO saida_almoxarifado (produto,quantidade,funcionario,data_saida) VALUES (?,?,?,?)");
if(!$stmt){ http_response_code(500); echo json_encode(['error'=>$conn->error]); exit; }
$stmt->bind_param('siss',$produto,$quantidade,$funcionario,$data_saida);
if(!$stmt->execute()){ http_response_code(500); echo json_encode(['error'=>$stmt->error]); exit; }

echo json_encode(['success'=>true,'id'=>$stmt->insert_id]);
?>