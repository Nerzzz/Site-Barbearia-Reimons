let servicoSelecionado = null;
let isFormOpen = false;

function selecionarServico(servico) {
    const botaoFinalizar = document.getElementById("btnFinalizarServico");
    const elemento = document.getElementById(servico);

    if (servicoSelecionado === servico) {
        document.getElementById(servico).classList.remove("selected");
        botaoFinalizar.classList.remove("show");
        servicoSelecionado = null;
        return;
    }

    if (servicoSelecionado) {
        document.getElementById(servicoSelecionado).classList.remove("selected");
        botaoFinalizar.classList.remove("show");
        servicoSelecionado = null;
    }

    botaoFinalizar.classList.add("show");

    elemento.classList.add("selected");

    servicoSelecionado = servico;

    console.log("Serviço selecionado:", servicoSelecionado);
}

function callForm() {
    const windowForm = document.getElementById("window-form");

    if(!isFormOpen) {
        isFormOpen = true;
        windowForm.style.display = "flex";
        return;
    }
    isFormOpen = false;
    windowForm.style.display = "none";
}