// Prévia da foto de perfil antes de salvar
const inputFoto   = document.getElementById('foto');
const preview     = document.getElementById('foto-preview');
const removerFoto = document.getElementById('remover_foto');

const TAMANHO_MAX = 2 * 1024 * 1024; // 2 MB, igual ao PHP
const TIPOS       = ['image/jpeg', 'image/png', 'image/webp'];

if (inputFoto && preview) {
    const fotoOriginal = preview.src;
    const fotoPadrao   = preview.dataset.padrao;
    let urlTemporaria  = null;

    const atualizarPreview = () => {
        if (urlTemporaria) {
            URL.revokeObjectURL(urlTemporaria);
            urlTemporaria = null;
        }

        const arquivo = inputFoto.files[0];
        inputFoto.setCustomValidity('');

        if (arquivo) {
            if (!TIPOS.includes(arquivo.type)) {
                inputFoto.setCustomValidity('Escolha uma imagem JPG, PNG ou WebP.');
            } else if (arquivo.size > TAMANHO_MAX) {
                inputFoto.setCustomValidity('A foto deve ter no máximo 2 MB.');
            }

            if (inputFoto.validationMessage) {
                inputFoto.reportValidity();
                inputFoto.value = '';
            } else {
                urlTemporaria = URL.createObjectURL(arquivo);
                preview.src = urlTemporaria;
                if (removerFoto) removerFoto.checked = false;
                return;
            }
        }

        preview.src = removerFoto && removerFoto.checked ? fotoPadrao : fotoOriginal;
    };

    inputFoto.addEventListener('change', atualizarPreview);

    if (removerFoto) {
        removerFoto.addEventListener('change', () => {
            if (removerFoto.checked) inputFoto.value = '';
            atualizarPreview();
        });
    }
}
