import { showAlert, scrollToElement } from './utils.js';

const inpNome = document.getElementById("nome");
const inpTelefone = document.getElementById("telefone");
const inpDataNasc = document.getElementById("dataNasc");
const inpObservacoes = document.getElementById("observacoes");

let barbeiro = null;
let servico = null;
let data = null;
let horario = null;

const form = document.getElementById("agendamento");

form.addEventListener("submit", async (event) => {
    const nome = inpNome.value.trim();
    const telefone = inpTelefone.value.trim();
    const dataNasc = inpDataNasc.value.trim();
    const observacoes = inpObservacoes.value.trim();

    if (!nome) {
        event.preventDefault();
        scrollToElement(".section-1");
        showAlert("Nome deve ser informado!", false);

        inpNome.focus();
        inpNome.classList.add("error");

        return;
    }
    else if(!/^[A-Za-zÀ-ÿ ]+$/.test(nome)){
        event.preventDefault();
        scrollToElement(".section-1");
        showAlert("Informe um nome válido!", false);

        inpNome.focus();
        inpNome.classList.add("error");

        return;
    }
    else{
        inpNome.classList.remove("error");
    }

    if (!telefone) {
        event.preventDefault();
        scrollToElement(".section-1");
        showAlert("Telefone deve ser informado!", false);

        inpTelefone.focus();
        inpTelefone.classList.add("error");

        return;
    }
    else if(!/^[0-9]{11}$/.test(telefone)){
        event.preventDefault();
        scrollToElement(".section-1");
        showAlert("Informe um telefone válido!", false);

        inpTelefone.focus();
        inpTelefone.classList.add("error");
        
        return;
    }
    else{
        inpTelefone.classList.remove("error");
    }

    if (!dataNasc) {
        event.preventDefault();
        scrollToElement(".section-1");
        showAlert("Data de nascimento deve ser informada!", false);

        inpDataNasc.focus();
        inpDataNasc.classList.add("error");

        return;
    }
    else{
        inpDataNasc.classList.remove("error");
    }

    if(!/^[A-Za-zÀ-ÿ .\-,\n0-9]*$/.test(observacoes)){
        event.preventDefault();
        scrollToElement(".section-1");
        showAlert("Observação conteem caracteres inválidos!", false);

        inpObservacoes.focus();
        inpObservacoes.classList.add("error");

        return;
    }
    else{
        inpObservacoes.classList.remove("error");
    }

    if(!barbeiro){
        event.preventDefault();
        scrollToElement(".section-1");
        showAlert("Selecione um barbeiro!", false);

        return;
    }

    if(!servico){
        event.preventDefault();
        scrollToElement(".servicos");
        showAlert("Selecione um serviço!", false);

        return;
    }

    if(!data || !horario){
        event.preventDefault();
        scrollToElement("#calendar");
        showAlert("Selecione um dia e horário!", false);

        return;
    }

    document.getElementById("barbeiro_id").value = barbeiro;
    document.getElementById("servico_id").value = servico;
    document.getElementById("data").value = data;
    document.getElementById("horario").value = horario;

    showAlert("Agendamento realizado com sucesso!", true);

    event.preventDefault();

    // MUDANÇAS: Recupera as informações de 'registerSchedule.php' para baixar o pdf do agendamento

    try {
        const resp = await fetch(`./registerSchedule.php?nome=${nome}&telefone=${telefone}&dataNasc=${dataNasc}&barbeiro_id=${barbeiro}&servico_id=${servico}&data=${data}&horario=${horario}`, {
            method: "POST",
            body: new FormData(form),
        });
        if (!resp.ok) throw new Error();

        const blob = await resp.blob();

        // dispara o download
        const url = URL.createObjectURL(blob);
        const a = document.createElement("a");
        a.href = url;
        a.download = "agendamento.pdf";
        a.click();
        URL.revokeObjectURL(url);

        // volta para o index depois de um instante
        setTimeout(() => {
            window.location.href = "index.html";
        }, 2000);
    } catch {
        showAlert("Erro ao enviar. Tente novamente.", "error");
    }
});

document.querySelectorAll(".card-barbeiro").forEach((card) => {
    card.addEventListener("click", () => {
        barbeiro = card.dataset.id;
        
        card.classList.add("selected");
        document.querySelectorAll(".card-barbeiro").forEach((card) => {
            if(card.dataset.id != barbeiro){
                card.classList.remove("selected");
            }
        });

        renderHorarios();

        if(barbeiro){
            document.querySelector(".horarios").style.display = "flex";
        }
    });
});

document.querySelectorAll(".card-servico").forEach((card) => {
    card.addEventListener("click", () => {
        servico = card.dataset.id;
        
        card.classList.add("selected");
        document.querySelectorAll(".card-servico").forEach((card) => {
            if(card.dataset.id != servico){
                card.classList.remove("selected");
            }
        });
    });
});

async function getHorariosOcupados() {
    if (!barbeiro || !data) {
        return [];
    }

    try {
        const response = await fetch(`src/php/horarios.php?barbeiroId=${barbeiro}&data=${data}`, {
            method: 'GET',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded'
            }
        });

        const horarios = await response.json();

        return horarios;
    } catch (error) {
        console.error('Erro ao obter agendamentos:', error);
        return [];
    }
}

async function renderHorarios(inicio = "09:00", fim = "20:00", intervaloMin = 30) {
    if(!barbeiro || !data) return;

    const horariosOcupados = await getHorariosOcupados();

    const horariosContainer = document.getElementById("hour_container");
    horariosContainer.innerHTML = "";

    const horarios = [];
    const [hIni, mIni] = inicio.split(":").map(Number);
    const [hFim, mFim] = fim.split(":").map(Number);

    let atual = hIni * 60 + mIni;
    const limite = hFim * 60 + mFim;

    while (atual < limite) {
        const h = String(Math.floor(atual / 60)).padStart(2, "0");
        const m = String(atual % 60).padStart(2, "0");
        horarios.push(`${h}:${m}`);
        atual += intervaloMin;
    }

    horarios.forEach(hora => {
        const button = document.createElement("button");
        button.type = "button";
        button.classList.add("card", "card-horario");
        button.dataset.hora = hora;
        button.textContent = hora;
        button.id = hora;

        const [ano, mes, dia] = data.split("-").map(Number);
        const [h, m] = hora.split(":").map(Number);
        const dataHorario = new Date(ano, mes - 1, dia, h, m);

        const ocupied = horariosOcupados.includes(hora);

        if(ocupied){
            button.disabled = true;
            button.classList.add("ocupied");
        }

        if (dataHorario <= new Date()) {
            button.disabled = true;
        }

        button.addEventListener("click", () => {
            let data_horario = button.dataset.hora;
            horario = data_horario;
            
            document.getElementById(data_horario).classList.add("selected");
            document.querySelectorAll(".card-horario").forEach((button) => {
                if(button.dataset.hora != data_horario){
                    button.classList.remove("selected");
                }
            });
        });

        horariosContainer.appendChild(button);
    });
}

flatpickr("#calendar", {
    inline: true,
    locale: "pt",
    minDate: "today",
    maxDate: new Date().fp_incr(30), 
    disable: [
        date => date.getDay() === 0
    ],
    onChange: (selectedDates, dateStr) => {
        data = dateStr;

        if(selectedDates[0].getDay() === 6){
            renderHorarios("09:00", "18:00");
        }
        else{
            renderHorarios();
        }    
    }
});