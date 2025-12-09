<?php
// Robust salvar_historico endpoint
error_reporting(E_ALL);
ini_set('display_errors','0');
header('Content-Type: application/json; charset=utf-8');

// Capture fatal errors / shutdown and log them so we can see why a 500 occurred
register_shutdown_function(function(){
	$err = error_get_last();
	if($err){
		$msg = date('c') . " - HIST_FATAL: " . json_encode($err) . "\n";
		file_put_contents(__DIR__ . '/awm_log.txt', $msg, FILE_APPEND);
		// try to return a JSON error if headers not already sent
		if(!headers_sent()){
			http_response_code(500);
			echo json_encode(['ok'=>false,'error'=>'fatal_error','details'=>$err]);
		}
	}
});
include __DIR__ . '/connection.php';

$raw = file_get_contents('php://input');
$decoded = json_decode($raw, true);
// Log receipt for debugging
// also capture request headers to help diagnose content-type issues
$headers = function_exists('getallheaders') ? getallheaders() : [];
file_put_contents(__DIR__ . '/awm_log.txt', date('c') . " - HIST_RECEIVED raw_len=" . strlen($raw) . " raw=" . substr($raw,0,1000) . " _POST=" . json_encode($_POST) . " HEADERS=" . json_encode($headers) . "\n", FILE_APPEND);

// Robust parsing: prefer $_POST (FormData) -> JSON body -> parse_str fallback
// Robust handling when the client sends strange payloads:
// - normal FormData -> $_POST populated
// - sometimes frameworks send a single POST field containing a JSON string
// - or JSON body with application/json
$data = [];
if(!empty($_POST) && count($_POST) > 0){
	// if $_POST has a single element that itself is JSON (either key or value), decode it
	if(count($_POST) === 1){
		$k = array_keys($_POST)[0];
		$v = $_POST[$k];
		// case: value is JSON string
		if(is_string($v) && ($d = json_decode($v, true)) && is_array($d)){
			$data = $d;
		} elseif(is_string($k) && strpos(trim($k), '{') === 0 && ($d = json_decode($k, true)) && is_array($d)){
			// case: key itself is JSON (weird client behavior)
			$data = $d;
		} else {
			$data = $_POST;
		}
	} else {
		$data = $_POST;
	}
} elseif(is_array($decoded) && count($decoded) > 0){
	$data = $decoded;
} else {
	$parsed = [];
	if($raw) parse_str($raw, $parsed);
	$data = is_array($parsed) && count($parsed) > 0 ? $parsed : [];
}
// Log parsed payload for debugging
file_put_contents(__DIR__ . '/awm_log.txt', date('c') . " - HIST_PARSED payload=" . json_encode($data) . "\n", FILE_APPEND);

// Normalize possible keys and trim
function val($arr, $keys, $default=''){
	foreach((array)$keys as $k){ if(isset($arr[$k]) && $arr[$k] !== '') return trim($arr[$k]); }
	return $default;
}

$cliente_id_raw = val($data, ['cliente_id','clienteId','cliente','id_cliente']);
$cliente_id = is_numeric($cliente_id_raw) ? intval($cliente_id_raw) : 0;
$obra = val($data, ['obra','obranome','titulo']);
$data_field = val($data, ['data','rel_data','data_rel','date']);
$servico = val($data, ['servico','servico_prestado','descricao']);
$equipe = val($data, ['equipe','team','responsavel','responsável']);

// Validation: require cliente_id and obra and data and servico and equipe (frontend already checks)
$missing = [];
if(!$cliente_id) $missing[] = 'cliente_id';
if($obra === '') $missing[] = 'obra';
if($data_field === '') $missing[] = 'data';
if($servico === '') $missing[] = 'servico';
if($equipe === '') $missing[] = 'equipe';

if(count($missing) > 0){
	http_response_code(400);
	$resp = [
		'ok' => false,
		'error' => 'missing_fields',
		'missing' => $missing,
		'received_raw_len' => strlen($raw),
		'received_raw' => $raw,
		'received_post' => $_POST,
		'received_parsed' => $data
	];
	file_put_contents(__DIR__ . '/awm_log.txt', date('c') . " - HIST_VALIDATION_FAILED: " . json_encode($resp) . "\n", FILE_APPEND);
	echo json_encode($resp);
	exit;
}

