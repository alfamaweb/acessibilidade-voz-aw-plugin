(function () {
    'use strict';

    const EXCLUIDOS = [
        'header', 'nav', 'footer', 'aside', 'menu',
        'button', 'input', 'select', 'textarea', 'form',
        'script', 'style', 'noscript', 'template',
        'svg', 'canvas', 'iframe', 'audio', 'video',
        'dialog', '[role="dialog"]', '[role="alertdialog"]',
        '[role="navigation"]', '[role="menu"]',
        '[role="menubar"]', '[role="button"]',
        '[role="banner"]', '[role="contentinfo"]',
        '[role="complementary"]', '[aria-modal="true"]',
        '[hidden]', '[inert]', '[aria-hidden="true"]',
        // Classes de widget do core são prefixadas: widget_text, widget_nav_menu.
        // ".widget" e ".menu" sozinhos são genéricos demais e engolem
        // conteúdo legítimo, como uma página de cardápio.
        '[class^="widget_"]', '[class*=" widget_"]',
        '.widget-area', '.widget-title', '.sidebar',
        '.menu-item', '.nav-menu', '.main-navigation',
        '.modal', '.popup', '.pop-up',
        '.elementor-popup-modal', '.pum', '.pum-container',
        '.wp-block-navigation', '.wp-block-buttons',
        '.screen-reader-text', '.sr-only',
        '[vw]', '[vw-access-button]', '[vw-plugin-wrapper]',
        '#vlibras', '.vlibras-widget',
        // O próprio leitor: o Renderer garante este id como raiz única, então
        // não há motivo para procurá-lo por substring.
        '#aw-voice-reader-root',
        '[id*="onetap"]', '[class*="onetap"]',
        '[id*="OneTap"]', '[class*="OneTap"]',
        '[data-aw-voice-reader-ignore]'
    ].join(',');

    const ESPACO = /\s/;
    const CORRIDA = /\s+|\S+/g;
    const BLOCO = /^(block|flex|grid|list-item|table)/;

    /** Recebe o estilo já computado: resolvê-lo duas vezes por nó custa caro. */
    function oculto(css) {
        return css.display === 'none' ||
            css.visibility === 'hidden' ||
            css.visibility === 'collapse' ||
            css.contentVisibility === 'hidden' ||
            css.opacity === '0';
    }

    /**
     * Texto principal da página e o mapa que liga cada intervalo ao nó de
     * origem, para o destaque saber onde pintar.
     *
     * A normalização acontece durante a coleta, e não depois: colapsar
     * espaços no fim deslocaria todos os índices já registrados.
     */
    function capture() {
        const container = document.querySelector('main') ||
            document.querySelector('.entry-content') ||
            document.querySelector('article');

        if (!container) return null;

        for (let a = container.parentElement; a; a = a.parentElement) {
            // As classes de body e html descrevem a página, não um componente
            // a pular: aplicar a exclusão nesse nível descartaria o site todo
            // por causa de uma marca de terceiro como "onetap-enabled".
            // A visibilidade continua valendo: body oculto esconde tudo mesmo.
            const raiz = a === document.body || a === document.documentElement;
            if (!raiz && a.matches(EXCLUIDOS)) return null;
            if (oculto(window.getComputedStyle(a))) return null;
        }

        const mapa = [];
        let texto = '';

        function empurrar(trecho, node, nodeOffset, exato) {
            mapa.push({
                inicio: texto.length,
                fim: texto.length + trecho.length,
                node: node,
                nodeOffset: nodeOffset,
                exato: exato
            });
            texto += trecho;
        }

        // Só é chamada para segmentos de um caractere, criados por espaco()
        // ou quebra(), nunca para uma corrida de texto.
        function remover() {
            const ultimo = mapa.pop();
            texto = texto.slice(0, ultimo.inicio);
        }

        function espaco(node, nodeOffset) {
            if (!texto || texto.endsWith(' ') || texto.endsWith('\n')) return;
            empurrar(' ', node, nodeOffset, false);
        }

        function quebra() {
            if (texto.endsWith(' ')) remover();
            if (!texto || texto.endsWith('\n')) return;
            empurrar('\n', null, 0, false);
        }

        function adicionar(node) {
            const valor = node.nodeValue;
            let m;
            CORRIDA.lastIndex = 0;
            while ((m = CORRIDA.exec(valor)) !== null) {
                if (ESPACO.test(m[0].charAt(0))) {
                    // Uma corrida de espaços que contém quebra de linha vira
                    // quebra; as demais viram um único espaço.
                    if (m[0].indexOf('\n') !== -1) quebra();
                    else espaco(node, m.index);
                } else {
                    empurrar(m[0], node, m.index, true);
                }
            }
        }

        function walk(node) {
            if (node.nodeType === Node.TEXT_NODE) { adicionar(node); return; }
            if (node.nodeType !== Node.ELEMENT_NODE) return;
            if (node.matches(EXCLUIDOS)) return;

            const css = window.getComputedStyle(node);
            if (oculto(css)) return;
            if (node.tagName === 'BR') { quebra(); return; }

            const bloco = BLOCO.test(css.display);
            if (bloco) quebra();

            // Percorre os filhos ao vivo: walk() não altera o DOM, então não
            // há por que copiar a lista para um array a cada elemento.
            const fechado = node.tagName === 'DETAILS' && !node.open;
            for (let filho = node.firstChild; filho; filho = filho.nextSibling) {
                if (!fechado || filho.tagName === 'SUMMARY') walk(filho);
            }

            if (bloco) quebra();
        }

        walk(container);

        while (texto.endsWith(' ') || texto.endsWith('\n')) remover();

        return { texto: texto, mapa: mapa };
    }

    function segmentoDe(mapa, indice) {
        let baixo = 0;
        let alto = mapa.length - 1;
        while (baixo <= alto) {
            const meio = (baixo + alto) >> 1;
            const s = mapa[meio];
            if (indice < s.inicio) alto = meio - 1;
            else if (indice >= s.fim) baixo = meio + 1;
            else return s;
        }
        return null;
    }

    /** Posição no DOM correspondente a um índice do texto, ou null. */
    function resolve(mapa, indice) {
        const s = segmentoDe(mapa, indice);
        if (!s || !s.node) return null;
        // Segmento inexato é um bloco de espaços colapsado num só: nenhuma
        // palavra cai nele, então basta apontar para o início.
        const offset = s.exato ? s.nodeOffset + (indice - s.inicio) : s.nodeOffset;
        return { node: s.node, offset: offset };
    }

    /** Range do intervalo [inicio, fim), ou null se não cair em nós de texto. */
    function range(mapa, inicio, fim) {
        if (!(fim > inicio)) return null;
        const a = resolve(mapa, inicio);
        const b = resolve(mapa, fim - 1);
        if (!a || !b) return null;
        try {
            const r = document.createRange();
            r.setStart(a.node, a.offset);
            r.setEnd(b.node, b.offset + 1);
            return r;
        } catch (e) {
            return null;
        }
    }

    window.AWVoiceReaderContent = {
        capture: capture,
        range: range
    };
})();
