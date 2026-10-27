<?php

namespace Alfamaweb\VoiceReader;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * O markup do leitor, impresso uma única vez no rodapé.
 *
 * Nasce com o atributo hidden: sem JavaScript nada aparece, e o próprio
 * script decide quando revelar o botão.
 */
final class Renderer {

    /** Temas que chamam wp_footer() mais de uma vez não duplicam o widget. */
    private static $rendered = false;

    public static function footer() {
        if (self::$rendered || !Plugin::servesFrontend()) {
            return;
        }
        self::$rendered = true;

        self::markup(Settings::all(), false);
    }

    /**
     * Também usado pela pré-visualização do painel, que mostra o mesmo
     * markup já visível e com o painel aberto. Assim a prévia usa o CSS
     * real do site e não pode divergir dele.
     */
    public static function markup($options, $preview) {
        ?>
        <div
            id="aw-voice-reader-root"
            class="<?php echo esc_attr('aw-voice-reader-' . $options['position']); ?>"
            lang="<?php echo esc_attr(Plugin::language()); ?>"
            <?php echo $preview ? '' : 'hidden'; ?>
        >
            <?php
            // Sem texto visível, o aria-label passa a ser o nome canônico: a
            // regra WCAG 2.5.3 só se aplica quando existe rótulo visível.
            // O ícone é um alto-falante, deliberadamente diferente do boneco
            // de acessibilidade que outros widgets usam.
            ?>
            <button
                id="aw-voice-reader-toggle"
                type="button"
                aria-label="Ouvir esta página em voz alta"
                aria-expanded="<?php echo $preview ? 'true' : 'false'; ?>"
                aria-controls="aw-voice-reader-panel"
            >
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"
                    stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                    aria-hidden="true" focusable="false">
                    <path d="M4 9v6h4l5 4V5L8 9H4z"></path>
                    <path d="M16.5 8.5a5 5 0 0 1 0 7"></path>
                    <path d="M19.5 5.5a9 9 0 0 1 0 13"></path>
                </svg>
            </button>

            <section
                id="aw-voice-reader-panel"
                aria-label="Leitor de voz"
                <?php echo $preview ? '' : 'hidden'; ?>
            >
                <strong id="aw-voice-reader-title">Leitura da página</strong>

                <div id="aw-voice-reader-controls">
                    <button id="aw-voice-reader-play" type="button"
                        aria-label="Ouvir a página desde o início">Ouvir</button>
                    <button id="aw-voice-reader-pause" type="button"
                        aria-label="Pausar a leitura" disabled>Pausar</button>
                    <button id="aw-voice-reader-resume" type="button"
                        aria-label="Continuar a leitura" disabled>Continuar</button>
                    <button id="aw-voice-reader-stop" type="button"
                        aria-label="Parar a leitura" disabled>Parar</button>
                </div>

                <p id="aw-voice-reader-status"
                    role="status" aria-live="polite" aria-atomic="true">
                    Pronto para ouvir.
                </p>
            </section>
        </div>
        <?php
    }
}
