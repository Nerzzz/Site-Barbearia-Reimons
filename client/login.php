<?php
    //ini_set('display_errors', 1);
    //ini_set('display_startup_errors', 1);
    //error_reporting(E_ALL);
    //var_dump($barbeiro);

    session_start();
    require_once './src/php/bdconn.php';

    // ALTERADO: em vez de mostrar uma página em branco com o erro e o botão "Voltar", volta para o login.html com um código. O JavaScript do login.html
    // transforma esse código em mensagem dentro do próprio card de login.
    function voltar($codigoErro) {
        header('Location: ./login.html?erro=' . urlencode($codigoErro));
        exit;
    }

    // ALTERADO: quem abrir login.php direto no navegador (GET) vai para a tela de login
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        header('Location: ./login.html');
        exit;
    }

    // Os nomes 'login' e 'password' são os atributos name="" dos inputs do login.html
    $login    = trim($_POST['login'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($login === '' || $password === '') {
        voltar('campos');
    }

    $sql  = "SELECT id, nome, senha, nivel FROM barbeiros WHERE email = ? LIMIT 1";
    $stmt = mysqli_prepare($conn, $sql);
    if (!$stmt) {
        die("Erro no prepare: " . mysqli_error($conn));
    }

    mysqli_stmt_bind_param($stmt, "s", $login);
    mysqli_stmt_execute($stmt);
    $result   = mysqli_stmt_get_result($stmt);
    $barbeiro = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);

    $senhaOk = false;

    if ($barbeiro) {
        $info = password_get_info($barbeiro['senha']);

        if (!empty($info['algo'])) {
            // Senha já criptografada (cadastrada pelo dashboard)
            $senhaOk = password_verify($password, $barbeiro['senha']);
        } elseif (hash_equals((string) $barbeiro['senha'], $password)) {
            // Senha antiga em texto puro: aceita uma vez e já converte para hash
            $senhaOk = true;
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $up = mysqli_prepare($conn, "UPDATE barbeiros SET senha = ? WHERE id = ?");
            mysqli_stmt_bind_param($up, "si", $hash, $barbeiro['id']);
            mysqli_stmt_execute($up);
            mysqli_stmt_close($up);
        }
    }

    // nível 3 = excluído: trata como se não existisse
    if (!$senhaOk || (int) $barbeiro['nivel'] === 3) {
        voltar('credenciais');
    }

    // nível 0 = desativado
    if ((int) $barbeiro['nivel'] === 0) {
        voltar('desativado');
    }

    session_regenerate_id(true);
    $_SESSION['barbeiro_id']    = (int) $barbeiro['id'];
    $_SESSION['barbeiro_nome']  = $barbeiro['nome'];
    $_SESSION['barbeiro_nivel'] = (int) $barbeiro['nivel'];

    header("Location: ./dashboardBarbeiros.php");
    exit;
?>