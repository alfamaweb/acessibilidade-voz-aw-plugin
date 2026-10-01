<?php

namespace Alfamaweb\VoiceReader;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * A option do plugin e a tela que a edita.
 *
 * Settings::all() é a única porta de leitura das configurações e sempre
 * devolve valores já validados, mesmo que a option tenha sido gravada
 * por fora do painel.
 */
final class Settings {

    /**
     * A chave da option mantém o nome antigo de propósito: renomeá-la
     * descartaria as configurações de quem já tem o plugin instalado.
     */
    const OPTION = 'aw_voice_reader_options';

    const GROUP = 'acessibilidade_voz_aw_settings';
    const PAGE = 'acessibilidade-voz-aw';
    const SECTION = 'acessibilidade-voz-aw-appearance';

    /** Sufixo devolvido por add_options_page(), usado no admin_enqueue_scripts. */
    const HOOK = 'settings_page_acessibilidade-voz-aw';

    const DEFAULT_COLOR = '#334155';
    const DEFAULT_HIGHLIGHT = '#ffe066';

    /**
     * Mapas valor => rótulo, lidos tanto pela validação quanto pelos campos
     * do painel. Duas listas separadas divergiriam em silêncio: a opção
     * apareceria na tela e o sanitize a descartaria ao salvar.
     */
    const POSICOES = array(
        'bottom-right' => 'Inferior direita',
        'bottom-left' => 'Inferior esquerda',
        'middle-right' => 'Meio direita',
        'middle-left' => 'Meio esquerda',
    );

    const TAMANHOS = array(48, 56, 64);

    /** Mínimo e máximo de cada campo numérico, em pixels. */
    const LIMITES = array(
        'offset_x' => array(0, 200),
        'offset_y' => array(0, 400),
    );

    /** Vazio significa seguir o idioma configurado no WordPress. */
    const IDIOMAS = array(
        '' => 'Automático (idioma do site)',
        'pt-BR' => 'Português (Brasil)',
        'pt-PT' => 'Português (Portugal)',
        'en-US' => 'Inglês (Estados Unidos)',
        'es-ES' => 'Espanhol (Espanha)',
    );

    /** Chamado apenas em contexto admin. */
    public static function register() {
        add_action('admin_menu', array(__CLASS__, 'menu'));
        add_action('admin_init', array(__CLASS__, 'fields'));
    }

    public static function all() {
        return self::sanitize(get_option(self::OPTION, array()));
    }

    public static function defaults() {
        return array(
            'language' => '',
            'position' => 'bottom-right',
            'offset_x' => 16,
            'offset_y' => 88,
            'size' => 56,
            'color' => self::DEFAULT_COLOR,
            'highlight' => self::DEFAULT_HIGHLIGHT,
        );
    }

    /**
     * Usado como sanitize_callback na gravação e de novo na leitura, para
     * que o front-end nunca interpole um valor não validado no CSS.
     */
    public static function sanitize($input) {
        $input = is_array($input) ? $input : array();
        $padrao = self::defaults();

        $size = isset($input['size']) ? (int) $input['size'] : 0;
        if (!in_array($size, self::TAMANHOS, true)) {
            $size = $padrao['size'];
        }

        $language = isset($input['language']) && is_string($input['language']) &&
            array_key_exists($input['language'], self::IDIOMAS)
            ? $input['language'] : '';

        return array(
            'language' => $language,
            'position' => self::posicao($input, $padrao['position']),
            'offset_x' => self::limite($input, 'offset_x', $padrao['offset_x']),
            'offset_y' => self::limite($input, 'offset_y', $padrao['offset_y']),
            'size' => $size,
            'color' => self::cor($input, 'color', $padrao['color']),
            'highlight' => self::cor($input, 'highlight', $padrao['highlight']),
        );
    }

