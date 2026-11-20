<?php
    require_once("./src/php/bdconn.php");
?>

<!DOCTYPE html>
<html lang="pt-br">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Agendamento</title>

        <link rel="shortcut icon" href="public/icons/favicon.png" type="image/x-icon">

        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">

        <link rel="stylesheet" href="src/styles/root.css">
        <link rel="stylesheet" href="src/styles/scheduling.css">
        <link rel="stylesheet" href="src/styles/media.css">

        <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
        <script src="https://cdn.jsdelivr.net/npm/flatpickr/dist/l10n/pt.js"></script>

        <script defer type="module" src="./src/script/scheduling.js"></script>
    </head>
    <body>
        <main>
            <section>
                <h2>Agendamento de Horário</h2>
                <form id="agendamento" action="registerSchedule.php" method="POST">
                    <input type="hidden" name="barbeiro_id" id="barbeiro_id">
                    <input type="hidden" name="servico_id" id="servico_id">
                    <input type="hidden" name="data" id="data">
                    <input type="hidden" name="horario" id="horario">

                    <div class="form">
                        <div class="section-1">
                            <div class="inputs">
                                <div class="input">
                                    <input type="text" name="nome" id="nome" placeholder="John Doe" maxlength="50">
                                    <label>Nome completo</label>
                                </div>
                                <div class="input">
                                    <input type="tel" name="telefone" id="telefone" placeholder="xx xxxxx-xxxx" maxlength="11">
                                    <label>Telefone</label>
                                </div>
                                <div class="input">
                                    <input type="date" name="dataNasc" id="dataNasc">
                                    <label>Data de nascimento</label>
                                </div>
                                <div class="input">
                                    <textarea name="observacoes" id="observacoes" placeholder="Se for necessário mais algum detelhe, escreva aqui" maxlength="128"></textarea>
                                    <label>Observações</label>
                                </div>
                            </div>
                            <div class="barbers">
                                <h4>Escolha um profissional</h4>
                                <div class="content" onclick="" id="">
                                    <?php
                                        $sql = "SELECT id, nome, email, img_url FROM barbeiros WHERE ativo = 1";
                                        $result = mysqli_query($conn, $sql);
                                        
                                        if (mysqli_num_rows($result) > 0) {
                                            while ($row = mysqli_fetch_assoc($result)) {
                                                echo '<button type="button" class="card card-barbeiro" data-id="' . $row['id'] . '" id="' . $row['id'] . '">';
                                                
                                                if($row['img_url']) {
                                                    echo '<img src="' . $row['img_url'] . '" alt="Foto do barbeiro">';
                                                } else {
                                                    echo '<img src="./public/images/logo/light.svg" alt="Foto do barbeiro">';
                                                }

                                                echo '<span>' . $row['nome'] . '</span>';
                                                echo '</button>';
                                            }
                                        } else {
                                            echo "<span class='no_results'>Nenhum barbeiro encontrado</span>";
                                        }
                                    ?>
                                </div>
                            </div>
                        </div>
                        <div class="servicos">
                            <h4>Selecione um serviço</h4>
                            <span class="observation">Imagens ilustrativas, não representam o serviço real</span>
                            <div class="content">

                                <?php
                                    $sql = "SELECT id, nome, valor, img_url FROM servicos WHERE ativo = 1";
                                    $result = mysqli_query($conn, $sql);
                                    
                                    if (mysqli_num_rows($result) > 0) {
                                        while ($row = mysqli_fetch_assoc($result)) {
                                            echo '<button type="button" class="card card-servico" data-id="' . $row['id'] . '" id="' . $row['id'] . '">';
                                            
                                            if($row['img_url']) {
                                                echo '<img src="' . $row['img_url'] . '" alt="Imagem do serviço">';
                                            } else {
                                                echo '<img src="./public/images/logo/light.svg" alt="Imagem do serviço">';
                                            }

                                            echo '<span>' . $row['nome'] . '</span>';
                                            echo '<span class="valor">R$ ' . number_format($row['valor'], 2, ',', '.') . '</span>';
                                            echo '</button>';
                                        }
                                    } else {
                                        echo "<span class='no_results'>Nenhum serviço encontrado</span>";
                                    }
                                ?>

                                <button class="card" onclick="" id="">
                                    <img src="./public/images/barba.webp" alt="Corte de barba">
                                    <span>Corte barba</span>
                                    <span class="valor">R$ 40,00</span>
                                </button>

                                <button class="card" onclick="" id="">
                                    <img src="./public/images/corte-cabelo.webp" alt="Corte de cabelo">
                                    <span>Corte cabelo</span>
                                    <span class="valor">R$ 40,00</span>
                                </button>

                                <button class="card" onclick="" id="">
                                    <img src="./public/images/homem_barbudo.webp" alt="Corte de barba e sobrancelha">
                                    <span>Barba e sobrancelha</span>
                                    <span class="valor">R$ 75,00</span>
                                </button>

                                <button class="card" onclick="" id="">
                                    <img src="./public/images/cortecabelobarba.webp" alt="Corte de cabelo e barba">
                                    <span>Corte cabelo e barba</span>
                                    <span class="valor">R$ 65,00</span>
                                </button>

                                <button class="card" onclick="" id="">
                                    <img src="./public/images/cabelosobrancelha.webp" alt="Corte de cabelo e sobrancelha">
                                    <span>Cabelo e sobrancelha</span>
                                    <span class="valor">R$ 50,00</span>
                                </button>

                                <button class="card" onclick="" id="">
                                    <img src="./public/images/depilacaoouvido.webp" alt="Depilação de ouvido com cera">
                                    <span>Dep. Ouvido (Cera)</span>
                                    <span class="valor">R$ 20,00</span>
                                </button>

                                <button class="card" onclick="" id="">
                                    <img src="./public/images/hidratacao.webp" alt="Hidratação capilar">
                                    <span>Hidratação</span>
                                    <span class="valor">R$ 35,00</span>
                                </button>
                                
                                <button class="card" onclick="" id="">
                                    <img src="./public/images/pezinho.webp" alt="Pézinho">
                                    <span>Pézinho</span>
                                    <span class="valor">R$ 20,00</span>
                                </button>
                                
                                <button class="card" onclick="" id="">
                                    <img src="./public/images/sobrancelha.webp" alt="sobrancelha">
                                    <span>Sobrancelha</span>
                                    <span class="valor">R$ 20,00</span>
                                </button>
                            </div>
                        </div>
                        <div class="horarios">
                            <h4>Selecione o dia e horário</h4>
                            <div class="content">
                                <div class="calendar">
                                    <div id="calendar"></div>
                                </div>
                                <div id="horarios">
                                    <span>Selecione um horário</span>
                                    <div id="hour_container" class="container">
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="form-action">
                        <input type="submit" value="Confirmar agendamento">
                        <a class="btn" href="./index.html">Cancelar</a>
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