<?php
include "connection.php";
header('Content-Type: application/json; charset=utf-8');

$raw = file_get_contents("php://input");
$data = json_decode($raw, true);

if (!is_array($data)) {
	$data = $_POST;
}

$nome          = $data['nome'] ?? '';
$empresa       = $data['empresa'] ?? '';
$quantidade    = $data['quantidade'] ?? null;
$vu            = $data['vu'] ?? ($data['valor_unit'] ?? '');
$vt            = $data['vt'] ?? ($data['valor_total'] ?? '');
$data_field    = $data['data'] ?? '';
$horario       = $data['horario'] ?? '';
$recebido_por  = $data['recebido_por'] ?? '';

// LOG SIMPLES
file_put_contents(__DIR__."/awm_log.txt",
	date('c') . " - INSERT DATA: " . json_encode($data) . "\n",
	FILE_APPEND
);

// Ensure the `entrada` table and expected columns exist (fixes Unknown column errors)
$createTableSql = "CREATE TABLE IF NOT EXISTS entrada (
	id INT AUTO_INCREMENT PRIMARY KEY,
	nome VARCHAR(1024),
	empresa VARCHAR(255),
	quantidade VARCHAR(64),
	vu VARCHAR(64),
	vt VARCHAR(64),
	data VARCHAR(64),
	horario VARCHAR(64),
	recebido_por VARCHAR(255),
	created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci";
if (!$conn->query($createTableSql)) {
    file_put_contents(__DIR__.'/awm_log.txt', date('c') . " - ENSURE_TABLE_ERROR: " . $conn->error . "\n", FILE_APPEND);
}

$expectedCols = ['quantidade', 'vu', 'vt'];
foreach ($expectedCols as $col) {
    $colCheck = $conn->query("SHOW COLUMNS FROM entrada LIKE '" . $conn->real_escape_string($col) . "'");
    if (!$colCheck || $colCheck->num_rows === 0) {
        $alter = "ALTER TABLE entrada ADD COLUMN `" . $conn->real_escape_string($col) . "` VARCHAR(64) NULL";
        if ($conn->query($alter)) {
            file_put_contents(__DIR__.'/awm_log.txt', date('c') . " - ADDED COLUMN: $col\n", FILE_APPEND);
        } else {
            file_put_contents(__DIR__.'/awm_log.txt', date('c') . " - ADD_COLUMN_ERROR ($col): " . $conn->error . " - attempted: " . $alter . "\n", FILE_APPEND);
        }
    }
}

$sql = "INSERT INTO entrada
(nome, empresa, quantidade, vu, vt, data, horario, recebido_por)
VALUES (?, ?, ?, ?, ?, ?, ?, ?)";

try {

	$stmt = $conn->prepare($sql);

	if (!$stmt) {
		echo json_encode(["ok" => false, "error" => $conn->error]);
		exit;
	}

	$stmt->bind_param(
		"ssssssss",
		$nome,
		$empresa,
		$quantidade,
		$vu,
		$vt,
		$data_field,
		$horario,
		$recebido_por
	);

	if ($stmt->execute()) {
		echo json_encode(["ok" => true, "id" => $conn->insert_id]);
		exit;
	} else {
		echo json_encode(["ok" => false, "error" => $stmt->error]);
		exit;
	}

} catch (Exception $e) {
	echo json_encode(["ok" => false, "error" => $e->getMessage()]);
	exit;
}

?>
