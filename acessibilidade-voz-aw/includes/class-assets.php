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

        $options = Settings::all();
        $destaque = $options['highlight'];

        // Cores são hexadecimais validados, distâncias e tamanho são inteiros
        // com limite: a interpolação é segura.
        $widget = array(
            '--aw-voice-reader-color' => $options['color'],
            '--aw-voice-reader-foreground' => Color::foreground($options['color']),
            '--aw-voice-reader-size' => $options['size'] . 'px',
            '--aw-voice-reader-offset-x' => $options['offset_x'] . 'px',
            '--aw-voice-reader-offset-y' => $options['offset_y'] . 'px',
        );

        // O ::highlight() pinta texto espalhado pelo documento inteiro, então
        // as variáveis dele precisam viver no :root, não dentro do widget.
        $documento = array(
            '--aw-voice-reader-highlight' => $destaque,
            '--aw-voice-reader-highlight-fg' => Color::foreground($destaque),
            '--aw-voice-reader-highlight-soft' => Color::rgba($destaque, '0.3'),
        );

        wp_add_inline_style(
            self::STYLE,
            '#aw-voice-reader-root{' . self::regras($widget) . '}'
            . ':root{' . self::regras($documento) . '}'
        );
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

        wp_enqueue_style('wp-color-picker');
        wp_enqueue_style(
            self::ADMIN_STYLE,
            URL . 'assets/css/aw-voice-reader-admin.css',
            array('wp-color-picker'),
            VERSION
        );

        wp_enqueue_script(
            self::ADMIN_SCRIPT,
            URL . 'assets/js/aw-voice-reader-admin.js',
            array('wp-color-picker', 'jquery'),
            VERSION,
            true
        );
    }
}
