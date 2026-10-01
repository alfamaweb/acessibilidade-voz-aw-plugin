<?php

namespace Alfamaweb\VoiceReader;

if (!defined('ABSPATH')) {
    exit;
}

/** Contraste de cor. Não depende do WordPress. */
final class Color {

    /**
     * Preto ou branco sobre um fundo sRGB opaco. A opção vencedora sempre
     * supera 4.5:1: o pior caso da curva fica em torno de 4.54:1.
     */
    public static function foreground($hex) {
        $luminance = self::luminance($hex);
        $onWhite = 1.05 / ($luminance + 0.05);
        $onBlack = ($luminance + 0.05) / 0.05;

        return $onWhite >= $onBlack ? '#ffffff' : '#000000';
    }

    /** Luminância relativa conforme a WCAG 2: 0 é preto, 1 é branco. */
    private static function luminance($hex) {
        $channels = self::channels($hex);

        return 0.2126 * $channels[0]
             + 0.7152 * $channels[1]
             + 0.0722 * $channels[2];
    }

    /**
     * A mesma cor em rgba(), para o fundo suave da frase destacada.
     *
     * O alfa entra como string justamente para não precisar formatar float
     * em CSS: quem chama escreve '0.3' e é isso que sai.
     */
    public static function rgba($hex, $alpha) {
        return 'rgba(' . implode(', ', self::bytes($hex)) . ', ' . $alpha . ')';
    }

    /** Os três canais em sRGB linear, de 0 a 1. */
    private static function channels($hex) {
        $channels = array();
        foreach (self::bytes($hex) as $byte) {
            $value = $byte / 255;
            $channels[] = $value <= 0.04045
                ? $value / 12.92
                : pow(($value + 0.055) / 1.055, 2.4);
        }

        return $channels;
    }

    /**
     * Canais de 0 a 255. Única cópia da expansão de 3 dígitos e do
     * tratamento de entrada inválida, que vira preto em vez de gerar aviso.
     */
    private static function bytes($hex) {
        $hex = ltrim((string) $hex, '#');

        if (strlen($hex) === 3) {
            $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1]
                 . $hex[2] . $hex[2];
        }

        if (strlen($hex) !== 6 || !ctype_xdigit($hex)) {
            $hex = '000000';
        }

        return array(
            (int) hexdec(substr($hex, 0, 2)),
            (int) hexdec(substr($hex, 2, 2)),
            (int) hexdec(substr($hex, 4, 2)),
        );
    }
}
