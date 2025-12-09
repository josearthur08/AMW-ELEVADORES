<?php
include "connection.php";
header('Content-Type: application/json; charset=utf-8');

// Accept JSON body or form POST
$raw = file_get_contents('php://input');
$data = json_decode($raw, true);
if(!is_array($data)) $data = $_POST;

$id = isset($data['id']) ? intval($data['id']) : 0;
if($id <= 0){
    http_response_code(400);
    echo json_encode(['ok'=>false,'error'=>'ID inválido']);
    exit;
}

// Optional: check row exists
$res = $conn->prepare("SELECT id FROM entrada WHERE id = ? LIMIT 1");
if($res){
    $res->bind_param('i', $id);
    $res->execute();
    $r = $res->get_result();
    if($r->num_rows === 0){
        echo json_encode(['ok'=>false,'error'=>'Registro não encontrado']);
        exit;
    }
}

$stmt = $conn->prepare("DELETE FROM entrada WHERE id = ? LIMIT 1");
if(!$stmt){
    file_put_contents(__DIR__."/awm_log.txt", date('c') . " - EXCLUIR_ENTRADA PREPARE_ERROR: " . $conn->error . "\n", FILE_APPEND);
    http_response_code(500);
    echo json_encode(['ok'=>false,'error'=>$conn->error]);
    exit;
}

$stmt->bind_param('i', $id);
if(!$stmt->execute()){
    file_put_contents(__DIR__."/awm_log.txt", date('c') . " - EXCLUIR_ENTRADA EXEC_ERROR: " . $stmt->error . "\n", FILE_APPEND);
    http_response_code(500);
    echo json_encode(['ok'=>false,'error'=>$stmt->error]);
    exit;
}

file_put_contents(__DIR__."/awm_log.txt", date('c') . " - EXCLUIR_ENTRADA id=" . $id . "\n", FILE_APPEND);

echo json_encode(['ok'=>true,'id'=>$id]);
exit;
?>