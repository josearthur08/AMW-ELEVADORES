<?php
include "connection.php";
// accept form POST (id) or JSON
$id = null;
if(isset($_POST['id'])) $id = intval($_POST['id']);
else {
    $data = json_decode(file_get_contents('php://input'), true);
    if(isset($data['id'])) $id = intval($data['id']);
}
if(!$id){ http_response_code(400); echo json_encode(["error"=>"id missing"]); exit; }
$stmt = $conn->prepare("DELETE FROM programacao WHERE id = ?");
if(!$stmt){ http_response_code(500); echo json_encode(["error"=>"prepare failed"]); exit; }
$stmt->bind_param('i', $id);
if($stmt->execute()){
    echo json_encode(["success"=>true]);
} else {
    http_response_code(500);
    echo json_encode(["error"=>"execute failed"]);
}
?>