<?php
// Apply migration: backup `historico_cliente` and execute migration SQL file
// WARNING: destructive only in that it creates a backup and alters schema.
header('Content-Type: application/json; charset=utf-8');
include __DIR__ . '/connection.php';

$log = function($m){ file_put_contents(__DIR__ . '/awm_log.txt', date('c') . " - APPLY_MIGRATION: " . $m . "\n", FILE_APPEND); };

$sqlFile = __DIR__ . '/migrations/001_add_historico_columns.sql';
if(!file_exists($sqlFile)){
    http_response_code(500);
    $msg = 'migration file not found: ' . $sqlFile;
    $log($msg);
    echo json_encode(['ok'=>false,'error'=>'no_migration_file','details'=>$msg]);
    exit;
}

// Create backup table
$ts = date('Ymd_His');
$backupName = 'historico_cliente_backup_' . $ts;
// check if historico_cliente exists
$res = $conn->query("SHOW TABLES LIKE 'historico_cliente'");
if($res && $res->num_rows > 0){
    $bkSql = "CREATE TABLE `" . $conn->real_escape_string($backupName) . "` AS SELECT * FROM `historico_cliente`";
    if($conn->query($bkSql)){
        $log("backup created: " . $backupName);
    } else {
        $err = $conn->error;
        $log("backup_failed: " . $err);
        http_response_code(500);
        echo json_encode(['ok'=>false,'error'=>'backup_failed','details'=>$err]);
        exit;
    }
} else {
    $log('historico_cliente table not found; will still attempt migration');
}

$sql = file_get_contents($sqlFile);
if(trim($sql) === ''){
    $log('migration file empty');
    echo json_encode(['ok'=>false,'error'=>'empty_migration']);
    exit;
}

// Execute migration (multi_query)
$msgs = [];
if($conn->multi_query($sql)){
    do {
        if($res = $conn->store_result()){
            $res->free();
        }
        $msgs[] = 'query_ok';
    } while ($conn->more_results() && $conn->next_result());
    $log('migration executed successfully');
    echo json_encode(['ok'=>true,'backup'=>$backupName,'messages'=>$msgs]);
    exit;
} else {
    $err = $conn->error;
    $log('migration_failed: ' . $err);
    http_response_code(500);
    echo json_encode(['ok'=>false,'error'=>'migration_failed','details'=>$err]);
    exit;
}

?>
