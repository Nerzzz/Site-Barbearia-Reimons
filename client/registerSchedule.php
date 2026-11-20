<?php
    require_once './src/php/bdconn.php';

    if(!isset($_POST["nome"]) || !isset($_POST["telefone"]) || !isset($_POST["dataNasc"]) || !isset($_POST["barbeiro_id"]) || !isset($_POST["servico_id"]) || !isset($_POST["data"]) || !isset($_POST["horario"])){
        die("Dados não recebidos corretamente.");
    }

    $sql = "INSERT INTO agendamento (cliente, telefone, data_nasc, obs, id_barbeiro, id_servico, data, horario) VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
    $stmt = mysqli_prepare($conn, $sql);

    mysqli_stmt_bind_param($stmt, "ssssisss", $_POST["nome"], $_POST["telefone"], $_POST["dataNasc"], $_POST["observacoes"], $_POST["barbeiro_id"], $_POST["servico_id"], $_POST["data"], $_POST["horario"]);

    if(mysqli_stmt_execute($stmt)){
        echo "Agendamento realizado com sucesso!";
    } else {
        echo "Erro ao realizar agendamento: " . mysqli_error($conn);
    }
?>