<?php
include "connection.php";
header('Content-Type: application/json; charset=utf-8');

$info = [];
// current database
$res = $conn->query("SELECT DATABASE() as db");
$info['database'] = ($res && ($r = $res->fetch_assoc())) ? $r['db'] : null;

// list tables
$tables = [];
$rt = $conn->query("SHOW TABLES");
if($rt){
    while($row = $rt->fetch_row()){
        $tables[] = $row[0];
    }
}
$info['tables'] = $tables;

// describe specific tables
$check = ['entrada','historico_cliente','saida_almoxarifado'];
$info['schema'] = [];
foreach($check as $t){
    if(in_array($t, $tables)){
        $cols = [];
        $r = $conn->query("SHOW COLUMNS FROM `$t`");
        if($r){ while($c = $r->fetch_assoc()) $cols[] = $c; }
        $cnt = $conn->query("SELECT COUNT(*) as c FROM `$t`");
        $count = ($cnt && ($cr = $cnt->fetch_assoc())) ? intval($cr['c']) : null;
        $info['schema'][$t] = ['columns'=>$cols, 'count'=>$count];
    } else {
        $info['schema'][$t] = null;
    }
}

// last lines of log for quick context
$logPath = __DIR__ . '/awm_log.txt';
$info['last_log'] = [];
if(file_exists($logPath)){
    $lines = file($logPath);
    $info['last_log'] = array_slice($lines, -50);
}

echo json_encode(['ok'=>true,'info'=>$info]);
?>