// Ensure table exists
$createSql = "CREATE TABLE IF NOT EXISTS historico_cliente (
	id INT AUTO_INCREMENT PRIMARY KEY,
	cliente_id INT NOT NULL,
	obra VARCHAR(1024),
	endereco VARCHAR(1024),
	`data` VARCHAR(64),
	servico TEXT,
	equipe VARCHAR(255),
	created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci";
if(!$conn->query($createSql)){
	http_response_code(500);
	$err = $conn->error;
	file_put_contents(__DIR__ . '/awm_log.txt', date('c') . " - HIST_CREATE_TABLE_ERR: " . $err . "\n", FILE_APPEND);
	echo json_encode(['ok'=>false,'error'=>'create_table_failed','details'=>$err]);
	exit;
}
// Log that table creation/check succeeded
file_put_contents(__DIR__ . '/awm_log.txt', date('c') . " - HIST_CREATE_OK\n", FILE_APPEND);

// Inspect current columns for historico_cliente and log them
$cols = [];
$res = $conn->query("SHOW COLUMNS FROM `historico_cliente`");
if($res){
	while($row = $res->fetch_assoc()) $cols[] = $row['Field'];
	file_put_contents(__DIR__ . '/awm_log.txt', date('c') . " - HIST_COLUMNS=" . json_encode($cols) . "\n", FILE_APPEND);
} else {
	file_put_contents(__DIR__ . '/awm_log.txt', date('c') . " - HIST_SHOW_COLUMNS_ERR=" . $conn->error . "\n", FILE_APPEND);
}

// Check for required columns but do NOT auto-alter the table here.
// If columns are missing, return an informative 500 with migration SQL so an admin can run it manually.
 $requiredCols = ['cliente_id','obra','endereco','data','servico','equipe'];
 $missing = array_values(array_diff($requiredCols, $cols));
 if(count($missing) > 0){
	 // Try to add missing columns automatically. If any ALTER fails, return migration SQL for manual application.
	 $colTypes = [
		 'cliente_id' => 'INT NOT NULL',
		 'obra' => 'VARCHAR(1024)',
		 'endereco' => 'VARCHAR(1024)',
		 'data' => 'VARCHAR(64)',
		 'servico' => 'TEXT',
		 'equipe' => 'VARCHAR(255)'
	 ];
	 $alterFailed = false;
	 foreach($missing as $col){
		 if(!isset($colTypes[$col])){ $alterFailed = true; continue; }
		 $alterSql = "ALTER TABLE `historico_cliente` ADD COLUMN `" . $col . "` " . $colTypes[$col] . " NULL";
		 if($conn->query($alterSql)){
			 file_put_contents(__DIR__ . '/awm_log.txt', date('c') . " - HIST_AUTO_ALTER_ADDED_" . $col . "\n", FILE_APPEND);
		 } else {
			 file_put_contents(__DIR__ . '/awm_log.txt', date('c') . " - HIST_AUTO_ALTER_FAILED_" . $col . "=" . $conn->error . "\n", FILE_APPEND);
			 $alterFailed = true;
		 }
	 }
	 if($alterFailed){
		 $fixSql = "-- Migration: add missing columns to historico_cliente\n" .
				  "ALTER TABLE `historico_cliente`\n" .
				  "  ADD COLUMN IF NOT EXISTS `cliente_id` INT NOT NULL,\n" .
				  "  ADD COLUMN IF NOT EXISTS `obra` VARCHAR(1024) NULL,\n" .
				  "  ADD COLUMN IF NOT EXISTS `endereco` VARCHAR(1024) NULL,\n" .
				  "  ADD COLUMN IF NOT EXISTS `data` VARCHAR(64) NULL,\n" .
				  "  ADD COLUMN IF NOT EXISTS `servico` TEXT NULL,\n" .
				  "  ADD COLUMN IF NOT EXISTS `equipe` VARCHAR(255) NULL;\n";
		 file_put_contents(__DIR__ . '/awm_log.txt', date('c') . " - HIST_MISSING_COLUMNS=" . json_encode($missing) . "\n", FILE_APPEND);
		 file_put_contents(__DIR__ . '/awm_log.txt', date('c') . " - HIST_MIGRATION_SQL=" . $fixSql . "\n", FILE_APPEND);
		 http_response_code(500);
		 echo json_encode([
			 'ok' => false,
			 'error' => 'missing_columns',
			 'missing' => $missing,
			 'fix_sql' => $fixSql
		 ]);
		 exit;
	 }
	 // refresh columns after alters
	 $cols = [];
	 $res2 = $conn->query("SHOW COLUMNS FROM `historico_cliente`");
	 if($res2){ while($r=$res2->fetch_assoc()) $cols[]=$r['Field']; }
	 file_put_contents(__DIR__ . '/awm_log.txt', date('c') . " - HIST_COLUMNS_AFTER_AUTOALTER=" . json_encode($cols) . "\n", FILE_APPEND);
 }

