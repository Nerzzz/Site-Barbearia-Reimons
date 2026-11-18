<?php
    require_once './src/php/bdconn.php';

    if(!isset($_POST['login']) || !isset($_POST['password'])) {
        echo "Login ou senha não informados";
        echo "<button onclick='history.go(-1)'>Voltar</button>";
        exit;
    }

    $login = trim($_POST['login']);
    $password = $_POST['password'];
    

    $sql = "SELECT * FROM barbeiros WHERE email = ? AND senha = ?";
    
    $stmt = mysqli_prepare($conn, $sql);
    if (!$stmt) {
        die("Erro no prepare: " . mysqli_error($conn));
    }

    mysqli_stmt_bind_param($stmt, "ss", $login, $password);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);

    if (mysqli_num_rows($result) > 0) {
        header("Location: ./src/php/dashboard.php");
        exit;
    } else {
        echo "Login ou senha incorretos";
        echo "<button onclick='history.go(-1)'>Voltar</button>";
        exit;
    }
?>