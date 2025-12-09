<?php
include "connection.php";

$result = $conn->query("SELECT * FROM saida_almoxarifado ORDER BY data_saida DESC");
if(!$result){
    echo "Erro na consulta: " . $conn->error;
    exit;
}
?>

<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Lista de Saídas</title>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css?family=Montserrat:400,700&display=swap">
    <link rel="stylesheet" href="style.css">
</head>
<body>

<h2>Saídas do Almoxarifado</h2>

<table border="1" cellpadding="5">
    <tr>
        <th>ID</th>
        <th>Produto</th>
        <th>Quantidade</th>
        <th>Funcionário</th>
        <th>Data</th>
    </tr>

    <?php while ($row = $result->fetch_assoc()): ?>
        <tr>
            <td><?= $row['id']; ?></td>
            <td><?= htmlspecialchars($row['produto']); ?></td>
            <td><?= intval($row['quantidade']); ?></td>
            <td><?= htmlspecialchars($row['funcionario']); ?></td>
            <td><?= htmlspecialchars($row['data_saida']); ?></td>
        </tr>
    <?php endwhile; ?>
</table>

</body>
</html>
