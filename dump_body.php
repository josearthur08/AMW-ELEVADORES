<?php
// Dump raw request body, $_POST and request headers for debugging
header('Content-Type: application/json; charset=utf-8');
$raw = file_get_contents('php://input');
$post = $_POST;
$headers = [];
foreach(getallheaders() as $k=>$v) $headers[$k]=$v;
echo json_encode([
    'raw' => $raw,
    'post' => $post,
    'headers' => $headers
]);
?>