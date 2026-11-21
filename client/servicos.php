<?php
    session_start();
    require_once './src/php/bdconn.php';
    require_once './fotos.php';

    // Configurações
    // ---------------------------------------------------------------
    const NIVEL_BARBEIRO      = 1;
    const NIVEL_ADMIN         = 2;
    const POR_PAGINA          = 14;
    const SERVICO_CANCELADO   = 999;   // id reservado para agendamentos cancelados
    const NOME_MAX            = 30;    // tamanho da coluna servicos.nome
    const PAGINA_AGENDAMENTOS = './dashboardBarbeiros.php';

    // Funções auxiliares
    // ---------------------------------------------------------------
    function e($valor) {
        return htmlspecialchars((string) $valor, ENT_QUOTES, 'UTF-8');
    }

    function preparar($conn, $sql, $tipos = '', ...$params) {
        $stmt = mysqli_prepare($conn, $sql);
        if (!$stmt) {
            die("Erro no prepare: " . mysqli_error($conn));
        }
        if ($tipos !== '') {
            mysqli_stmt_bind_param($stmt, $tipos, ...$params);
        }
        mysqli_stmt_execute($stmt);
        return $stmt;
    }

    function buscar($conn, $sql, $tipos = '', ...$params) {
        $stmt = preparar($conn, $sql, $tipos, ...$params);
        $linhas = mysqli_fetch_all(mysqli_stmt_get_result($stmt), MYSQLI_ASSOC);
        mysqli_stmt_close($stmt);
        return $linhas;
    }

    function executar($conn, $sql, $tipos = '', ...$params) {
        $stmt = preparar($conn, $sql, $tipos, ...$params);
        $afetadas = mysqli_stmt_affected_rows($stmt);
        mysqli_stmt_close($stmt);
        return $afetadas;
    }

    function redirecionar($pagina, $mensagem = null, $tipo = 'sucesso') {
        if ($mensagem !== null) {
            $_SESSION['flash'] = ['mensagem' => $mensagem, 'tipo' => $tipo];
        }
        header('Location: ./servicos.php?pagina=' . (int) $pagina);
        exit;
    }

    // 40 -> "R$ 40,00"
    function formatarValor($valor) {
        return 'R$ ' . number_format((float) $valor, 2, ',', '.');
    }

    // Aceita "40", "40.5", "40,50" e "1.250,00"
    function lerValor($texto) {
        $texto = trim((string) $texto);
        if (strpos($texto, ',') !== false) {
            $texto = str_replace(['.', ','], ['', '.'], $texto);
        }
        return is_numeric($texto) ? round((float) $texto, 2) : null;
    }

    // Autenticação: só administrador
    // ---------------------------------------------------------------
    if (empty($_SESSION['barbeiro_id'])) {
        header('Location: ./login.html');
        exit;
    }

    $usuario = buscar($conn,
        "SELECT id, nome, nivel FROM barbeiros WHERE id = ?",
        "i", (int) $_SESSION['barbeiro_id']
    )[0] ?? null;

    if (!$usuario || !in_array((int) $usuario['nivel'], [NIVEL_BARBEIRO, NIVEL_ADMIN], true)) {
        session_unset();
        session_destroy();
        header('Location: ./login.html?erro=sessao');
        exit;
    }

    // Barbeiro comum não gerencia serviços: vai para os próprios agendamentos
    if ((int) $usuario['nivel'] !== NIVEL_ADMIN) {
        header('Location: ' . PAGINA_AGENDAMENTOS);
        exit;
    }

    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }

    // Estado da página
    // ---------------------------------------------------------------
    $erros = [];
    $form = [
        'id'      => 0,
        'nome'    => '',
        'valor'   => '',
        'ativo'   => 1,
        'img_url' => '',
    ];
    $paginaAtual = max(1, (int) ($_GET['pagina'] ?? $_POST['pagina'] ?? 1));

    // Ações
    // ---------------------------------------------------------------
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // Quando o envio passa do post_max_size do PHP, o $_POST chega vazio
        if (empty($_POST) && (int) ($_SERVER['CONTENT_LENGTH'] ?? 0) > 0) {
            redirecionar($paginaAtual, 'O arquivo enviado é grande demais.', 'erro');
        }

        if (!hash_equals($_SESSION['csrf'], (string) ($_POST['csrf'] ?? ''))) {
            redirecionar($paginaAtual, 'Sessão expirada. Tente novamente.', 'erro');
        }

        $acao = $_POST['acao'] ?? '';

        // Ativar / desativar (não apaga: agendamentos antigos usam o id)
        // ---------------------------------------------------------------
        if ($acao === 'alternar') {
            $id    = (int) ($_POST['id'] ?? 0);
            $ativo = ($_POST['ativo'] ?? '') === '1' ? 1 : 0;

            $servico = buscar($conn, "SELECT nome FROM servicos WHERE id = ? AND id <> ?", "ii", $id, SERVICO_CANCELADO)[0] ?? null;
            if (!$servico) {
                redirecionar($paginaAtual, 'Serviço não encontrado.', 'erro');
            }

            executar($conn, "UPDATE servicos SET ativo = ? WHERE id = ?", "ii", $ativo, $id);
            redirecionar($paginaAtual, $ativo
                ? 'Serviço "' . $servico['nome'] . '" reativado. Ele volta a aparecer no agendamento.'
                : 'Serviço "' . $servico['nome'] . '" desativado. Ele não aparece mais no agendamento.');
        }

        // Adicionar / Editar
        // ---------------------------------------------------------------
        if ($acao === 'salvar') {
            $form = [
                'id'      => (int) ($_POST['id'] ?? 0),
                'nome'    => trim($_POST['nome'] ?? ''),
                'valor'   => trim($_POST['valor'] ?? ''),
                'ativo'   => ($_POST['ativo'] ?? '1') === '1' ? 1 : 0,
                'img_url' => '',
            ];
            $valor       = lerValor($form['valor']);
            $removerFoto = isset($_POST['remover_foto']);
            $editando    = $form['id'] > 0;
            $fotoAtual   = '';

            if ($editando) {
                $existe = buscar($conn,
                    "SELECT img_url FROM servicos WHERE id = ? AND id <> ?",
                    "ii", $form['id'], SERVICO_CANCELADO
                )[0] ?? null;

                if (!$existe) {
                    redirecionar($paginaAtual, 'Serviço não encontrado.', 'erro');
                }
                $fotoAtual       = (string) $existe['img_url'];
                $form['img_url'] = $fotoAtual;
            }

            $foto = validarFoto($_FILES['foto'] ?? [], $erros);

            if ($form['nome'] === '' || mb_strlen($form['nome']) > NOME_MAX) {
                $erros[] = 'Informe um nome com até ' . NOME_MAX . ' caracteres.';
            }
            if ($valor === null || $valor < 0 || $valor > 99999) {
                $erros[] = 'Informe um valor válido, por exemplo 40,00.';
            }

            if (!$erros) {
                $duplicado = buscar($conn,
                    "SELECT id FROM servicos WHERE LOWER(nome) = LOWER(?) AND id <> ?",
                    "si", $form['nome'], $form['id']
                );
                if ($duplicado) {
                    $erros[] = 'Já existe um serviço com este nome.';
                }
            }

            // Recebe a foto numa área temporária; só vira definitiva depois do banco
            // ---------------------------------------------------------------
            $temporario = null;
            if (!$erros && $foto) {
                $temporario = receberFoto($foto, PASTA_SERVICOS);
                if ($temporario === null) {
                    $erros[] = 'Não foi possível salvar a foto no servidor. Verifique as permissões da pasta ' . PASTA_SERVICOS . '.';
                }
            }

            if (!$erros) {
                try {
                    if ($editando) {
                        $id = $form['id'];

                        if ($temporario) {
                            $caminho = caminhoFoto($id, $form['nome'], $foto['ext'], PASTA_SERVICOS);
                        } elseif ($removerFoto || $fotoAtual === '') {
                            $caminho = '';
                        } elseif (pastaDaFoto($fotoAtual) === PASTA_SERVICOS) {
                            // Sem foto nova: acompanha uma possível troca de nome
                            $caminho = caminhoFoto($id, $form['nome'], extensaoFoto($fotoAtual), PASTA_SERVICOS);
                        } else {
                            // Imagem antiga fora da pasta de serviços (ex.: public/images/barba.webp): mantém
                            $caminho = $fotoAtual;
                        }

                        executar($conn,
                            "UPDATE servicos SET nome = ?, valor = ?, ativo = ?, img_url = ? WHERE id = ?",
                            "sdisi", $form['nome'], $valor, $form['ativo'], $caminho, $id
                        );
                    } else {
                        // Próximo id calculado aqui: funciona com ou sem AUTO_INCREMENT
                        // e pula o 999, reservado para agendamentos cancelados.
                        $id = (int) buscar($conn, "SELECT COALESCE(MAX(id), 0) + 1 AS proximo FROM servicos")[0]['proximo'];
                        if ($id === SERVICO_CANCELADO) {
                            $id++;
                        }
                        $caminho = $temporario ? caminhoFoto($id, $form['nome'], $foto['ext'], PASTA_SERVICOS) : '';

                        executar($conn,
                            "INSERT INTO servicos (id, nome, valor, img_url, ativo) VALUES (?, ?, ?, ?, ?)",
                            "isdsi", $id, $form['nome'], $valor, $caminho, $form['ativo']
                        );
                    }
                } catch (Throwable $erro) {
                    descartarTemporario($temporario);
                    throw $erro;
                }

                // Banco salvo: agora acerta os arquivos
                $arquivoOk = true;

                if ($temporario) {
                    $arquivoOk = moverFoto($temporario, $caminho);
                } elseif ($editando && $caminho !== '' && $caminho !== $fotoAtual) {
                    // Nome do serviço mudou: renomeia o arquivo
                    $arquivoOk = moverFoto(caminhoAbsoluto($fotoAtual), $caminho);
                }

                // Imagem removida ou trocada por outra com extensão diferente.
                // Só apaga o que está em public/images/servicos (nunca as imagens antigas do site).
                if ($editando && $fotoAtual !== '' && $fotoAtual !== $caminho && ($temporario || $caminho === '')
                    && pastaDaFoto($fotoAtual) === PASTA_SERVICOS) {
                    apagarFoto($fotoAtual);
                }

                if (!$arquivoOk) {
                    redirecionar($paginaAtual, 'Dados salvos, mas houve um problema ao gravar a imagem. Envie a imagem novamente.', 'erro');
                }

                redirecionar($paginaAtual, $editando
                    ? 'Serviço atualizado com sucesso.'
                    : 'Serviço cadastrado com sucesso.');
            }
        }
    }
    // Carregar serviço para edição
    // ---------------------------------------------------------------
    elseif (isset($_GET['editar'])) {
        $registro = buscar($conn,
            "SELECT id, nome, valor, ativo, img_url FROM servicos WHERE id = ? AND id <> ?",
            "ii", (int) $_GET['editar'], SERVICO_CANCELADO
        )[0] ?? null;

        if (!$registro) {
            redirecionar($paginaAtual, 'Serviço não encontrado.', 'erro');
        }

        $form = [
            'id'      => (int) $registro['id'],
            'nome'    => $registro['nome'],
            'valor'   => number_format((float) $registro['valor'], 2, ',', ''),
            'ativo'   => (int) $registro['ativo'],
            'img_url' => (string) $registro['img_url'],
        ];
    }

    // Mensagem flash
    // ---------------------------------------------------------------
    $flash = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);

    // Listagem paginada (ativos primeiro)
    // ---------------------------------------------------------------
    $total = (int) buscar($conn,
        "SELECT COUNT(*) AS total FROM servicos WHERE id <> ?",
        "i", SERVICO_CANCELADO
    )[0]['total'];

    $totalPaginas = max(1, (int) ceil($total / POR_PAGINA));
    $paginaAtual  = min($paginaAtual, $totalPaginas);
    $offset       = ($paginaAtual - 1) * POR_PAGINA;

    $servicos = buscar($conn,
        "SELECT id, nome, valor, ativo, img_url
           FROM servicos
          WHERE id <> ?
          ORDER BY ativo DESC, nome
          LIMIT ? OFFSET ?",
        "iii", SERVICO_CANCELADO, POR_PAGINA, $offset
    );

    $editando = $form['id'] > 0;
