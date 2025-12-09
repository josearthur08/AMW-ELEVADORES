<?php
error_reporting(E_ALL);
ini_set('display_errors','0');
include "connection.php";
header('Content-Type: application/json; charset=utf-8');

// parse input: prefer JSON body, then $_POST, then parse_str fallback
$raw = file_get_contents('php://input');
$data = json_decode($raw, true);
if(!$data || !is_array($data)){
	if(!empty($_POST)) {
		$data = $_POST;
	} else {
		parse_str($raw, $parsed);
		if($parsed && is_array($parsed)) $data = $parsed;
	}
}

// Basic validation
$obra = isset($data['obra']) ? $data['obra'] : null;
$endereco = isset($data['endereco']) ? $data['endereco'] : '';
$servico = isset($data['servico']) ? $data['servico'] : null;
$data_field = isset($data['data']) ? $data['data'] : null;
$equipe = isset($data['equipe']) ? $data['equipe'] : null;

if(!$obra || !$servico || !$data_field || !$equipe){
	http_response_code(400);
	echo json_encode(['error'=>'missing_fields','received'=>$data]);
	file_put_contents(__DIR__.'/awm_log.txt', date('c')." - PROG_MISSING_FIELDS: " . json_encode($data) . "\n", FILE_APPEND);
	exit;
}

// ensure table exists and has endereco column
$create = "CREATE TABLE IF NOT EXISTS programacao (
	id INT AUTO_INCREMENT PRIMARY KEY,
	obra VARCHAR(1024),
	endereco VARCHAR(1024),
	servico TEXT,
	`data` VARCHAR(64),
	equipe VARCHAR(255),
	created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci";
if(!$conn->query($create)){
	file_put_contents(__DIR__.'/awm_log.txt', date('c')." - PROG_CREATE_ERROR: " . $conn->error . " - payload: " . json_encode($data) . "\n", FILE_APPEND);
	http_response_code(500);
	echo json_encode(['error'=>'create_failed','details'=>$conn->error]);
	exit;
}

// if table exists but column missing, attempt to add it (safe idempotent)
$res = $conn->query("SHOW COLUMNS FROM programacao LIKE 'endereco'");
if(!$res || $res->num_rows === 0){
	$conn->query("ALTER TABLE programacao ADD COLUMN endereco VARCHAR(1024) NULL");
}

$stmt = $conn->prepare("INSERT INTO programacao (obra,endereco,servico,`data`,equipe) VALUES (?,?,?,?,?)");
if(!$stmt){
	file_put_contents(__DIR__.'/awm_log.txt', date('c')." - PROG_PREPARE_ERROR: " . $conn->error . " - payload: " . json_encode($data) . "\n", FILE_APPEND);
	http_response_code(500);
	echo json_encode(['error'=>'prepare_failed','details'=>$conn->error]);
	exit;
}
$stmt->bind_param('sssss', $obra, $endereco, $servico, $data_field, $equipe);
$ok = $stmt->execute();
if(!$ok){
	file_put_contents(__DIR__.'/awm_log.txt', date('c')." - PROG_EXECUTE_ERROR: " . $stmt->error . " - payload: " . json_encode($data) . "\n", FILE_APPEND);
	http_response_code(500);
	echo json_encode(['error'=>'execute_failed','details'=>$stmt->error]);
	exit;
}
$id = $stmt->insert_id;
file_put_contents(__DIR__.'/awm_log.txt', date('c')." - PROG_INSERTED id=" . $id . " - payload: " . json_encode($data) . "\n", FILE_APPEND);
echo json_encode(['ok'=>true,'success'=>true,'id'=>$id]);
?>
