(function ($, config) {
    'use strict';

    /*
     * Cópia em JS do Color do PHP: a prévia precisa da mesma cor automática
     * que o site vai calcular ao salvar.
     */
    function bytes(hex) {
        hex = hex.replace('#', '');
        if (hex.length === 3) {
            hex = hex[0] + hex[0] + hex[1] + hex[1] + hex[2] + hex[2];
        }
        return [0, 2, 4].map(function (i) {
            return parseInt(hex.substr(i, 2), 16);
        });
    }

    /** Luminância relativa conforme a WCAG 2. */
    function luminancia(hex) {
        var canais = bytes(hex).map(function (byte) {
            var v = byte / 255;
            return v <= 0.04045 ? v / 12.92 : Math.pow((v + 0.055) / 1.055, 2.4);
        });
        return 0.2126 * canais[0] + 0.7152 * canais[1] + 0.0722 * canais[2];
    }

    function contraste(a, b) {
        var la = luminancia(a);
        var lb = luminancia(b);
        return (Math.max(la, lb) + 0.05) / (Math.min(la, lb) + 0.05);
    }

    function frente(hex) {
        var l = luminancia(hex);
        return 1.05 / (l + 0.05) >= (l + 0.05) / 0.05 ? '#ffffff' : '#000000';
    }

    function formatar(numero) {
        return numero.toFixed(1).replace('.', ',');
    }

    $(function () {
        var $form = $('.aw-voice-reader-form');
        var stage = document.querySelector('.aw-voice-reader-stage');
        var root = document.getElementById('aw-voice-reader-root');

        function campo(chave) {
            return $form.find('[name="aw_voice_reader_options[' + chave + ']"]');
        }

        function valor(chave) {
            var $campo = campo(chave);
            if ($campo.is(':radio')) {
                return $campo.filter(':checked').val();
            }
            if ($campo.filter(':checkbox').length) {
                return $campo.filter(':checkbox').is(':checked') ? '1' : '0';
            }
            return $.trim($campo.val() || '');
        }

        /** Hex válido digitado, senão o padrão do campo (vazio nos opcionais). */
        function cor(chave) {
            var v = valor(chave);
            return /^#([0-9a-f]{3}){1,2}$/i.test(v) ? v : String(campo(chave).data('default-color') || '');
        }

        /** Número dentro do min/max do próprio campo, como o sanitize fará. */
        function numero(chave) {
            var $campo = campo(chave);
            var n = parseInt($campo.val(), 10);
            var minimo = parseInt($campo.attr('min'), 10);
            var maximo = parseInt($campo.attr('max'), 10);
            if (isNaN(n)) {
                n = parseInt($campo.prop('defaultValue'), 10);
            }
            return Math.max(minimo, Math.min(maximo, n));
        }

        function aviso(chave, texto, fundo, minimo, automatico) {
            var $msg = $('#aw-voice-reader-' + chave + '-contraste');
            var razao = contraste(texto, fundo);
            var baixo = razao < minimo;
            var msg = (automatico ? 'Automático: ' : '') + 'contraste de ' + formatar(razao) + ':1';

            msg += baixo
                ? ' — abaixo do mínimo de ' + formatar(minimo) + ':1 da WCAG. Pode ficar ilegível para parte do público.'
                : ' — dentro do recomendado.';

            // Reescrever o mesmo texto faria o aria-live repetir o anúncio.
            if ($msg.text() !== msg) {
                $msg.text(msg).toggleClass('is-baixo', baixo);
            }
        }

        function atualizar() {
            var fundo = cor('color');
            var icone = cor('icon_color') || frente(fundo);
            var fundoControle = cor('control_bg') || fundo;
            var textoControle = cor('control_text') || frente(fundoControle);
            var painel = cor('panel_bg');
            var textoPainel = cor('panel_text');
            var destaque = cor('highlight');
            var rgb = bytes(destaque);

            // Ícone é elemento gráfico (WCAG 1.4.11, 3:1); o resto é texto (4.5:1).
            aviso('icon_color', icone, fundo, 3, !cor('icon_color'));
            aviso('control_text', textoControle, fundoControle, 4.5, !cor('control_text'));
            aviso('panel_text', textoPainel, painel, 4.5, false);

            if (!root) {
                return;
            }

            var variaveis = {
                '--aw-voice-reader-color': fundo,
                '--aw-voice-reader-foreground': icone,
                '--aw-voice-reader-size': numero('size') + 'px',
                '--aw-voice-reader-icon-size': numero('icon_size') + '%',
                '--aw-voice-reader-radius': config.raios[valor('shape')] || config.raios.circle,
                '--aw-voice-reader-shadow': valor('shadow') === '1' ? config.sombra : 'none',
                '--aw-voice-reader-hover-scale': valor('hover') === '1' ? config.escala : '1',
                // No quadro as distâncias reais não caberiam.
                '--aw-voice-reader-offset-x': Math.min(numero('offset_x'), 24) + 'px',
                '--aw-voice-reader-offset-y': Math.min(numero('offset_y'), 24) + 'px',
                '--aw-voice-reader-panel-bg': painel,
                '--aw-voice-reader-panel-text': textoPainel,
                '--aw-voice-reader-panel-border': cor('panel_border'),
                '--aw-voice-reader-panel-radius': numero('panel_radius') + 'px',
                '--aw-voice-reader-control-bg': fundoControle,
                '--aw-voice-reader-control-fg': textoControle
            };

            Object.keys(variaveis).forEach(function (nome) {
                root.style.setProperty(nome, variaveis[nome]);
            });

            stage.style.setProperty('--aw-voice-reader-highlight', destaque);
            stage.style.setProperty('--aw-voice-reader-highlight-fg', frente(destaque));
            stage.style.setProperty('--aw-voice-reader-highlight-soft',
                'rgba(' + rgb.join(', ') + ', 0.3)');

            root.className = 'aw-voice-reader-' + (valor('position') || 'bottom-right');
        }

        // A prévia é decorativa: o Tab não deve parar nos botões dela.
        $(stage).find('button').attr('tabindex', '-1');

        // O picker atualiza o input depois de disparar o evento.
        function depois() {
            setTimeout(atualizar, 0);
        }

        $form.find('.aw-voice-reader-color').wpColorPicker({
            change: depois,
            clear: depois
        });

        $form.on('input change', 'input, select', atualizar);

        atualizar();
    });
})(jQuery, window.awVoiceReaderAdmin);
