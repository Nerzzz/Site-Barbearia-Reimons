<?php
    session_start();
    require_once './src/php/bdconn.php';
    require_once './barbeirosAgenda.php';

    // Configurações
    // ---------------------------------------------------------------
    const NIVEL_BARBEIRO = 1; // vê e altera só os próprios agendamentos
    const NIVEL_ADMIN    = 2; // vê e altera os agendamentos de todos
    const NIVEL_EXCLUIDO = 3;
    const POR_PAGINA     = 10;

    const PERIODOS = [
        'proximos'   => 'Próximos',
        'hoje'       => 'Hoje',
        'anteriores' => 'Anteriores',
        'cancelados' => 'Cancelados',
    ];

    // Funções auxiliares (mesmas do dashboard.php)
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

    function redirecionar($url, $mensagem = null, $tipo = 'sucesso') {
        if ($mensagem !== null) {
            $_SESSION['flash'] = ['mensagem' => $mensagem, 'tipo' => $tipo];
        }
        header('Location: ' . $url);
        exit;
    }

    // Monta o endereço da página mantendo os filtros atuais
    function urlAgenda(array $filtros, array $mudar = []) {
        $params = array_filter(array_merge($filtros, $mudar), function ($v) {
            return $v !== null && $v !== '' && $v !== 0;
        });
        return './dashboardBarbeiros.php' . ($params ? '?' . http_build_query($params) : '');
    }

    // Busca um agendamento respeitando a permissão: barbeiro só acessa os dele
    function buscarAgendamento($conn, $id, $ehAdmin, $meuId) {
        $sql = "SELECT a.*, b.nome AS barbeiro, s.nome AS servico, s.valor
                  FROM agendamento a
                  LEFT JOIN barbeiros b ON b.id = a.id_barbeiro
                  LEFT JOIN " . TABELA_SERVICOS . " s ON s.id = a.id_servico
                 WHERE a.id = ?";
        if ($ehAdmin) {
            return buscar($conn, $sql, "i", (int) $id)[0] ?? null;
        }
        return buscar($conn, $sql . " AND a.id_barbeiro = ?", "ii", (int) $id, $meuId)[0] ?? null;
    }

    // Autenticação (revalida no banco a cada acesso)
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

    $ehAdmin = (int) $usuario['nivel'] === NIVEL_ADMIN;
    $meuId   = (int) $usuario['id'];

    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }

    // Filtros (vêm pela URL)
    // ---------------------------------------------------------------
    $periodo = $_GET['periodo'] ?? $_POST['periodo'] ?? 'proximos';
    if (!array_key_exists($periodo, PERIODOS)) {
        $periodo = 'proximos';
    }
    // Barbeiro comum sempre vê só os próprios; admin pode filtrar
    $filtroBarbeiro = $ehAdmin ? (int) ($_GET['barbeiro'] ?? $_POST['barbeiro'] ?? 0) : 0;
    $paginaAtual    = max(1, (int) ($_GET['pagina'] ?? $_POST['pagina'] ?? 1));

    $filtros = ['periodo' => $periodo, 'barbeiro' => $filtroBarbeiro, 'pagina' => $paginaAtual];

    // Ações: cancelar e remarcar
    // ---------------------------------------------------------------
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!hash_equals($_SESSION['csrf'], (string) ($_POST['csrf'] ?? ''))) {
            redirecionar(urlAgenda($filtros), 'Sessão expirada. Tente novamente.', 'erro');
        }

        $acao = $_POST['acao'] ?? '';
        $ag   = buscarAgendamento($conn, (int) ($_POST['id'] ?? 0), $ehAdmin, $meuId);

        if (!$ag) {
            redirecionar(urlAgenda($filtros), 'Agendamento não encontrado.', 'erro');
        }
        if (!agendamentoAlteravel($ag)) {
            redirecionar(urlAgenda($filtros), 'Este agendamento já passou ou foi cancelado e não pode ser alterado.', 'erro');
        }

        $descricao = $ag['cliente'] . ' (' . formatarData($ag['data']) . ' às ' . substr($ag['horario'], 0, 5) . ')';

        // Cancelar (não apaga: troca o id_servico por 999)
        // ---------------------------------------------------------------
        if ($acao === 'cancelar') {
            executar($conn,
                "UPDATE agendamento SET id_servico = ? WHERE id = ? AND id_servico <> ?",
                "iii", SERVICO_CANCELADO, (int) $ag['id'], SERVICO_CANCELADO
            );
            redirecionar(urlAgenda($filtros), 'Agendamento de ' . $descricao . ' cancelado.');
        }

        // Remarcar
        // ---------------------------------------------------------------
        if ($acao === 'remarcar') {
            $novaData    = (string) ($_POST['nova_data'] ?? '');
            $novoHorario = (string) ($_POST['novo_horario'] ?? '');
            $voltar      = urlAgenda($filtros, ['remarcar' => (int) $ag['id'], 'nova_data' => $novaData]) . '#remarcar';

            // Remarcação livre: qualquer dia e horário, sem regra de expediente.
            // Só confere se a data/hora é válida e se ainda não passou.
            if (!lerData($novaData) || !preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $novoHorario)) {
                redirecionar($voltar, 'Informe um dia e um horário válidos.', 'erro');
            }
            if (strtotime("$novaData $novoHorario") <= time()) {
                redirecionar($voltar, 'Esse horário já passou. Escolha um horário futuro.', 'erro');
            }
            if ($novaData === $ag['data'] && $novoHorario === substr($ag['horario'], 0, 5)) {
                redirecionar($voltar, 'Escolha um dia ou horário diferente do atual.', 'erro');
            }

            // O mesmo barbeiro não pode ter dois clientes no mesmo horário
            $ocupado = buscar($conn,
                "SELECT id FROM agendamento
                  WHERE id_barbeiro = ? AND data = ? AND horario = ? AND id_servico <> ? AND id <> ?",
                "issii", (int) $ag['id_barbeiro'], $novaData, $novoHorario . ':00', SERVICO_CANCELADO, (int) $ag['id']
            );
            if ($ocupado) {
                redirecionar($voltar, 'Esse horário já está ocupado. Escolha outro.', 'erro');
            }

            executar($conn,
                "UPDATE agendamento SET data = ?, horario = ? WHERE id = ?",
                "ssi", $novaData, $novoHorario . ':00', (int) $ag['id']
            );
            redirecionar(urlAgenda($filtros),
                'Agendamento de ' . $ag['cliente'] . ' remarcado para ' . formatarData($novaData) . ' às ' . $novoHorario . '.'
            );
        }

        redirecionar(urlAgenda($filtros));
    }

    // Painel de remarcação (?remarcar=ID&nova_data=AAAA-MM-DD)
    // ---------------------------------------------------------------
    $remarcando       = null;
    $novaData         = null;
    $horariosOcupados = [];

    if (isset($_GET['remarcar'])) {
        $remarcando = buscarAgendamento($conn, (int) $_GET['remarcar'], $ehAdmin, $meuId);

        if (!$remarcando || !agendamentoAlteravel($remarcando)) {
            redirecionar(urlAgenda($filtros), 'Este agendamento não pode ser remarcado.', 'erro');
        }

        $novaData = $_GET['nova_data'] ?? $remarcando['data'];
        if (!lerData($novaData) || $novaData < hoje()) {
            $novaData = hoje();
        }

        $ocupados = buscar($conn,
            "SELECT TIME_FORMAT(horario, '%H:%i') AS hora FROM agendamento
              WHERE id_barbeiro = ? AND data = ? AND id_servico <> ? AND id <> ?",
            "isii", (int) $remarcando['id_barbeiro'], $novaData, SERVICO_CANCELADO, (int) $remarcando['id']
        );
        $horariosOcupados = array_column($ocupados, 'hora');
        sort($horariosOcupados);
    }

    // Mensagem flash
    // ---------------------------------------------------------------
    $flash = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);

    // Listagem
    // ---------------------------------------------------------------
    $hoje  = hoje();
    $agora = date('H:i:s');

    $where  = [];
    $tipos  = '';
    $params = [];

    if (!$ehAdmin) {
        $where[] = 'a.id_barbeiro = ?'; $tipos .= 'i'; $params[] = $meuId;
    } elseif ($filtroBarbeiro > 0) {
        $where[] = 'a.id_barbeiro = ?'; $tipos .= 'i'; $params[] = $filtroBarbeiro;
    }

    switch ($periodo) {
        case 'hoje':
            $where[] = 'a.id_servico <> ? AND a.data = ?';
            $tipos  .= 'is'; array_push($params, SERVICO_CANCELADO, $hoje);
            $ordem   = 'a.data ASC, a.horario ASC';
            break;
        case 'anteriores':
            $where[] = 'a.id_servico <> ? AND (a.data < ? OR (a.data = ? AND a.horario < ?))';
            $tipos  .= 'isss'; array_push($params, SERVICO_CANCELADO, $hoje, $hoje, $agora);
            $ordem   = 'a.data DESC, a.horario DESC';
            break;
        case 'cancelados':
            $where[] = 'a.id_servico = ?';
            $tipos  .= 'i'; $params[] = SERVICO_CANCELADO;
            $ordem   = 'a.data DESC, a.horario DESC';
            break;
        default: // próximos
            $where[] = 'a.id_servico <> ? AND (a.data > ? OR (a.data = ? AND a.horario >= ?))';
            $tipos  .= 'isss'; array_push($params, SERVICO_CANCELADO, $hoje, $hoje, $agora);
            $ordem   = 'a.data ASC, a.horario ASC';
    }

    $whereSql = 'WHERE ' . implode(' AND ', $where);

    $total = (int) buscar($conn, "SELECT COUNT(*) AS total FROM agendamento a $whereSql", $tipos, ...$params)[0]['total'];

    $totalPaginas = max(1, (int) ceil($total / POR_PAGINA));
    $paginaAtual  = min($paginaAtual, $totalPaginas);
    $filtros['pagina'] = $paginaAtual;
    $offset = ($paginaAtual - 1) * POR_PAGINA;

    $agendamentos = buscar($conn,
        "SELECT a.*, b.nome AS barbeiro, s.nome AS servico, s.valor
           FROM agendamento a
           LEFT JOIN barbeiros b ON b.id = a.id_barbeiro
           LEFT JOIN " . TABELA_SERVICOS . " s ON s.id = a.id_servico
           $whereSql
          ORDER BY $ordem
          LIMIT ? OFFSET ?",
        $tipos . 'ii', ...array_merge($params, [POR_PAGINA, $offset])
    );

    // Lista de barbeiros para o filtro do admin
    $barbeiros = $ehAdmin
        ? buscar($conn, "SELECT id, nome FROM barbeiros WHERE nivel <> ? ORDER BY nome", "i", NIVEL_EXCLUIDO)
        : [];

