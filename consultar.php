<?php
include "connection.php";

$allowed = [
    'entrada','historico_cliente','clientes','programacao','saida','saida_almoxarifado','usuarios'
];

$tabela = isset($_GET['tabela']) ? $_GET['tabela'] : '';
$dados = [];

if($tabela === 'historico_cliente'){
    $id = isset($_GET['cliente']) ? intval($_GET['cliente']) : 0;
    $stmt = $conn->prepare("SELECT * FROM historico_cliente WHERE cliente_id = ? ORDER BY `data` DESC");
    if($stmt){
        $stmt->bind_param('i', $id);
        if($stmt->execute()){
            $res = $stmt->get_result();
            while($row = $res->fetch_assoc()) $dados[] = $row;
        } else {
            file_put_contents(__DIR__."/awm_query_errors.log", date('c') . " - HISTORICO EXEC ERROR: " . $stmt->error . "\n", FILE_APPEND);
        }
    } else {
        file_put_contents(__DIR__."/awm_query_errors.log", date('c') . " - HISTORICO PREPARE ERROR: " . $conn->error . "\n", FILE_APPEND);
    }

} else {
    // ensure the requested table is known/safe
    if(!in_array($tabela, $allowed)){
        file_put_contents(__DIR__."/awm_log.txt", date('c') . " - CONSULTAR REJECTED TABLE: " . $tabela . "\n", FILE_APPEND);
        echo json_encode([]);
        exit;
    }
    // safe select: escape table name and use backticks
    $safe = $conn->real_escape_string($tabela);
    $sql = "SELECT * FROM `" . $safe . "`";
    $result = $conn->query($sql);
    if($result){
        while($row = $result->fetch_assoc()) $dados[] = $row;
    } else {
        file_put_contents(__DIR__."/awm_query_errors.log", date('c') . " - QUERY ERROR: " . $conn->error . " - SQL: " . $sql . "\n", FILE_APPEND);
        file_put_contents(__DIR__."/awm_log.txt", date('c') . " - CONSULTAR ERROR: tabela=" . $tabela . " error=" . $conn->error . "\n", FILE_APPEND);
    }
}

// log number of rows returned
file_put_contents(__DIR__."/awm_log.txt", date('c') . " - CONSULTAR: tabela=" . $tabela . " rows=" . count($dados) . "\n", FILE_APPEND);

echo json_encode($dados);
?>
