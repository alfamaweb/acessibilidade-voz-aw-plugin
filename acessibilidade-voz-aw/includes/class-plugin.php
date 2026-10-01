<?php

namespace Alfamaweb\VoiceReader;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Liga as classes aos hooks do WordPress. Sem regra de negócio: o que o
 * plugin faz está em Settings, Assets e Renderer.
 */
final class Plugin {

    public static function boot() {
        add_action('wp_enqueue_scripts', array(Assets::class, 'front'));
        add_action('wp_footer', array(Renderer::class, 'footer'), 5);

        if (is_admin()) {
            Settings::register();
            add_action('admin_enqueue_scripts', array(Assets::class, 'admin'));

            // Sem isto a tela existe mas fica escondida num submenu: a
            // listagem de plugins mostraria apenas "Desativar".
            add_filter(
                'plugin_action_links_' . plugin_basename(FILE),
                array(__CLASS__, 'actionLinks')
            );
        }
    }

    public static function actionLinks($links) {
        $url = admin_url('options-general.php?page=' . Settings::PAGE);
        array_unshift(
            $links,
            '<a href="' . esc_url($url) . '">Configurações</a>'
        );

        return $links;
    }

    /**
     * Exclui painel e AJAX (is_admin), feeds e incorporação, onde um botão
     * flutuante não faz sentido ou quebra o formato.
     */
    public static function servesFrontend() {
        return !is_admin() && !is_feed() && !is_embed();
    }

    /**
     * A escolha do painel vence; vazia, segue o site. O get_bloginfo já
     * devolve a tag BCP 47 que a API de voz espera.
     */
    public static function language() {
        $escolhido = Settings::all()['language'];

        return $escolhido ? $escolhido : (get_bloginfo('language') ?: 'pt-BR');
    }
}
