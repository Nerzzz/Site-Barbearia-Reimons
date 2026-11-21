<?php
    // Funções das imagens enviadas pelo painel: fotos dos barbeiros e dos serviços.
    // Usado pelo dashboard.php e servicos.php (upload) e por qualquer página que exiba as imagens.

    const PASTA_FOTOS      = 'public/images/barbeiros';
    const PASTA_SERVICOS   = 'public/images/servicos';
    // Pastas onde o painel pode gravar, renomear e apagar imagens
    const PASTAS_IMAGENS   = [PASTA_FOTOS, PASTA_SERVICOS];
    const FOTO_PADRAO      = 'public/images/logo/light.svg';
    const FOTO_TAMANHO_MAX = 2 * 1024 * 1024; // 2 MB
    const FOTO_TIPOS       = [
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/webp' => 'webp',
    ];

    // Raiz do site = a pasta que contém "public/".
    // Procura a partir da pasta deste arquivo e sobe no máximo 2 níveis, então funciona
    // tanto com o fotos.php em htdocs/ quanto em htdocs/src/php/, sem sair do htdocs
    // (hospedagens com open_basedir, como a byethost, bloqueiam pastas acima dele).
    function raizSite() {
        static $raiz = null;
        if ($raiz !== null) {
            return $raiz;
        }
        foreach ([__DIR__, dirname(__DIR__), dirname(__DIR__, 2)] as $pasta) {
            if (@is_dir($pasta . '/public')) {
                return $raiz = $pasta;
            }
        }
        // Último recurso: a pasta da página que está rodando (ex.: dashboard.php)
        return $raiz = dirname($_SERVER['SCRIPT_FILENAME']);
    }

    function caminhoAbsoluto($relativo) {
        // Aceita "public/..." e "./public/..." do mesmo jeito
        $relativo = preg_replace('#^(\./|/)+#', '', $relativo);
        return raizSite() . '/' . $relativo;
    }

    // "Otávio Júnior" -> "otavio-junior"
    function slugNome($nome) {
        $mapa = [
            'á'=>'a','à'=>'a','â'=>'a','ã'=>'a','ä'=>'a','é'=>'e','è'=>'e','ê'=>'e','ë'=>'e',
            'í'=>'i','ì'=>'i','î'=>'i','ï'=>'i','ó'=>'o','ò'=>'o','ô'=>'o','õ'=>'o','ö'=>'o',
            'ú'=>'u','ù'=>'u','û'=>'u','ü'=>'u','ç'=>'c','ñ'=>'n',
        ];
        $slug = strtr(mb_strtolower($nome, 'UTF-8'), $mapa);
        $slug = preg_replace('/[^a-z0-9]+/', '-', $slug);
        $slug = trim(substr($slug, 0, 60), '-');
        return $slug !== '' ? $slug : 'imagem';
    }

    // Caminho padronizado: <pasta>/<nome>-<id>.<ext>
    // ex.: public/images/barbeiros/gustavo-2.jpg ou public/images/servicos/corte-cabelo-2.webp
    // O id no final evita que dois cadastros com o mesmo nome dividam a imagem.
    function caminhoFoto($id, $nome, $ext, $pasta = PASTA_FOTOS) {
        return $pasta . '/' . slugNome($nome) . '-' . (int) $id . '.' . $ext;
    }

    // Em qual pasta do painel está a imagem (ou null se estiver em outro lugar).
    // Aceita caminhos gravados com ou sem "./" no começo.
    function pastaDaFoto($caminho) {
        if (!is_string($caminho) || $caminho === '') {
            return null;
        }
        $caminho = preg_replace('#^(\./|/)+#', '', $caminho);
        foreach (PASTAS_IMAGENS as $pasta) {
            if (strpos($caminho, $pasta . '/') === 0) {
                return $pasta;
            }
        }
        return null;
    }

    function fotoEstaNaPasta($caminho) {
        return pastaDaFoto($caminho) !== null;
    }

    function extensaoFoto($caminho) {
        return strtolower(pathinfo($caminho, PATHINFO_EXTENSION));
    }

    // Confere o arquivo enviado. Retorna null se nada foi enviado ou se houve erro.
    function validarFoto($arquivo, array &$erros) {
        $codigo = $arquivo['error'] ?? UPLOAD_ERR_NO_FILE;

        if ($codigo === UPLOAD_ERR_NO_FILE) {
            return null;
        }
        if ($codigo === UPLOAD_ERR_INI_SIZE || $codigo === UPLOAD_ERR_FORM_SIZE || $arquivo['size'] > FOTO_TAMANHO_MAX) {
            $erros[] = 'A foto deve ter no máximo 2 MB.';
            return null;
        }
        if ($codigo !== UPLOAD_ERR_OK || !is_uploaded_file($arquivo['tmp_name'])) {
            $erros[] = 'Não foi possível receber a foto. Tente novamente.';
            return null;
        }

        // Verifica o conteúdo real do arquivo, não a extensão informada
        $mime = (new finfo(FILEINFO_MIME_TYPE))->file($arquivo['tmp_name']);
        if (!array_key_exists($mime, FOTO_TIPOS) || @getimagesize($arquivo['tmp_name']) === false) {
            $erros[] = 'A foto deve ser uma imagem JPG, PNG ou WebP.';
            return null;
        }

        return ['tmp' => $arquivo['tmp_name'], 'ext' => FOTO_TIPOS[$mime]];
    }

    // Cria a pasta (se preciso) com um .htaccess que bloqueia scripts (Apache)
    function prepararPastaFotos($pastaRelativa = PASTA_FOTOS) {
        $pasta = caminhoAbsoluto($pastaRelativa);

        if (!is_dir($pasta) && !mkdir($pasta, 0755, true)) {
            return false;
        }

        $htaccess = $pasta . '/.htaccess';
        if (!is_file($htaccess)) {
            file_put_contents($htaccess,
                "<FilesMatch \"\\.(php|phtml|phar|php\\d)$\">\n    Require all denied\n</FilesMatch>\nOptions -Indexes\n"
            );
        }
        return is_writable($pasta);
    }

    // Guarda o upload num arquivo temporário dentro da pasta, até o banco confirmar
    function receberFoto(array $foto, $pasta = PASTA_FOTOS) {
        if (!prepararPastaFotos($pasta)) {
            return null;
        }
        $temporario = caminhoAbsoluto($pasta) . '/.envio-' . bin2hex(random_bytes(8));
        return move_uploaded_file($foto['tmp'], $temporario) ? $temporario : null;
    }

    function descartarTemporario($temporario) {
        if ($temporario && is_file($temporario)) {
            @unlink($temporario);
        }
    }

    // Move/renomeia um arquivo para o caminho final, substituindo o que já existir lá
    function moverFoto($origemAbsoluta, $destinoRelativo) {
        $destino = caminhoAbsoluto($destinoRelativo);
        if (!is_file($origemAbsoluta)) {
            return false;
        }
        if ($origemAbsoluta === $destino) {
            return true;
        }
        if (is_file($destino)) {
            @unlink($destino); // necessário no Windows, onde rename() não sobrescreve
        }
        return rename($origemAbsoluta, $destino);
    }

    // Apaga uma imagem, mas só se ela estiver dentro de uma das pastas do painel
    function apagarFoto($caminho) {
        $pasta = pastaDaFoto($caminho);
        if ($pasta === null) {
            return;
        }
        $arquivo = caminhoAbsoluto($pasta . '/' . basename($caminho));
        if (is_file($arquivo)) {
            @unlink($arquivo);
        }
    }

    // Endereço para usar no <img src>. Como o nome do arquivo se repete quando a
    // foto é trocada, o "?v=" com a data do arquivo força o navegador a baixar a nova.
    function urlFoto($caminho) {
        if (!$caminho) {
            return FOTO_PADRAO;
        }
        $pasta = pastaDaFoto($caminho);
        if ($pasta === null) {
            return $caminho;
        }
        $arquivo = caminhoAbsoluto($pasta . '/' . basename($caminho));
        return is_file($arquivo) ? $caminho . '?v=' . filemtime($arquivo) : FOTO_PADRAO;
    }