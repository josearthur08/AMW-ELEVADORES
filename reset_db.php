<?php
// Reset and recreate the `awm` database and required tables.
// WARNING: destructive. Run only if you understand this will erase existing data.

header('Content-Type: application/json; charset=utf-8');

$host = 'localhost';
$user = 'root';
$pass = '';
$db = 'awm';

$log = function($msg){ file_put_contents(__DIR__ . '/awm_log.txt', date('c') . " - RESET_DB: " . $msg . "\n", FILE_APPEND); };

$log('starting reset');

// connect without selecting a database to run DROP/CREATE
$admin = new mysqli($host, $user, $pass);
if($admin->connect_error){
    $err = 'admin connect error: ' . $admin->connect_error;
    $log($err);
    echo json_encode(['ok'=>false,'error'=>$err]);
    exit;
}

// drop database if exists
if(!$admin->query("DROP DATABASE IF EXISTS `" . $admin->real_escape_string($db) . "`")){
    $log('drop failed: ' . $admin->error);
}
else {
    $log('dropped database if existed');
}

// create database
$createDbSql = "CREATE DATABASE `" . $admin->real_escape_string($db) . "` CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci";
if(!$admin->query($createDbSql)){
    $err = 'create db failed: ' . $admin->error;
    $log($err);
    echo json_encode(['ok'=>false,'error'=>$err]);
    exit;
}
$log('created database');

$admin->close();

// connect to the new database
$conn = new mysqli($host, $user, $pass, $db);
if($conn->connect_error){
    $err = 'connect to new db failed: ' . $conn->connect_error;
    $log($err);
    echo json_encode(['ok'=>false,'error'=>$err]);
    exit;
}

$errors = [];

// create tables
$schemas = [
    'clientes' => "CREATE TABLE IF NOT EXISTS clientes (
        id INT AUTO_INCREMENT PRIMARY KEY,
        nome VARCHAR(255) NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci",

    'historico_cliente' => "CREATE TABLE IF NOT EXISTS historico_cliente (
        id INT AUTO_INCREMENT PRIMARY KEY,
        cliente_id INT NOT NULL,
        obra VARCHAR(1024),
        `data` VARCHAR(64),
        servico TEXT,
        equipe VARCHAR(255),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci",

    'entrada' => "CREATE TABLE IF NOT EXISTS entrada (
        id INT AUTO_INCREMENT PRIMARY KEY,
        nome VARCHAR(1024),
        empresa VARCHAR(255),
        quantidade VARCHAR(64),
        vu VARCHAR(64),
        vt VARCHAR(64),
        `data` VARCHAR(64),
        horario VARCHAR(32),
        recebido_por VARCHAR(255),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci",

    'programacao' => "CREATE TABLE IF NOT EXISTS programacao (
        id INT AUTO_INCREMENT PRIMARY KEY,
        obra VARCHAR(1024),
        endereco VARCHAR(1024),
        servico TEXT,
        `data` VARCHAR(64),
        equipe VARCHAR(255),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci",

    'saida' => "CREATE TABLE IF NOT EXISTS saida (
        id INT AUTO_INCREMENT PRIMARY KEY,
        nome VARCHAR(1024),
        obra VARCHAR(1024),
        equipe VARCHAR(255),
        `data` VARCHAR(64),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci",

    'saida_almoxarifado' => "CREATE TABLE IF NOT EXISTS saida_almoxarifado (
        id INT AUTO_INCREMENT PRIMARY KEY,
        produto VARCHAR(1024),
        quantidade VARCHAR(64),
        funcionario VARCHAR(255),
        data_saida VARCHAR(64),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci",

    'usuarios' => "CREATE TABLE IF NOT EXISTS usuarios (
        id INT AUTO_INCREMENT PRIMARY KEY,
        nome VARCHAR(255),
        senha VARCHAR(255),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci"
];

foreach($schemas as $name => $sql){
    if(!$conn->query($sql)){
        $errors[] = "create table $name failed: " . $conn->error;
        $log("create $name failed: " . $conn->error);
    } else {
        $log("created table $name");
    }
}

// insert sample client GPLUS
$clientName = 'GPLUS';
$stmt = $conn->prepare('INSERT INTO clientes (nome) VALUES (?)');
if($stmt){
    $stmt->bind_param('s', $clientName);
    if($stmt->execute()){
        $newClientId = $stmt->insert_id;
        $log('inserted sample client id=' . $newClientId);
    } else {
        $errors[] = 'insert client failed: ' . $stmt->error;
        $log('insert client failed: ' . $stmt->error);
    }
    $stmt->close();
} else {
    $errors[] = 'prepare insert client failed: ' . $conn->error;
    $log('prepare insert client failed: ' . $conn->error);
}

$conn->close();

$resp = ['ok'=>count($errors)===0, 'errors'=>$errors, 'sample_client_id'=> isset($newClientId) ? $newClientId : null];
file_put_contents(__DIR__ . '/awm_log.txt', date('c') . " - RESET_FINISHED result=" . json_encode($resp) . "\n", FILE_APPEND);
echo json_encode($resp);

?>
