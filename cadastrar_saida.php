<?php
include "connection.php";

$mysqli = $conn;

// Quando enviar o formulário
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $produto = $_POST['produto'];
    $quantidade = $_POST['quantidade'];
    $funcionario = $_POST['funcionario'];
    $data_saida = $_POST['data_saida'];

    $stmt = $mysqli->prepare("INSERT INTO saida_almoxarifado (produto, quantidade, funcionario, data_saida) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("siss", $produto, $quantidade, $funcionario, $data_saida);

    if ($stmt->execute()) {
        echo "<p>Saída registrada com sucesso!</p>";
    } else {
        echo "<p>Erro ao registrar saída: " . $stmt->error . "</p>";
    }

    $stmt->close();
}
?>

<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Saída de Almoxarifado</title>
</head>
<body>

<h2>Registrar Saída de Almoxarifado</h2>

<form method="POST">
    <label>Produto:</label><br>
    <input type="text" name="produto" required><br><br>

    <label>Quantidade:</label><br>
    <input type="number" name="quantidade" required><br><br>

    <label>Funcionário:</label><br>
    <input type="text" name="funcionario" required><br><br>

    <label>Data da Saída:</label><br>
    <input type="date" name="data_saida" required><br><br>

    <button type="submit">Salvar</button>
</form>

</body>
</html>
