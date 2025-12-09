<?php
include "connection.php";
header('Content-Type: application/json; charset=utf-8');

$raw = file_get_contents('php://input');
$data = json_decode($raw, true);
if (!is_array($data)) $data = $_POST;

file_put_contents(__DIR__."/awm_log.txt", date('c') . " - SALVAR_SAIDA RAW_LEN=" . strlen($raw) . " data=" . json_encode($data) . "\n", FILE_APPEND);

$nome = $data['nome'] ?? '';
$obra = $data['obra'] ?? '';
$equipe = $data['equipe'] ?? '';
$data_field = $data['data'] ?? '';

if (!$nome && !$obra && !$equipe && !$data_field) {
	echo json_encode(['ok' => false, 'error' => 'Nenhum dado recebido']);
	exit;
}

// Ensure table exists
$create = "CREATE TABLE IF NOT EXISTS saida (
	id INT AUTO_INCREMENT PRIMARY KEY,
	nome VARCHAR(1024),
	obra VARCHAR(255),
	equipe VARCHAR(255),
	data VARCHAR(64),
	created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci";
if (!$conn->query($create)) {
	file_put_contents(__DIR__."/awm_log.txt", date('c') . " - SALVAR_SAIDA CREATE_ERROR: " . $conn->error . "\n", FILE_APPEND);
	echo json_encode(['ok' => false, 'error' => 'Erro ao garantir tabela: ' . $conn->error]);
	exit;
}

$stmt = $conn->prepare("INSERT INTO saida (nome,obra,equipe,data) VALUES (?,?,?,?)");
if (!$stmt) {
	file_put_contents(__DIR__."/awm_log.txt", date('c') . " - SALVAR_SAIDA PREPARE_ERROR: " . $conn->error . "\n", FILE_APPEND);
	echo json_encode(['ok' => false, 'error' => $conn->error]);
	exit;
}

$stmt->bind_param("ssss", $nome, $obra, $equipe, $data_field);
if (!$stmt->execute()) {
	file_put_contents(__DIR__."/awm_log.txt", date('c') . " - SALVAR_SAIDA EXEC_ERROR: " . $stmt->error . "\n", FILE_APPEND);
	echo json_encode(['ok' => false, 'error' => $stmt->error]);
	exit;
}

echo json_encode(['ok' => true, 'id' => $conn->insert_id]);
exit;
?>
