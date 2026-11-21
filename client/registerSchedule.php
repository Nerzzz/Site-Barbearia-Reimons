<?php
    require_once './src/php/bdconn.php';
    require_once './src/php/pdf.php';

    $campos = ["nome", "telefone", "dataNasc", "barbeiro_id", "servico_id", "data", "horario"];
    foreach ($campos as $campo) {
        if (empty($_POST[$campo])) {
            die("Dados não recebidos corretamente.");
        }
    }
    $obs = $_POST["observacoes"] ?? "";

    $sql = "INSERT INTO agendamento (cliente, telefone, data_nasc, obs, id_barbeiro, id_servico, data, horario)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "ssssisss",
        $_POST["nome"], $_POST["telefone"], $_POST["dataNasc"], $obs,
        $_POST["barbeiro_id"], $_POST["servico_id"], $_POST["data"], $_POST["horario"]);

    if (!mysqli_stmt_execute($stmt)) {
        die("Erro ao realizar agendamento: " . mysqli_error($conn));
    }

    $idAgendamento = mysqli_insert_id($conn);

    // MUDANÇAS: Geração do PDF com os dados do agendamento

    $q = mysqli_prepare($conn, "SELECT nome FROM barbeiros WHERE id = ?");
    mysqli_stmt_bind_param($q, "i", $_POST["barbeiro_id"]);
    mysqli_stmt_execute($q);
    $barbeiro = mysqli_fetch_row(mysqli_stmt_get_result($q))[0] ?? '-';

    $q = mysqli_prepare($conn, "SELECT nome FROM servicos WHERE id = ?");
    mysqli_stmt_bind_param($q, "i", $_POST["servico_id"]);
    mysqli_stmt_execute($q);
    $servico = mysqli_fetch_row(mysqli_stmt_get_result($q))[0] ?? '-';

    $dataBr = date('d/m/Y', strtotime($_POST["data"]));

    $pdf = gerarPdf("Comprovante de Agendamento", [
        "Código"       => "#" . $idAgendamento,
        "Cliente"      => $_POST["nome"],
        "Telefone"     => $_POST["telefone"],
        "Profissional" => $barbeiro,
        "Serviço"      => $servico,
        "Data"         => $dataBr,
        "Horário"      => $_POST["horario"],
        "Observações"  => $obs ?: "-",
    ], __DIR__ . "/public/images/logo/logo.jpg");

    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="agendamento-' . $idAgendamento . '.pdf"');
    header('Content-Length: ' . strlen($pdf));
    echo $pdf;
    
    exit;
?>