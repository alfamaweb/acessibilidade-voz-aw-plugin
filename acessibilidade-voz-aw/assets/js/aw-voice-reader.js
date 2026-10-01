(function () {
    'use strict';

    const config = window.awVoiceReader || {};

    const SEPARADOR = /_/g;
    const NEURAL = /natural|neural/;
    const SUPERIOR = /enhanced|premium/;
    const MULTILINGUE = /multil|mehrsprachig|多语言/;
    const FIM_DE_PALAVRA = /[\s,;:.!?]/;
    const FIM_DE_FRASE = /[.!?;:](?:\s|$)/g;

    /**
     * Desempate entre vozes da mesma tecnologia, da que soa mais natural para
     * a menos. Sem isto as dezesseis neurais pt-BR empatam na pontuação e
     * vence simplesmente a primeira que o navegador listar — ou seja, a
     * escolha fica ao acaso.
     *
     * Isto é dado, não regra: um retrato da nomenclatura dos fornecedores em
     * outubro de 2026, feito de ouvido. Envelhece, e envelhecer aqui não
     * quebra nada: uma voz que saia da lista volta a valer pela tecnologia.
     */
    const PREFERIDAS = {
        'pt-br': ['thalita', 'brenda', 'francisca', 'giovanna', 'leila',
            'yara', 'leticia', 'manuela', 'elza', 'antônio', 'antonio',
            'fabio', 'humberto', 'valerio'],
        'pt-pt': ['raquel', 'fernanda', 'duarte'],
        'en-us': ['ava', 'aria', 'jenny', 'andrew', 'guy'],
        'en-gb': ['sonia', 'libby', 'ryan'],
        'es-es': ['elvira', 'ximena', 'álvaro', 'alvaro']
    };

    /** Bônus de 1 a N, menor que o salto de tecnologia: só desempata. */
    function bonusDeNome(nome, idioma) {
        const ordem = PREFERIDAS[idioma];
        if (!ordem) return 0;
        const posicao = ordem.findIndex(parte => nome.indexOf(parte) !== -1);

        return posicao === -1 ? 0 : ordem.length - posicao;
    }

    function normalizarTag(valor) {
        return String(valor || '').toLowerCase().replace(SEPARADOR, '-');
    }

    /**
     * Melhor voz disponível para o idioma, por pontuação.
     *
     * A região exata pesa mais que a qualidade: uma voz pt-BR robótica
     * pronuncia português brasileiro melhor que uma pt-PT natural.
     *
     * Voz local não é prioridade. No Windows as únicas pt-BR locais são
     * SAPI antigas e soam bem pior que as neurais em rede; localService
     * só desempata quando a qualidade é equivalente.
     *
     * Os marcadores de nome são um retrato da nomenclatura dos fornecedores,
     * não um critério estável: a API não expõe nenhum campo de qualidade.
     */
    function escolherVoz(vozes, idioma) {
        const alvo = normalizarTag(idioma);
        if (!alvo) return null;
        const base = alvo.split('-')[0];

        let melhor = null;
        let melhorNota = -Infinity;

        for (const voz of vozes) {
            const codigo = normalizarTag(voz.lang);
            const nome = String(voz.name || '').toLowerCase();

            let nota;
            if (codigo === alvo) nota = 100;
            else if (codigo.split('-')[0] === base) nota = 50;
            else continue;

            // Marcadores de tecnologia, na ordem em que soam melhor.
            if (NEURAL.test(nome)) nota += 30;
            else if (nome.indexOf('google') !== -1) nota += 25;
            else if (SUPERIOR.test(nome)) nota += 20;

            // Vozes multilíngues são generalistas; a dedicada ao idioma
            // costuma soar melhor. "multil" cobre multilingual e multilíngue.
            if (MULTILINGUE.test(nome)) nota -= 10;

            if (codigo === alvo) nota += bonusDeNome(nome, alvo);

            // Desempates. voice.default não é escolha do usuário: no Windows
            // ele aponta para a voz SAPI legada, justamente a que soa pior.
            // Por isso vale pouco, e nunca o bastante para desbancar uma
            // neural.
            if (voz.localService) nota += 5;
            if (voz.default) nota += 2;

            if (nota > melhorNota) {
                melhorNota = nota;
                melhor = voz;
            }
        }

        return melhor;
    }

    /** Apara espaços nas pontas e devolve o trecho com o offset já corrigido. */
    function podar(trecho, inicio) {
        const semInicio = trecho.replace(/^\s+/, '');
        return {
            texto: semInicio.replace(/\s+$/, ''),
            inicio: inicio + (trecho.length - semInicio.length)
        };
    }

    /**
     * Fim da palavra que começa em charIndex. charLength não é informado por
     * todos os navegadores, então caímos na varredura até o próximo separador.
     */
    function fimDaPalavra(texto, charIndex, charLength) {
        if (charLength > 0) return charIndex + charLength;
        let fim = charIndex;
        while (fim < texto.length && !FIM_DE_PALAVRA.test(texto.charAt(fim))) fim++;
        return fim > charIndex ? fim : charIndex;
    }

    /**
     * Divide em blocos faláveis, cada um sabendo onde começa no texto
     * completo. É esse offset que permite ao onboundary virar posição no DOM.
     */
    function chunks(text) {
        const result = [];
        const limit = 180;
        let base = 0;

        for (const paragrafo of text.split(/\n+/)) {
            // Reencontra o parágrafo no texto original para manter os offsets
            // alinhados com o mapa da captura.
            const posicao = text.indexOf(paragrafo, base);
            base = posicao + paragrafo.length;

            let atual = podar(paragrafo, posicao);

            while (atual.texto.length > limit) {
                const amostra = atual.texto.slice(0, limit + 1);
                let corte = -1;
                let achado;
                FIM_DE_FRASE.lastIndex = 0;
                while ((achado = FIM_DE_FRASE.exec(amostra)) !== null) {
                    if (achado.index >= 40) corte = achado.index + 1;
                }
                if (corte < 1) corte = amostra.lastIndexOf(' ');
                if (corte < 1) corte = limit;

                // Não parte um par UTF-16 ao meio.
                const code = atual.texto.charCodeAt(corte - 1);
                if (code >= 0xD800 && code <= 0xDBFF) corte--;

                // Com um surrogate solto no início, o espaço pode cair no
                // índice 1 e o decremento acima zerar o corte, deixando o
                // restante do mesmo tamanho e travando a aba.
                if (corte < 1) corte = 2;

                const parte = podar(atual.texto.slice(0, corte), atual.inicio);
                if (parte.texto) result.push(parte);

                atual = podar(atual.texto.slice(corte), atual.inicio + corte);
            }

            if (atual.texto) result.push(atual);
        }

        return result;
    }

    function init() {
        const root = document.getElementById('aw-voice-reader-root');
        if (!root || root.dataset.awVoiceReaderReady) return;
        root.dataset.awVoiceReaderReady = 'true';

        const get = name => document.getElementById('aw-voice-reader-' + name);
        const toggle = get('toggle');
        const panel = get('panel');
        const status = get('status');
        const buttons = {
            play: get('play'),
            pause: get('pause'),
            resume: get('resume'),
            stop: get('stop')
        };

        // O idioma vem do WordPress; o atributo lang e o pt-BR cobrem o caso
        // de o script ser carregado sem a configuração localizada.
        const lang = config.lang || root.getAttribute('lang') || 'pt-BR';

        // O leitor nunca depende do módulo de destaque: um plugin que combine
        // ou remova arquivos JS deixa a leitura funcionando, só sem pintura.
        const highlight = window.AWVoiceReaderHighlight || {
            iniciar: function () {},
            palavra: function () {},
            bloco: function () {},
            limpar: function () {}
        };

        function message(value) {
            status.textContent = value;
        }

        function primeiroAtivo() {
            return Object.values(buttons).find(b => !b.disabled);
        }

        function focusAvailable() {
            // Mantém o foco do teclado quando o botão pressionado é desabilitado.
            if (panel.hidden) return;
            if (Object.values(buttons).includes(document.activeElement) &&
                document.activeElement.disabled) {
                const alvo = primeiroAtivo();
                if (alvo) alvo.focus();
            }
        }

        function setPanel(open) {
            panel.hidden = !open;
            toggle.setAttribute('aria-expanded', String(open));
            if (open) {
                const alvo = primeiroAtivo();
                if (alvo) alvo.focus();
            } else {
                toggle.focus();
            }
        }

        toggle.addEventListener('click', () => setPanel(panel.hidden));
        root.addEventListener('keydown', event => {
            if (event.key === 'Escape' && !panel.hidden) {
                event.preventDefault();
                event.stopPropagation();
                setPanel(false);
            }
        });

        root.hidden = false;

        if (!('speechSynthesis' in window && 'SpeechSynthesisUtterance' in window)) {
            Object.values(buttons).forEach(b => { b.disabled = true; });
            message('Este navegador não oferece leitura por voz.');
            return;
        }

        const synth = window.speechSynthesis;

        let state = 'idle';
        let queue = [];
        let index = 0;
        let session = 0;
        let current = null;
        // Mapa da captura atual, usado para traduzir índice em posição no DOM.
        let mapa = null;
        // Escolhida uma vez por leitura: varrer a lista de vozes a cada bloco
        // custaria dezenas de milhares de iterações e daria sempre o mesmo
        // resultado.
        let voz = null;

        function updateControls(nextState, text) {
            state = nextState;
            buttons.play.disabled = state !== 'idle';
            buttons.pause.disabled = state !== 'reading';
            buttons.resume.disabled = state !== 'paused';
            buttons.stop.disabled = state === 'idle';
            if (text) message(text);
        }

        function soltar() {
            queue = [];
            mapa = null;
            index = 0;
            highlight.limpar();
        }

        function stop(text) {
            if (current) synth.cancel();
            session++;
            current = null;
            soltar();
            updateControls('idle', text || 'Leitura parada.');
            focusAvailable();
        }

        function speakNext(token) {
            if (token !== session || state !== 'reading') return;

            if (index >= queue.length) {
                current = null;
                soltar();
                updateControls('idle', 'Leitura concluída.');
                focusAvailable();
                return;
            }

            const bloco = queue[index];

            // O destaque do bloco é o piso, não um plano B: onde o onboundary
            // existe, a palavra refina por cima no primeiro evento; onde não
            // existe, o bloco é o que sobra. Sem isto o primeiro bloco de cada
            // leitura ficaria sem destaque nenhum justamente nos navegadores
            // que não disparam o evento.
            highlight.bloco(bloco.inicio, bloco.inicio + bloco.texto.length);

            const utterance = new SpeechSynthesisUtterance(bloco.texto);

            utterance.lang = lang;
            utterance.rate = 1;
            utterance.volume = 1;
            utterance.pitch = 1;
            if (voz) utterance.voice = voz;

            // Mantém a referência viva até o fim: sem isto o coletor de lixo
            // pode descartar a utterance e a fala é interrompida.
            current = utterance;

            utterance.onboundary = function (event) {
                if (token !== session || current !== utterance) return;
                if (event.name && event.name !== 'word') return;
                highlight.palavra(
                    bloco.inicio + event.charIndex,
                    bloco.inicio + fimDaPalavra(bloco.texto, event.charIndex, event.charLength)
                );
            };

            utterance.onend = function () {
                if (token !== session || current !== utterance) return;
                current = null;
                index++;
                // Se a pausa caiu entre dois blocos, quem retoma é o Continuar.
                if (state === 'reading') speakNext(token);
            };

            utterance.onerror = function (event) {
                if (token !== session || current !== utterance) return;
                const detail = event.error === 'not-allowed'
                    ? 'O navegador bloqueou a voz. Clique em Ouvir novamente.'
                    : 'Não foi possível continuar. Tente clicar em Ouvir novamente.';
                stop(detail);
            };

            try {
                synth.speak(utterance);
            } catch (error) {
                stop('Não foi possível iniciar a voz neste navegador.');
            }
        }

        buttons.play.addEventListener('click', function () {
            if (state !== 'idle') return;

            // A síntese é global ao documento: não assume o controle se outro
            // componente já estiver falando.
            if (synth.speaking || synth.pending) {
                message('Há outra leitura de voz ativa. Encerre-a antes de iniciar.');
                return;
            }

            const capturado = window.AWVoiceReaderContent
                ? window.AWVoiceReaderContent.capture() : null;
            queue = capturado ? chunks(capturado.texto) : [];
            if (!queue.length) {
                message('Não foi encontrado conteúdo principal visível para leitura.');
                return;
            }

            mapa = capturado.mapa;
            highlight.iniciar(capturado.texto, mapa);
            voz = escolherVoz(synth.getVoices(), lang);

            index = 0;
            session++;
            updateControls('reading', 'Lendo o conteúdo da página.');
            if (synth.paused) synth.resume();
            speakNext(session);
            focusAvailable();
        });

        buttons.pause.addEventListener('click', function () {
            if (state !== 'reading') return;
            updateControls('paused', 'Leitura pausada.');
            synth.pause();
            focusAvailable();
        });

        buttons.resume.addEventListener('click', function () {
            if (state !== 'paused') return;
            updateControls('reading', 'Lendo o conteúdo da página.');
            synth.resume();
            if (!current) speakNext(session);
            focusAvailable();
        });

        buttons.stop.addEventListener('click', () => stop());
        window.addEventListener('pagehide', () => stop());

        updateControls('idle', 'Pronto para ouvir.');
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init, { once: true });
    } else {
        init();
    }
})();