    /**
     * Instalações da 1.x gravavam "side" com left ou right. A conversão
     * acontece na leitura, então nenhuma rotina de atualização é necessária.
     */
    private static function posicao($input, $padrao) {
        if (isset($input['position']) &&
            is_string($input['position']) &&
            array_key_exists($input['position'], self::POSICOES)) {
            return $input['position'];
        }

        if (isset($input['side'])) {
            if ($input['side'] === 'left') {
                return 'bottom-left';
            }
            if ($input['side'] === 'right') {
                return 'bottom-right';
            }
        }

        return $padrao;
    }

    private static function limite($input, $chave, $padrao) {
        if (!isset($input[$chave]) || !is_numeric($input[$chave])) {
            return $padrao;
        }

        list($minimo, $maximo) = self::LIMITES[$chave];

        return max($minimo, min($maximo, (int) $input[$chave]));
    }

    private static function cor($input, $chave, $padrao) {
        $valor = isset($input[$chave]) && is_string($input[$chave])
            ? sanitize_hex_color($input[$chave]) : null;

        return $valor ? $valor : $padrao;
    }

    public static function menu() {
        add_options_page(
            'Acessibilidade Voz AW',
            'Acessibilidade Voz AW',
            'manage_options',
            self::PAGE,
            array(__CLASS__, 'page')
        );
    }

    public static function fields() {
        register_setting(
            self::GROUP,
            self::OPTION,
            array(
                'type' => 'array',
                'sanitize_callback' => array(__CLASS__, 'sanitize'),
                'default' => self::defaults(),
                'show_in_rest' => false,
            )
        );

        add_settings_section(
            self::SECTION,
            '',
            '__return_false',
            self::PAGE
        );

        $campos = array(
            array('language', 'Idioma da leitura', 'languageField', true),
            array('position', 'Posição do botão', 'positionField', false),
            array('offset_x', 'Distância lateral', 'offsetXField', true),
            array('offset_y', 'Distância vertical', 'offsetYField', true),
            array('size', 'Tamanho do botão', 'sizeField', true),
            array('color', 'Cor do botão', 'colorField', true),
            array('highlight', 'Cor do destaque', 'highlightField', true),
        );

        foreach ($campos as $campo) {
            list($chave, $rotulo, $metodo, $rotulavel) = $campo;
            add_settings_field(
                'aw-voice-reader-' . $chave,
                $rotulo,
                array(__CLASS__, $metodo),
                self::PAGE,
                self::SECTION,
                // Um grupo de rádios tem vários controles: o label_for do
                // WordPress apontaria para apenas um deles.
                $rotulavel ? array('label_for' => 'aw-voice-reader-' . $chave) : array()
            );
        }
    }

    public static function languageField() {
        $options = self::all();
        ?>
        <select
            id="aw-voice-reader-language"
            name="<?php echo esc_attr(self::OPTION . '[language]'); ?>"
        >
            <?php foreach (self::IDIOMAS as $valor => $rotulo) : ?>
                <option value="<?php echo esc_attr($valor); ?>"
                    <?php selected($options['language'], $valor); ?>>
                    <?php echo esc_html($rotulo); ?>
                </option>
            <?php endforeach; ?>
        </select>
        <p class="description">
            Define qual voz o navegador deve usar. Em automático, segue o
            idioma configurado em Configurações &gt; Geral. Escolha um idioma
            fixo quando o site estiver configurado em outro idioma que não o
            do conteúdo. A voz final depende das vozes instaladas no
            dispositivo de quem acessa.
        </p>
        <?php
    }

    public static function positionField() {
        $options = self::all();
        ?>
        <fieldset>
            <legend class="screen-reader-text">Posição do botão</legend>
            <?php foreach (self::POSICOES as $valor => $rotulo) : ?>
                <label class="aw-voice-reader-radio">
                    <input type="radio"
                        name="<?php echo esc_attr(self::OPTION . '[position]'); ?>"
                        value="<?php echo esc_attr($valor); ?>"
                        <?php checked($options['position'], $valor); ?>>
                    <?php echo esc_html($rotulo); ?>
                </label>
            <?php endforeach; ?>
        </fieldset>
        <?php
    }

