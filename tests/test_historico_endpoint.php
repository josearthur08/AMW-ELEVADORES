<?php
// Simple test harness to POST a historico entry to the local endpoint
// Usage: php tests/test_historico_endpoint.php

$url = 'http://localhost/salvar_historico.php';
$data = [
    'cliente_id' => '3',
    'obra' => 'gplus',
    'data' => '2025-12-06',
    'servico' => 'Teste automatizado',
    'equipe' => 'CI'
];

if(!function_exists('curl_init')){
    echo "cURL extension required for this test.\n";
    exit(1);
}

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
curl_setopt($ch, CURLOPT_HEADER, true);

$res = curl_exec($ch);
if($res === false){
    echo "cURL error: " . curl_error($ch) . "\n";
    curl_close($ch);
    exit(2);
}
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$header_size = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
$header = substr($res, 0, $header_size);
$body = substr($res, $header_size);

echo "HTTP/Status: $http_code\n";
echo "--- Response headers ---\n" . $header . "\n";
echo "--- Response body ---\n" . $body . "\n";

curl_close($ch);

// If JSON, pretty print
$json = json_decode($body, true);
if($json) {
    echo "--- Parsed JSON ---\n" . json_encode($json, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
}

?>