?>
<!DOCTYPE html>
<html lang="pt-br">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Barbearia Reimons | Serviços</title>

        <link rel="shortcut icon" href="public/icons/favicon.png" type="image/x-icon">

        <link rel="stylesheet" href="src/styles/root.css">
        <link rel="stylesheet" href="src/styles/scheduling.css">
        <link rel="stylesheet" href="src/styles/media.css">
        <!-- "?v=" força o navegador a baixar o CSS novo; aumente ao alterar o dashboard.css -->
        <link rel="stylesheet" href="src/styles/dashboard.css?v=4">

        <!-- Mesmo script de prévia da foto usado no dashboard.php -->
        <script defer src="./src/script/dashboard.js"></script>
    </head>
    <body>
        <main>
            <section>
                <div class="topo">
                    <h2>Gerenciar Serviços</h2>
                    <div class="usuario">
                        <nav class="menu-painel">
                            <a href="<?= e(PAGINA_AGENDAMENTOS) ?>">Agendamentos</a>
                            <a href="./dashboard.php">Funcionários</a>
                            <a class="ativo" href="./servicos.php">Serviços</a>
                        </nav>
                        <span>Olá, <?= e($usuario['nome']) ?></span>
                        <a class="btn" href="./logout.php">Sair</a>
                    </div>
                </div>

                <?php if ($flash): ?>
                    <div class="flash <?= e($flash['tipo']) ?>"><?= e($flash['mensagem']) ?></div>
                <?php endif; ?>

                <!-- ===================== LISTAGEM ===================== -->
                <div class="barbers servicos-lista">
                    <h4>Serviços cadastrados</h4>

                    <?php if (!$servicos): ?>
                        <p class="observation">Nenhum serviço cadastrado.</p>
                    <?php else: ?>
                        <div class="content">
                            <?php foreach ($servicos as $s):
                                $ativo = (int) $s['ativo'] === 1;
                            ?>
                                <article class="card<?= $ativo ? '' : ' inativo' ?><?= (int) $s['id'] === $form['id'] ? ' editando' : '' ?>">
                                    <img src="<?= e(urlFoto($s['img_url'])) ?>" alt="<?= e($s['nome']) ?>">
                                    <span><?= e($s['nome']) ?></span>
                                    <strong class="preco"><?= e(formatarValor($s['valor'])) ?></strong>

                                    <div class="tags">
                                        <span class="tag<?= $ativo ? ' nivel-2' : '' ?>"><?= $ativo ? 'Ativo' : 'Desativado' ?></span>
                                    </div>

                                    <div class="acoes">
                                        <a class="btn" href="./servicos.php?pagina=<?= $paginaAtual ?>&editar=<?= (int) $s['id'] ?>#form-servico">Editar</a>

                                        <form method="post" action="./servicos.php"
                                              <?php if ($ativo): ?>onsubmit="return confirm(<?= e(json_encode('Desativar ' . $s['nome'] . '? Ele deixa de aparecer no agendamento.', JSON_UNESCAPED_UNICODE)) ?>);"<?php endif; ?>>
                                            <input type="hidden" name="csrf" value="<?= e($_SESSION['csrf']) ?>">
                                            <input type="hidden" name="acao" value="alternar">
                                            <input type="hidden" name="id" value="<?= (int) $s['id'] ?>">
                                            <input type="hidden" name="ativo" value="<?= $ativo ? '0' : '1' ?>">
                                            <input type="hidden" name="pagina" value="<?= $paginaAtual ?>">
                                            <button type="submit" class="btn<?= $ativo ? ' excluir' : '' ?>"><?= $ativo ? 'Desativar' : 'Reativar' ?></button>
                                        </form>
                                    </div>
                                </article>
                            <?php endforeach; ?>
                        </div>

                        <?php if ($totalPaginas > 1): ?>
                            <nav class="paginacao" aria-label="Paginação">
                                <?php if ($paginaAtual > 1): ?>
                                    <a href="./servicos.php?pagina=<?= $paginaAtual - 1 ?>" aria-label="Página anterior">&laquo;</a>
                                <?php endif; ?>
                                <?php for ($p = 1; $p <= $totalPaginas; $p++): ?>
                                    <?php if ($p === $paginaAtual): ?>
                                        <span class="atual" aria-current="page"><?= $p ?></span>
                                    <?php else: ?>
                                        <a href="./servicos.php?pagina=<?= $p ?>"><?= $p ?></a>
                                    <?php endif; ?>
                                <?php endfor; ?>
                                <?php if ($paginaAtual < $totalPaginas): ?>
                                    <a href="./servicos.php?pagina=<?= $paginaAtual + 1 ?>" aria-label="Próxima página">&raquo;</a>
                                <?php endif; ?>
                            </nav>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>

                <!-- ===================== FORMULÁRIO ===================== -->
                <form id="form-servico" method="post" action="./servicos.php" enctype="multipart/form-data">
                    <input type="hidden" name="csrf" value="<?= e($_SESSION['csrf']) ?>">
                    <input type="hidden" name="acao" value="salvar">
                    <input type="hidden" name="id" value="<?= $form['id'] ?>">
                    <input type="hidden" name="pagina" value="<?= $paginaAtual ?>">

                    <h4><?= $editando ? 'Editar serviço' : 'Adicionar serviço' ?></h4>

                    <?php if ($erros): ?>
                        <div class="flash erro">
                            <ul>
                                <?php foreach ($erros as $erro): ?>
                                    <li><?= e($erro) ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endif; ?>

                    <div class="form">
                        <div class="section-1">
                            <div class="inputs">
                                <div class="input">
                                    <input type="text" name="nome" id="nome" placeholder="Corte cabelo" maxlength="<?= NOME_MAX ?>" value="<?= e($form['nome']) ?>" required>
                                    <label for="nome">Nome do serviço</label>
                                </div>
                                <div class="input">
                                    <input type="text" name="valor" id="valor" placeholder="40,00" inputmode="decimal" value="<?= e($form['valor']) ?>" required>
                                    <label for="valor">Valor (R$)</label>
                                </div>
                                <div class="foto-campo">
                                    <img id="foto-preview" class="foto-servico"
                                         src="<?= e(urlFoto($form['img_url'])) ?>"
                                         data-padrao="<?= e(FOTO_PADRAO) ?>"
                                         alt="Prévia da imagem do serviço">

                                    <div class="input">
                                        <input type="hidden" name="MAX_FILE_SIZE" value="<?= FOTO_TAMANHO_MAX ?>">
                                        <input type="file" name="foto" id="foto" accept="image/jpeg,image/png,image/webp">
                                        <label for="foto">Imagem do serviço (JPG, PNG ou WebP, até 2 MB)</label>
                                    </div>

                                    <?php if ($editando && $form['img_url'] !== ''): ?>
                                        <label class="remover-foto">
                                            <input type="checkbox" name="remover_foto" id="remover_foto" value="1">
                                            Remover imagem atual
                                        </label>
                                    <?php endif; ?>
                                </div>
                                <div class="input">
                                    <select name="ativo" id="ativo">
                                        <option value="1" <?= $form['ativo'] === 1 ? 'selected' : '' ?>>Sim, aparece no agendamento</option>
                                        <option value="0" <?= $form['ativo'] === 0 ? 'selected' : '' ?>>Não, fica oculto</option>
                                    </select>
                                    <label for="ativo"></label>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="form-action">
                        <input type="submit" value="<?= $editando ? 'Salvar alterações' : 'Adicionar serviço' ?>">
                        <?php if ($editando): ?>
                            <a class="btn" href="./servicos.php?pagina=<?= $paginaAtual ?>">Cancelar</a>
                        <?php else: ?>
                            <a class="btn" href="./index.html">Voltar ao site</a>
                        <?php endif; ?>
                    </div>
                </form>
            </section>
        </main>

        <footer id="contato">
            <div class="content">
                <img src="./public/images/logo/dark.svg" alt="Logo da barbearia">

                <div class="info">
                    <h4>Barbearia Reimons</h4>
                    <p>Avenida Raposo Tavares, 632, Pauliceia, Piracicaba - SP</p>

                    <div class="funcionamento">
                        <span>Funcionamento</span>
                        <ul>
                            <li><p>Segunda à sexta, das 08:00 às 20:00</p></li>
                            <li><p>Sábados, das 08:00 às 18:00</p></li>
                        </ul>
                    </div>

                    <nav class="social">
                        <a href="">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 640 640"><path d="M476.9 161.1C435 119.1 379.2 96 319.9 96C197.5 96 97.9 195.6 97.9 318C97.9 357.1 108.1 395.3 127.5 429L96 544L213.7 513.1C246.1 530.8 282.6 540.1 319.8 540.1L319.9 540.1C442.2 540.1 544 440.5 544 318.1C544 258.8 518.8 203.1 476.9 161.1zM319.9 502.7C286.7 502.7 254.2 493.8 225.9 477L219.2 473L149.4 491.3L168 423.2L163.6 416.2C145.1 386.8 135.4 352.9 135.4 318C135.4 216.3 218.2 133.5 320 133.5C369.3 133.5 415.6 152.7 450.4 187.6C485.2 222.5 506.6 268.8 506.5 318.1C506.5 419.9 421.6 502.7 319.9 502.7zM421.1 364.5C415.6 361.7 388.3 348.3 383.2 346.5C378.1 344.6 374.4 343.7 370.7 349.3C367 354.9 356.4 367.3 353.1 371.1C349.9 374.8 346.6 375.3 341.1 372.5C308.5 356.2 287.1 343.4 265.6 306.5C259.9 296.7 271.3 297.4 281.9 276.2C283.7 272.5 282.8 269.3 281.4 266.5C280 263.7 268.9 236.4 264.3 225.3C259.8 214.5 255.2 216 251.8 215.8C248.6 215.6 244.9 215.6 241.2 215.6C237.5 215.6 231.5 217 226.4 222.5C221.3 228.1 207 241.5 207 268.8C207 296.1 226.9 322.5 229.6 326.2C232.4 329.9 268.7 385.9 324.4 410C359.6 425.2 373.4 426.5 391 423.9C401.7 422.3 423.8 410.5 428.4 397.5C433 384.5 433 373.4 431.6 371.1C430.3 368.6 426.6 367.2 421.1 364.5z"/></svg>
                        </a>
                        <a href="">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 640 640"><!--!Font Awesome Free v7.3.1 by @fontawesome - https://fontawesome.com License - https://fontawesome.com/license/free Copyright 2026 Fonticons, Inc.--><path d="M320.3 205C256.8 204.8 205.2 256.2 205 319.7C204.8 383.2 256.2 434.8 319.7 435C383.2 435.2 434.8 383.8 435 320.3C435.2 256.8 383.8 205.2 320.3 205zM319.7 245.4C360.9 245.2 394.4 278.5 394.6 319.7C394.8 360.9 361.5 394.4 320.3 394.6C279.1 394.8 245.6 361.5 245.4 320.3C245.2 279.1 278.5 245.6 319.7 245.4zM413.1 200.3C413.1 185.5 425.1 173.5 439.9 173.5C454.7 173.5 466.7 185.5 466.7 200.3C466.7 215.1 454.7 227.1 439.9 227.1C425.1 227.1 413.1 215.1 413.1 200.3zM542.8 227.5C541.1 191.6 532.9 159.8 506.6 133.6C480.4 107.4 448.6 99.2 412.7 97.4C375.7 95.3 264.8 95.3 227.8 97.4C192 99.1 160.2 107.3 133.9 133.5C107.6 159.7 99.5 191.5 97.7 227.4C95.6 264.4 95.6 375.3 97.7 412.3C99.4 448.2 107.6 480 133.9 506.2C160.2 532.4 191.9 540.6 227.8 542.4C264.8 544.5 375.7 544.5 412.7 542.4C448.6 540.7 480.4 532.5 506.6 506.2C532.8 480 541 448.2 542.8 412.3C544.9 375.3 544.9 264.5 542.8 227.5zM495 452C487.2 471.6 472.1 486.7 452.4 494.6C422.9 506.3 352.9 503.6 320.3 503.6C287.7 503.6 217.6 506.2 188.2 494.6C168.6 486.8 153.5 471.7 145.6 452C133.9 422.5 136.6 352.5 136.6 319.9C136.6 287.3 134 217.2 145.6 187.8C153.4 168.2 168.5 153.1 188.2 145.2C217.7 133.5 287.7 136.2 320.3 136.2C352.9 136.2 423 133.6 452.4 145.2C472 153 487.1 168.1 495 187.8C506.7 217.3 504 287.3 504 319.9C504 352.5 506.7 422.6 495 452z"/></svg>
                        </a>
                        <a href="">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 640 640"><!--!Font Awesome Free v7.3.1 by @fontawesome - https://fontawesome.com License - https://fontawesome.com/license/free Copyright 2026 Fonticons, Inc.--><path d="M576 320C576 178.6 461.4 64 320 64C178.6 64 64 178.6 64 320C64 440 146.7 540.8 258.2 568.5L258.2 398.2L205.4 398.2L205.4 320L258.2 320L258.2 286.3C258.2 199.2 297.6 158.8 383.2 158.8C399.4 158.8 427.4 162 438.9 165.2L438.9 236C432.9 235.4 422.4 235 409.3 235C367.3 235 351.1 250.9 351.1 292.2L351.1 320L434.7 320L420.3 398.2L351 398.2L351 574.1C477.8 558.8 576 450.9 576 320z"/></svg>
                        </a>
                    </nav>
                </div>
            </div>
            <div class="links">
                <nav>
                    <p>Social</p>
                    <ul>
                        <li><a href="">Whatsapp</a></li>
                        <li><a href="">Instagram</a></li>
                        <li><a href="">Facebook</a></li>
                    </ul>
                </nav>
            </div>
        </footer>
    </body>
</html>