<?php
    require_once 'bdconn.php';

    if (isset($_GET['barbeiroId']) && isset($_GET['data'])) {
        $barbeiroId = $_GET['barbeiroId'];
        $data = $_GET['data'];

        $query = 'SELECT TIME_FORMAT(horario, "%H:%i") AS horario FROM agendamento WHERE id_barbeiro = ? AND data = ?';
        $stmt = mysqli_prepare($conn, $query);
        mysqli_stmt_bind_param($stmt, "is", $barbeiroId, $data);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);

        $agendamentos = array();
        while ($row = mysqli_fetch_assoc($result)) {
            $agendamentos[] = $row['horario'];
        }
        echo json_encode($agendamentos);
    } else {
        echo json_encode(array());
    }
?>