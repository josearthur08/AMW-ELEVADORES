<?php
header('Content-Type: application/json; charset=utf-8');
include __DIR__ . '/connection.php';

$raw = file_get_contents('php://input');
parse_str($raw, $parsed);
$id = 0;
if(isset($_POST['id'])) $id = intval($_POST['id']);
elseif(isset($parsed['id'])) $id = intval($parsed['id']);
elseif(isset($GLOBALS['HTTP_RAW_POST_DATA'])){ /* ignore */ }

if(!$id){ http_response_code(400); echo json_encode(['ok'=>false,'error'=>'invalid_id']); exit; }

$stmt = $conn->prepare('DELETE FROM historico_cliente WHERE id = ?');
if(!$stmt){ http_response_code(500); echo json_encode(['ok'=>false,'error'=>'prepare_failed','details'=>$conn->error]); exit; }
$stmt->bind_param('i', $id);
if(!$stmt->execute()){ http_response_code(500); echo json_encode(['ok'=>false,'error'=>'execute_failed','details'=>$stmt->error]); exit; }

file_put_contents(__DIR__ . '/awm_log.txt', date('c') . " - EXCLUIR_HISTORICO id=" . $id . "\n", FILE_APPEND);

echo json_encode(['ok'=>true,'deleted'=>$id]);

?>