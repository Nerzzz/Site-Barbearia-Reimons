<?php
    // Regras da agenda da barbearia.
    // Usado pelo agendamentos.php

    // Garante que "hoje" e "agora" sejam do horário de Brasília,
    // mesmo que o servidor da hospedagem esteja em outro fuso.
    date_default_timezone_set('America/Sao_Paulo');

    // Duração de cada horário, em minutos
    const DURACAO_HORARIO = 30;

    // Quantos dias para frente é possível remarcar
    const DIAS_MAXIMO_REMARCAR = 90;

    // Horário de funcionamento (o mesmo do rodapé do site).
    // Chave = dia da semana: 1 = segunda ... 6 = sábado, 7 = domingo.
    const HORARIO_FUNCIONAMENTO = [
        1 => ['08:00', '20:00'],
        2 => ['08:00', '20:00'],
        3 => ['08:00', '20:00'],
        4 => ['08:00', '20:00'],
        5 => ['08:00', '20:00'],
        6 => ['08:00', '18:00'],
    ];

    // Tabela de serviços (id, nome, valor, img_url, ativo)
    const TABELA_SERVICOS = 'servicos';

    // Agendamento cancelado = id_servico 999 (não existe serviço com esse id).
    // Assim o cancelamento não apaga o registro e não precisa de coluna nova.
    const SERVICO_CANCELADO = 999;

    const DIAS_SEMANA = [1 => 'Seg', 2 => 'Ter', 3 => 'Qua', 4 => 'Qui', 5 => 'Sex', 6 => 'Sáb', 7 => 'Dom'];

    function estaCancelado(array $ag) {
        return (int) $ag['id_servico'] === SERVICO_CANCELADO;
    }

    // Nome do serviço vindo do JOIN com a tabela de serviços
    function nomeServico(array $ag) {
        if (estaCancelado($ag)) {
            return 'Cancelado';
        }
        return $ag['servico'] ?? ('Serviço #' . (int) $ag['id_servico']);
    }

    // 40 -> "R$ 40,00"
    function formatarValor($valor) {
        return $valor === null ? '' : 'R$ ' . number_format((float) $valor, 2, ',', '.');
    }

    // Converte "2026-11-20" em DateTime, ou null se a data for inválida
    function lerData($data) {
        $dt = DateTime::createFromFormat('!Y-m-d', (string) $data);
        return ($dt && $dt->format('Y-m-d') === $data) ? $dt : null;
    }

    function hoje() {
        return date('Y-m-d');
    }

    // Todos os horários de início do dia, ex.: ["08:00", "08:30", ..., "19:30"].
    // O último é o que ainda termina dentro do expediente.
    function horariosDoDia($data) {
        $dt = lerData($data);
        if (!$dt) {
            return [];
        }

        $diaSemana = (int) $dt->format('N');
        if (!array_key_exists($diaSemana, HORARIO_FUNCIONAMENTO)) {
            return []; // fechado
        }

        [$abre, $fecha] = HORARIO_FUNCIONAMENTO[$diaSemana];
        $inicio = strtotime("$data $abre");
        $fim    = strtotime("$data $fecha");

        $horarios = [];
        for ($t = $inicio; $t + DURACAO_HORARIO * 60 <= $fim; $t += DURACAO_HORARIO * 60) {
            $horarios[] = date('H:i', $t);
        }
        return $horarios;
    }

    // Horários do dia que ainda não passaram (para hoje, corta os que já foram)
    function horariosFuturosDoDia($data) {
        $agora = time();
        return array_values(array_filter(horariosDoDia($data), function ($hora) use ($data, $agora) {
            return strtotime("$data $hora") > $agora;
        }));
    }

    // Data/hora dentro do expediente, no intervalo de 30 min, no futuro e até o limite de dias
    function horarioPermitido($data, $hora) {
        $limite = date('Y-m-d', strtotime('+' . DIAS_MAXIMO_REMARCAR . ' days'));
        return lerData($data) !== null
            && $data <= $limite
            && in_array($hora, horariosFuturosDoDia($data), true);
    }

    // O agendamento ainda pode ser cancelado/remarcado? (não cancelado e ainda não aconteceu)
    function agendamentoAlteravel(array $ag) {
        return !estaCancelado($ag)
            && strtotime($ag['data'] . ' ' . substr($ag['horario'], 0, 5)) > time();
    }

    // "2026-11-20" -> "Sex, 20/11/2026"
    function formatarData($data) {
        $dt = lerData($data);
        return $dt ? DIAS_SEMANA[(int) $dt->format('N')] . ', ' . $dt->format('d/m/Y') : (string) $data;
    }

    // "19999998888" -> "(19) 99999-8888"
    function formatarTelefone($telefone) {
        $n = preg_replace('/\D/', '', (string) $telefone);
        if (strlen($n) === 11) {
            return sprintf('(%s) %s-%s', substr($n, 0, 2), substr($n, 2, 5), substr($n, 7));
        }
        if (strlen($n) === 10) {
            return sprintf('(%s) %s-%s', substr($n, 0, 2), substr($n, 2, 4), substr($n, 6));
        }
        return (string) $telefone;
    }

    // Link para abrir conversa no WhatsApp com o cliente
    function linkWhatsapp($telefone) {
        $n = preg_replace('/\D/', '', (string) $telefone);
        return strlen($n) >= 10 ? 'https://wa.me/55' . $n : null;
    }