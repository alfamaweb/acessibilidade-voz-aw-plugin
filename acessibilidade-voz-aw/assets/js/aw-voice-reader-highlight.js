(function () {
    'use strict';

    const LIMITE_FRASE = 400;
    const FIM_DE_FRASE = /[.!?\n]/;
    const ESPACO = /\s/;
    const MARGEM = 80;
    const NOME_PALAVRA = 'aw-voice-reader-palavra';
    const NOME_FRASE = 'aw-voice-reader-frase';

    let textoAtual = '';
    let mapaAtual = null;
    let fraseAtual = null;
    let destPalavra = null;
    let destFrase = null;

    /**
     * A CSS Custom Highlight API pinta sem tocar no DOM do site. Onde ela
     * não existe, a leitura continua funcionando sem destaque.
     */
    function suportado() {
        return typeof CSS !== 'undefined' &&
            !!CSS.highlights &&
            typeof window.Highlight === 'function';
    }

    /**
     * Os dois destaques são criados e inscritos uma vez só. Reinscrever no
     * registry a cada palavra invalidaria o registro inteiro em vez do range
     * que mudou.
     */
    function registrar() {
        if (destPalavra) return true;
        if (!suportado()) return false;

        destFrase = new window.Highlight();
        destPalavra = new window.Highlight();
        CSS.highlights.set(NOME_FRASE, destFrase);
        CSS.highlights.set(NOME_PALAVRA, destPalavra);

        return true;
    }

    function pintar(destaque, range) {
        destaque.clear();
        if (range) destaque.add(range);
    }

    /**
     * Intervalo da frase que contém o índice. Expande até pontuação final ou
     * quebra de linha, com teto para não pintar um parágrafo inteiro quando
     * o texto não tem pontuação.
     */
    function frase(texto, indice) {
        const piso = Math.max(0, indice - LIMITE_FRASE);
        let inicio = indice;
        while (inicio > piso && !FIM_DE_FRASE.test(texto.charAt(inicio - 1))) inicio--;
        while (inicio < texto.length && ESPACO.test(texto.charAt(inicio))) inicio++;

        const teto = Math.min(texto.length, inicio + LIMITE_FRASE);
        let fim = Math.max(indice, inicio);
        while (fim < teto && !FIM_DE_FRASE.test(texto.charAt(fim))) fim++;
        // Inclui a pontuação final, mas não a quebra de linha.
        if (fim < teto && texto.charAt(fim) !== '\n') fim++;

        return { inicio: inicio, fim: fim };
    }

    function iniciar(texto, mapa) {
        limpar();
        textoAtual = texto || '';
        mapaAtual = mapa || null;
    }

    /**
     * Solta o mapa junto com a pintura: ele guarda uma referência a um nó de
     * texto por palavra da página, e segurá-las impede a coleta de subárvores
     * que o tema venha a remover do DOM.
     */
    function limpar() {
        fraseAtual = null;
        textoAtual = '';
        mapaAtual = null;
        if (destPalavra) {
            destPalavra.clear();
            destFrase.clear();
        }
    }

    function intervalo(inicio, fim) {
        if (!mapaAtual || !window.AWVoiceReaderContent) return null;
        return window.AWVoiceReaderContent.range(mapaAtual, inicio, fim);
    }

    /**
     * Rolar a cada palavra enjoa e desorienta. Só vale quando o destaque saiu
     * da área útil; a margem evita que ele fique colado nas bordas.
     */
    function rolar(range) {
        if (!range || typeof range.getBoundingClientRect !== 'function') return;

        // Medir força um flush de layout, então isto só é chamado na troca de
        // frase ou de bloco, nunca a cada palavra.
        const rect = range.getBoundingClientRect();
        const altura = window.innerHeight;
        if (rect.top >= MARGEM && rect.bottom <= altura - MARGEM) return;

        const reduzido = typeof window.matchMedia === 'function' &&
            window.matchMedia('(prefers-reduced-motion: reduce)').matches;

        window.scrollTo({
            top: window.scrollY + rect.top - (altura / 2),
            behavior: reduzido ? 'auto' : 'smooth'
        });
    }

    /** Destaca a palavra falada e, por baixo, a frase que a contém. */
    function palavra(inicio, fim) {
        if (!registrar()) return;

        // A frase só é recalculada quando a leitura sai dos limites da
        // anterior. Recalcular a cada palavra custaria centenas de testes de
        // regex para devolver quase sempre o mesmo intervalo.
        if (!fraseAtual || inicio < fraseAtual.inicio || inicio >= fraseAtual.fim) {
            fraseAtual = frase(textoAtual, inicio);
            const r = intervalo(fraseAtual.inicio, fraseAtual.fim);
            pintar(destFrase, r);
            rolar(r);
        }

        pintar(destPalavra, intervalo(inicio, fim));
    }

    /**
     * Piso do destaque: marca o bloco inteiro ao começar a falá-lo. Onde o
     * onboundary existe, a primeira palavra refina por cima.
     */
    function bloco(inicio, fim) {
        if (!registrar()) return;

        const r = intervalo(inicio, fim);
        pintar(destPalavra, null);
        pintar(destFrase, r);
        // Zerado de propósito: a próxima palavra recalcula a frase real, em
        // vez de herdar os limites do bloco.
        fraseAtual = null;
        rolar(r);
    }

    window.AWVoiceReaderHighlight = {
        iniciar: iniciar,
        palavra: palavra,
        bloco: bloco,
        limpar: limpar
    };
})();