    public static function offsetXField() {
        self::numero('offset_x', 'Distância da borda lateral, em pixels.');
    }

    public static function offsetYField() {
        self::numero('offset_y',
            'Distância da borda inferior, em pixels. Aumente para não colidir '
            . 'com outros botões flutuantes, como WhatsApp ou widgets de acessibilidade.');
    }

    private static function numero($chave, $ajuda) {
        $options = self::all();
        list($minimo, $maximo) = self::LIMITES[$chave];
        ?>
        <input
            type="number"
            id="<?php echo esc_attr('aw-voice-reader-' . $chave); ?>"
            name="<?php echo esc_attr(self::OPTION . '[' . $chave . ']'); ?>"
            value="<?php echo esc_attr($options[$chave]); ?>"
            min="<?php echo esc_attr($minimo); ?>"
            max="<?php echo esc_attr($maximo); ?>"
            step="1"
            class="small-text"
        >
        <p class="description"><?php echo esc_html($ajuda); ?></p>
        <?php
    }

    public static function sizeField() {
        $options = self::all();
        ?>
        <select
            id="aw-voice-reader-size"
            name="<?php echo esc_attr(self::OPTION . '[size]'); ?>"
        >
            <?php foreach (self::TAMANHOS as $valor) : ?>
                <option value="<?php echo esc_attr($valor); ?>"
                    <?php selected($options['size'], $valor); ?>>
                    <?php echo esc_html($valor . ' pixels'); ?>
                </option>
            <?php endforeach; ?>
        </select>
        <?php
    }

    public static function colorField() {
        self::campoCor('color', self::DEFAULT_COLOR,
            'Cor de fundo do botão flutuante. O ícone fica preto ou branco '
            . 'automaticamente, conforme o contraste.');
    }

    public static function highlightField() {
        self::campoCor('highlight', self::DEFAULT_HIGHLIGHT,
            'Cor que marca a palavra sendo lida. A frase ao redor recebe a '
            . 'mesma cor mais clara, e a cor do texto é calculada para garantir '
            . 'contraste.');
    }

    private static function campoCor($chave, $padrao, $ajuda) {
        $options = self::all();
        ?>
        <input
            type="text"
            id="<?php echo esc_attr('aw-voice-reader-' . $chave); ?>"
            name="<?php echo esc_attr(self::OPTION . '[' . $chave . ']'); ?>"
            value="<?php echo esc_attr($options[$chave]); ?>"
            data-default-color="<?php echo esc_attr($padrao); ?>"
            maxlength="7"
        >
        <p class="description"><?php echo esc_html($ajuda); ?></p>
        <?php
    }

    public static function page() {
        if (!current_user_can('manage_options')) {
            return;
        }

        // Páginas criadas por add_options_page() não carregam o options-head.php
        // do core, então a confirmação de gravação precisa ser registrada aqui.
        if (isset($_GET['settings-updated'])) {
            add_settings_error(
                self::OPTION,
                'aw-voice-reader-saved',
                'Configurações salvas.',
                'updated'
            );
        }
        ?>
        <div class="wrap aw-voice-reader-admin">
            <h1>Acessibilidade Voz AW</h1>
            <p>Personalize o botão de leitura por voz do site.</p>
            <?php settings_errors(self::OPTION); ?>
            <form action="<?php echo esc_url(admin_url('options.php')); ?>" method="post">
                <?php
                // Adiciona nonce e campos do grupo; options.php checa a permissão.
                settings_fields(self::GROUP);
                do_settings_sections(self::PAGE);
                submit_button('Salvar alterações');
                ?>
            </form>
            <p>Desenvolvido por Alfamaweb.</p>
        </div>
        <?php
    }
}
