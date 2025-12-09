<?php
// Simple helper to return last lines of awm_log.txt for local debugging.
header('Content-Type: application/json; charset=utf-8');
$path = __DIR__ . '/awm_log.txt';
if(!file_exists($path)){
    echo json_encode(["ok"=>false, "error"=>"log_not_found"]);
    exit;
}
$lines = array_slice(file($path), -200);
echo json_encode(["ok"=>true, "lines"=> $lines]);
?>