?>
<!DOCTYPE html>
<html lang="pt-br">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Barbearia Reimons | Agendamentos</title>

        <link rel="shortcut icon" href="public/icons/favicon.png" type="image/x-icon">

        <link rel="stylesheet" href="src/styles/root.css">
        <link rel="stylesheet" href="src/styles/scheduling.css">
        <link rel="stylesheet" href="src/styles/media.css">
        <!-- "?v=" força o navegador a baixar o CSS novo; aumente ao alterar os arquivos -->
        <link rel="stylesheet" href="src/styles/dashboard.css?v=3">
        <link rel="stylesheet" href="src/styles/dashboardBarbeiros.css?v=3">
    </head>
    <body>
        <main>
            <section>
                <div class="topo">
                    <h2><?= $ehAdmin ? 'Agendamentos' : 'Meus Agendamentos' ?></h2>
                    <div class="usuario">
                        <nav class="menu-painel">
                            <a class="ativo" href="./dashboardBarbeiros.php">Agendamentos</a>
                            <?php if ($ehAdmin): ?>
                                <a href="./dashboard.php">Funcionários</a>
                                <a href="./servicos.php">Serviços</a>
                            <?php endif; ?>
                        </nav>
                        <span>Olá, <?= e($usuario['nome']) ?></span>
                        <a class="btn" href="./logout.php">Sair</a>
                    </div>
                </div>

                <?php if ($flash): ?>
                    <div class="flash <?= e($flash['tipo']) ?>"><?= e($flash['mensagem']) ?></div>
                <?php endif; ?>

                <!-- ===================== REMARCAR ===================== -->
                <?php if ($remarcando): ?>
                    <div class="remarcar" id="remarcar">
                        <h4>Remarcar agendamento</h4>
                        <p>
                            <strong><?= e($remarcando['cliente']) ?></strong>
                            · <?= e(nomeServico($remarcando)) ?>
                            <?php if ($ehAdmin): ?> · com <?= e($remarcando['barbeiro'] ?? '—') ?><?php endif; ?>
                            <br>
                            Horário atual: <?= e(formatarData($remarcando['data'])) ?> às <?= e(substr($remarcando['horario'], 0, 5)) ?>
                        </p>

                        <!-- Trocar o dia recarrega a página para mostrar os horários já ocupados nesse dia -->
                        <form class="remarcar-dia" method="get" action="./dashboardBarbeiros.php#remarcar">
                            <input type="hidden" name="periodo" value="<?= e($periodo) ?>">
                            <input type="hidden" name="barbeiro" value="<?= $filtroBarbeiro ?>">
                            <input type="hidden" name="pagina" value="<?= $paginaAtual ?>">
                            <input type="hidden" name="remarcar" value="<?= (int) $remarcando['id'] ?>">
                            <label for="nova_data">Novo dia</label>
                            <input type="date" id="nova_data" name="nova_data"
                                   value="<?= e($novaData) ?>" min="<?= e(hoje()) ?>"
                                   onchange="this.form.submit()">
                            <noscript><button type="submit" class="btn">Trocar dia</button></noscript>
                        </form>

                        <?php if ($horariosOcupados): ?>
                            <p class="ocupados">
                                <span>Já ocupados neste dia:</span>
                                <?php foreach ($horariosOcupados as $hora): ?>
                                    <span class="chip"><?= e($hora) ?></span>
                                <?php endforeach; ?>
                            </p>
                        <?php else: ?>
                            <p class="observation">Nenhum outro agendamento neste dia.</p>
                        <?php endif; ?>

                        <form method="post" action="./dashboardBarbeiros.php">
                            <input type="hidden" name="csrf" value="<?= e($_SESSION['csrf']) ?>">
                            <input type="hidden" name="acao" value="remarcar">
                            <input type="hidden" name="id" value="<?= (int) $remarcando['id'] ?>">
                            <input type="hidden" name="nova_data" value="<?= e($novaData) ?>">
                            <input type="hidden" name="periodo" value="<?= e($periodo) ?>">
                            <input type="hidden" name="barbeiro" value="<?= $filtroBarbeiro ?>">
                            <input type="hidden" name="pagina" value="<?= $paginaAtual ?>">

                            <div class="remarcar-hora">
                                <label for="novo_horario">Novo horário</label>
                                <input type="time" id="novo_horario" name="novo_horario"
                                       value="<?= e(substr($remarcando['horario'], 0, 5)) ?>" required>
                            </div>

                            <div class="form-action">
                                <input type="submit" value="Confirmar novo horário">
                                <a class="btn" href="<?= e(urlAgenda($filtros)) ?>">Cancelar</a>
                            </div>
                        </form>
                    </div>
                <?php endif; ?>

                <!-- ===================== FILTROS ===================== -->
                <form class="filtros" method="get" action="./dashboardBarbeiros.php">
                    <nav class="abas" aria-label="Período">
                        <?php foreach (PERIODOS as $chave => $rotulo): ?>
                            <a class="<?= $chave === $periodo ? 'ativo' : '' ?>"
                               href="<?= e(urlAgenda($filtros, ['periodo' => $chave, 'pagina' => null])) ?>"><?= e($rotulo) ?></a>
                        <?php endforeach; ?>
                    </nav>

                    <?php if ($ehAdmin): ?>
                        <input type="hidden" name="periodo" value="<?= e($periodo) ?>">
                        <label class="filtro-barbeiro">
                            <span>Barbeiro</span>
                            <select name="barbeiro" onchange="this.form.submit()">
                                <option value="0">Todos</option>
                                <?php foreach ($barbeiros as $b): ?>
                                    <option value="<?= (int) $b['id'] ?>" <?= (int) $b['id'] === $filtroBarbeiro ? 'selected' : '' ?>><?= e($b['nome']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                        <noscript><button type="submit" class="btn">Filtrar</button></noscript>
                    <?php endif; ?>
                </form>

                <!-- ===================== LISTA ===================== -->
                <?php if (!$agendamentos): ?>
                    <p class="observation vazio">Nenhum agendamento <?= e(mb_strtolower(PERIODOS[$periodo])) ?>.</p>
                <?php else: ?>
                    <p class="observation"><?= $total ?> agendamento<?= $total === 1 ? '' : 's' ?></p>

                    <table class="agenda">
                        <thead>
                            <tr>
                                <th>Data</th>
                                <th>Horário</th>
                                <th>Cliente</th>
                                <th>Telefone</th>
                                <th>Serviço</th>
                                <?php if ($ehAdmin): ?><th>Barbeiro</th><?php endif; ?>
                                <th>Observações</th>
                                <th>Ações</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($agendamentos as $ag):
                                $alteravel = agendamentoAlteravel($ag);
                                $whats     = linkWhatsapp($ag['telefone']);
                                $hora      = substr($ag['horario'], 0, 5);
                            ?>
                                <tr class="<?= estaCancelado($ag) ? 'cancelado' : '' ?><?= $remarcando && (int) $remarcando['id'] === (int) $ag['id'] ? ' editando' : '' ?>">
                                    <td data-rotulo="Data"><?= e(formatarData($ag['data'])) ?></td>
                                    <td data-rotulo="Horário"><strong><?= e($hora) ?></strong></td>
                                    <td data-rotulo="Cliente"><?= e($ag['cliente']) ?></td>
                                    <td data-rotulo="Telefone">
                                        <?php if ($whats): ?>
                                            <a href="<?= e($whats) ?>" target="_blank" rel="noopener" title="Abrir no WhatsApp"><?= e(formatarTelefone($ag['telefone'])) ?></a>
                                        <?php else: ?>
                                            <?= e(formatarTelefone($ag['telefone'])) ?>
                                        <?php endif; ?>
                                    </td>
                                    <td data-rotulo="Serviço">
                                        <?= e(nomeServico($ag)) ?>
                                        <?php if (!estaCancelado($ag) && $ag['valor'] !== null): ?>
                                            <small class="valor"><?= e(formatarValor($ag['valor'])) ?></small>
                                        <?php endif; ?>
                                    </td>
                                    <?php if ($ehAdmin): ?>
                                        <td data-rotulo="Barbeiro"><?= e($ag['barbeiro'] ?? '—') ?></td>
                                    <?php endif; ?>
                                    <td data-rotulo="Observações" class="obs"><?= $ag['obs'] !== null && $ag['obs'] !== '' ? e($ag['obs']) : '—' ?></td>
                                    <td data-rotulo="Ações" class="acoes">
                                        <?php if ($alteravel): ?>
                                            <a class="btn" href="<?= e(urlAgenda($filtros, ['remarcar' => (int) $ag['id']])) ?>#remarcar">Remarcar</a>
                                            <form method="post" action="./dashboardBarbeiros.php"
                                                  onsubmit="return confirm(<?= e(json_encode('Cancelar o agendamento de ' . $ag['cliente'] . ' (' . formatarData($ag['data']) . ' às ' . $hora . ')?', JSON_UNESCAPED_UNICODE)) ?>);">
                                                <input type="hidden" name="csrf" value="<?= e($_SESSION['csrf']) ?>">
                                                <input type="hidden" name="acao" value="cancelar">
                                                <input type="hidden" name="id" value="<?= (int) $ag['id'] ?>">
                                                <input type="hidden" name="periodo" value="<?= e($periodo) ?>">
                                                <input type="hidden" name="barbeiro" value="<?= $filtroBarbeiro ?>">
                                                <input type="hidden" name="pagina" value="<?= $paginaAtual ?>">
                                                <button type="submit" class="btn excluir">Cancelar</button>
                                            </form>
                                        <?php elseif (estaCancelado($ag)): ?>
                                            <span class="status">Cancelado</span>
                                        <?php else: ?>
                                            <span class="status">Concluído</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>

                    <?php if ($totalPaginas > 1): ?>
                        <nav class="paginacao" aria-label="Paginação">
                            <?php if ($paginaAtual > 1): ?>
                                <a href="<?= e(urlAgenda($filtros, ['pagina' => $paginaAtual - 1])) ?>" aria-label="Página anterior">&laquo;</a>
                            <?php endif; ?>
                            <?php for ($p = 1; $p <= $totalPaginas; $p++): ?>
                                <?php if ($p === $paginaAtual): ?>
                                    <span class="atual" aria-current="page"><?= $p ?></span>
                                <?php else: ?>
                                    <a href="<?= e(urlAgenda($filtros, ['pagina' => $p])) ?>"><?= $p ?></a>
                                <?php endif; ?>
                            <?php endfor; ?>
                            <?php if ($paginaAtual < $totalPaginas): ?>
                                <a href="<?= e(urlAgenda($filtros, ['pagina' => $paginaAtual + 1])) ?>" aria-label="Próxima página">&raquo;</a>
                            <?php endif; ?>
                        </nav>
                    <?php endif; ?>
                <?php endif; ?>
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