// Prepare and execute insert
file_put_contents(__DIR__ . '/awm_log.txt', date('c') . " - HIST_BEFORE_PREPARE payload=" . json_encode($data) . "\n", FILE_APPEND);

// accept optional endereco
$endereco = val($data, ['endereco','end','address']);
$sql = "INSERT INTO `historico_cliente` (`cliente_id`,`obra`,`endereco`,`data`,`servico`,`equipe`) VALUES (?,?,?,?,?,?)";
try{
	$stmt = $conn->prepare($sql);
	file_put_contents(__DIR__ . '/awm_log.txt', date('c') . " - HIST_AFTER_PREPARE stmt_ok=" . ($stmt ? '1' : '0') . "\n", FILE_APPEND);
} catch (Exception $ex){
	http_response_code(500);
	$msg = $ex->getMessage();
	file_put_contents(__DIR__ . '/awm_log.txt', date('c') . " - HIST_PREPARE_EXCEPTION: " . $msg . " - sql=" . $sql . " - payload=" . json_encode($data) . "\n", FILE_APPEND);
	echo json_encode(['ok'=>false,'error'=>'prepare_exception','details'=>$msg]);
	exit;
}

if(!$stmt){
	http_response_code(500);
	$err = $conn->error;
	file_put_contents(__DIR__ . '/awm_log.txt', date('c') . " - HIST_PREPARE_ERR: " . $err . " - payload=" . json_encode($data) . "\n", FILE_APPEND);
	echo json_encode(['ok'=>false,'error'=>'prepare_failed','details'=>$err,'received'=>$data]);
	exit;
}

$stmt->bind_param('isssss', $cliente_id, $obra, $endereco, $data_field, $servico, $equipe);
$ok = $stmt->execute();
file_put_contents(__DIR__ . '/awm_log.txt', date('c') . " - HIST_AFTER_BIND\n", FILE_APPEND);
file_put_contents(__DIR__ . '/awm_log.txt', date('c') . " - HIST_BEFORE_EXECUTE\n", FILE_APPEND);
// execute result already stored in $ok above
file_put_contents(__DIR__ . '/awm_log.txt', date('c') . " - HIST_EXECUTE_RET=" . ($ok ? '1' : '0') . "\n", FILE_APPEND);
if(!$ok){
	http_response_code(500);
	$err = $stmt->error;
	file_put_contents(__DIR__ . '/awm_log.txt', date('c') . " - HIST_EXECUTE_ERR: " . $err . " - payload=" . json_encode($data) . "\n", FILE_APPEND);
	echo json_encode(['ok'=>false,'error'=>'execute_failed','details'=>$err,'received'=>$data]);
	exit;
}

$id = $stmt->insert_id;
file_put_contents(__DIR__ . '/awm_log.txt', date('c') . " - HIST_INSERT_OK id=" . $id . " - payload=" . json_encode($data) . "\n", FILE_APPEND);
// Return both ok and legacy success
echo json_encode(['ok'=>true,'success'=>true,'id'=>$id,'received'=>$data]);

?>
