<?php

namespace Alfamaweb\VoiceReader;

if (!defined('ABSPATH')) {
    exit;
}

/** Tudo que entra na fila de CSS e JS, no front-end e no painel. */
final class Assets {

    const STYLE = 'aw-voice-reader-style';
    const SCRIPT = 'aw-voice-reader-script';
    const CONTENT = 'aw-voice-reader-content';
    const HIGHLIGHT = 'aw-voice-reader-highlight';
    const ADMIN_STYLE = 'aw-voice-reader-admin-style';
    const ADMIN_SCRIPT = 'aw-voice-reader-admin-script';

    /** Objeto global lido pelo aw-voice-reader.js. */
    const CONFIG = 'awVoiceReader';

    /** Raio do botão flutuante para cada formato de Settings::FORMATOS. */
    const RAIOS = array(
        'circle' => '50%',
        'rounded' => '28%',
        'square' => '6px',
    );

    const SOMBRA = '0 4px 18px rgb(0 0 0 / 25%)';
    const ESCALA_HOVER = '1.08';

    public static function front() {
        if (!Plugin::servesFrontend()) {
            return;
        }

        wp_enqueue_style(
            self::STYLE,
            URL . 'assets/css/aw-voice-reader.css',
            array(),
            VERSION
        );

        // handle => arquivo e dependências. A ordem importa: o WordPress
        // resolve a cadeia, mas declará-la aqui mantém os três módulos
        // carregando na sequência em que se enxergam.
        $modulos = array(
            self::CONTENT => array('aw-voice-reader-content.js', array()),
            self::HIGHLIGHT => array('aw-voice-reader-highlight.js', array(self::CONTENT)),
            self::SCRIPT => array('aw-voice-reader.js', array(self::CONTENT, self::HIGHLIGHT)),
        );

        foreach ($modulos as $handle => $modulo) {
            wp_enqueue_script(
                $handle,
                URL . 'assets/js/' . $modulo[0],
                $modulo[1],
                VERSION,
                true
            );
        }

        wp_localize_script(self::SCRIPT, self::CONFIG, array(
            'lang' => Plugin::language(),
        ));

        wp_add_inline_style(self::STYLE, self::css(Settings::all()));
    }

    /**
     * As configurações viram variáveis CSS; o arquivo estático só as lê.
     * Cores são hexadecimais validados, distâncias e tamanhos são inteiros
     * com limite: a interpolação é segura. Cores opcionais vazias viram a
     * cor calculada aqui, então o CSS nunca recebe um valor vazio.
     */
    public static function css($options) {
        $fundoControle = $options['control_bg'] ? $options['control_bg'] : $options['color'];
        $destaque = $options['highlight'];

        $widget = array(
            '--aw-voice-reader-color' => $options['color'],
            '--aw-voice-reader-foreground' => $options['icon_color']
                ? $options['icon_color'] : Color::foreground($options['color']),
            '--aw-voice-reader-size' => $options['size'] . 'px',
            '--aw-voice-reader-icon-size' => $options['icon_size'] . '%',
            '--aw-voice-reader-radius' => self::RAIOS[$options['shape']],
            '--aw-voice-reader-shadow' => $options['shadow'] === '1' ? self::SOMBRA : 'none',
            '--aw-voice-reader-hover-scale' => $options['hover'] === '1' ? self::ESCALA_HOVER : '1',
            '--aw-voice-reader-z' => $options['z_index'],
            '--aw-voice-reader-offset-x' => $options['offset_x'] . 'px',
            '--aw-voice-reader-offset-y' => $options['offset_y'] . 'px',
            '--aw-voice-reader-panel-bg' => $options['panel_bg'],
            '--aw-voice-reader-panel-text' => $options['panel_text'],
            '--aw-voice-reader-panel-border' => $options['panel_border'],
            '--aw-voice-reader-panel-radius' => $options['panel_radius'] . 'px',
            '--aw-voice-reader-control-bg' => $fundoControle,
            '--aw-voice-reader-control-fg' => $options['control_text']
                ? $options['control_text'] : Color::foreground($fundoControle),
        );

        // O ::highlight() pinta texto espalhado pelo documento inteiro, então
        // as variáveis dele precisam viver no :root, não dentro do widget.
        $documento = array(
            '--aw-voice-reader-highlight' => $destaque,
            '--aw-voice-reader-highlight-fg' => Color::foreground($destaque),
            '--aw-voice-reader-highlight-soft' => Color::rgba($destaque, '0.3'),
        );

        return '#aw-voice-reader-root{' . self::regras($widget) . '}'
            . ':root{' . self::regras($documento) . '}';
    }

    private static function regras($variaveis) {
        $css = '';
        foreach ($variaveis as $nome => $valor) {
            $css .= $nome . ':' . $valor . ';';
        }

        return $css;
    }

    public static function admin($hook) {
        if ($hook !== Settings::HOOK) {
            return;
        }

        // A pré-visualização é o markup real do site, então precisa do CSS real.
        wp_enqueue_style(
            self::STYLE,
            URL . 'assets/css/aw-voice-reader.css',
            array(),
            VERSION
        );
        wp_add_inline_style(self::STYLE, self::css(Settings::all()));

        wp_enqueue_style(
            self::ADMIN_STYLE,
            URL . 'assets/css/aw-voice-reader-admin.css',
            array('wp-color-picker', self::STYLE),
            VERSION
        );

        wp_enqueue_script(
            self::ADMIN_SCRIPT,
            URL . 'assets/js/aw-voice-reader-admin.js',
            array('wp-color-picker', 'jquery'),
            VERSION,
            true
        );

        // Os mesmos valores que css() usa, para a prévia não divergir do site.
        wp_localize_script(self::ADMIN_SCRIPT, 'awVoiceReaderAdmin', array(
            'raios' => self::RAIOS,
            'sombra' => self::SOMBRA,
            'escala' => self::ESCALA_HOVER,
        ));
    }
}
