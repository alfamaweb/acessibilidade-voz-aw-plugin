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

    /** Sufixo devolvido por add_options_page(), usado no admin_enqueue_scripts. */
    const HOOK = 'settings_page_acessibilidade-voz-aw';

    const DEFAULT_COLOR = '#334155';
    const DEFAULT_HIGHLIGHT = '#ffe066';
    const DEFAULT_PANEL_BG = '#ffffff';
    const DEFAULT_PANEL_TEXT = '#172033';
    const DEFAULT_PANEL_BORDER = '#64748b';

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

    const FORMATOS = array(
        'circle' => 'Círculo',
        'rounded' => 'Arredondado',
        'square' => 'Quadrado',
    );

    /**
     * Mínimo e máximo de cada campo numérico. O botão não desce de 44px,
     * o alvo de toque mínimo da WCAG 2.5.5; as versões 1.x gravavam 48, 56
     * ou 64, todos dentro da faixa.
     */
    const LIMITES = array(
        'offset_x' => array(0, 200),
        'offset_y' => array(0, 400),
        'size' => array(44, 96),
        'icon_size' => array(30, 70),
        'panel_radius' => array(0, 32),
        'z_index' => array(1, 2147483647),
    );

    /** Cores em que vazio é válido e significa "calcular automaticamente". */
    const CORES_OPCIONAIS = array('icon_color', 'control_bg', 'control_text');

    const CORES = array(
        'color', 'icon_color', 'panel_bg', 'panel_text', 'panel_border',
        'control_bg', 'control_text', 'highlight',
    );

    const BOOLEANOS = array('shadow', 'hover');

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

    /**
     * Os padrões reproduzem a aparência da 1.3, para quem atualiza não ver
     * o botão mudar. A exceção é o efeito ao passar o mouse: o sublinhado
     * antigo não aparecia num botão sem texto.
     */
    public static function defaults() {
        return array(
            'language' => '',
            'position' => 'bottom-right',
            'offset_x' => 16,
            'offset_y' => 88,
            'z_index' => 99990,
            'shape' => 'circle',
            'size' => 56,
            'icon_size' => 45,
            'color' => self::DEFAULT_COLOR,
            'icon_color' => '',
            'shadow' => '1',
            'hover' => '1',
            'panel_bg' => self::DEFAULT_PANEL_BG,
            'panel_text' => self::DEFAULT_PANEL_TEXT,
            'panel_border' => self::DEFAULT_PANEL_BORDER,
            'panel_radius' => 14,
            'control_bg' => '',
            'control_text' => '',
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

        $saida = array(
            'language' => self::escolha($input, 'language', self::IDIOMAS, $padrao['language']),
            'position' => self::posicao($input, $padrao['position']),
            'shape' => self::escolha($input, 'shape', self::FORMATOS, $padrao['shape']),
        );

        foreach (array_keys(self::LIMITES) as $chave) {
            $saida[$chave] = self::limite($input, $chave, $padrao[$chave]);
        }

        foreach (self::CORES as $chave) {
            $saida[$chave] = self::cor($input, $chave, $padrao[$chave]);
        }

        foreach (self::BOOLEANOS as $chave) {
            $saida[$chave] = self::booleano($input, $chave, $padrao[$chave]);
        }

        // Mesma ordem dos padrões, para a option gravada ficar legível.
        return array_merge($padrao, $saida);
    }

    private static function escolha($input, $chave, $opcoes, $padrao) {
        return isset($input[$chave]) && is_string($input[$chave]) &&
            array_key_exists($input[$chave], $opcoes)
            ? $input[$chave] : $padrao;
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
        if (!isset($input[$chave]) || !is_string($input[$chave])) {
            return $padrao;
        }

        if ($input[$chave] === '' && in_array($chave, self::CORES_OPCIONAIS, true)) {
            return '';
        }

        $valor = sanitize_hex_color($input[$chave]);

        return $valor ? $valor : $padrao;
    }

    /**
     * Ausente vira o padrão: assim instalações antigas, que não têm a chave,
     * ganham o comportamento padrão. Por isso o checkbox do painel vem com
     * um hidden "0" antes dele — desmarcado, o campo ainda é enviado.
     */
    private static function booleano($input, $chave, $padrao) {
        if (!isset($input[$chave])) {
            return $padrao;
        }

        return (string) $input[$chave] === '1' ? '1' : '0';
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

    /**
     * Campos do painel, agrupados por seção. Cada campo é
     * chave => [rótulo, tipo, ajuda, extras].
     */
    private static function secoes() {
        return array(
            'leitura' => array('Leitura', array(
                'language' => array('Idioma da leitura', 'select',
                    'Define qual voz o navegador deve usar. Em automático, segue o '
                    . 'idioma configurado em Configurações > Geral. Escolha um idioma '
                    . 'fixo quando o site estiver configurado em outro idioma que não o '
                    . 'do conteúdo. A voz final depende das vozes instaladas no '
                    . 'dispositivo de quem acessa.',
                    array('opcoes' => self::IDIOMAS)),
                'highlight' => array('Cor do destaque', 'color',
                    'Cor que marca a palavra sendo lida. A frase ao redor recebe a '
                    . 'mesma cor mais clara, e a cor do texto é calculada para garantir '
                    . 'contraste.'),
            )),
            'posicao' => array('Posição', array(
                'position' => array('Posição do botão', 'radio', '',
                    array('opcoes' => self::POSICOES)),
                'offset_x' => array('Distância lateral', 'number',
                    'Distância da borda lateral, em pixels.'),
                'offset_y' => array('Distância vertical', 'number',
                    'Distância da borda inferior, em pixels. Aumente para não colidir '
                    . 'com outros botões flutuantes, como WhatsApp ou widgets de acessibilidade.'),
                'z_index' => array('Camada (z-index)', 'number',
                    'Aumente se o botão ficar escondido atrás de outro elemento do tema.'),
            )),
            'botao' => array('Botão flutuante', array(
                'shape' => array('Formato', 'select', '',
                    array('opcoes' => self::FORMATOS)),
                'size' => array('Tamanho do botão', 'number',
                    'Em pixels, de 44 a 96. Abaixo de 44 o botão fica difícil de tocar no celular.'),
                'icon_size' => array('Tamanho do ícone', 'number',
                    'Em porcentagem do botão.'),
                'color' => array('Cor de fundo', 'color', ''),
                'icon_color' => array('Cor do ícone', 'color',
                    'Vazio (botão Limpar) escolhe preto ou branco automaticamente, '
                    . 'conforme o contraste com o fundo.'),
                'shadow' => array('Sombra', 'checkbox', '',
                    array('rotulo' => 'Mostrar sombra ao redor do botão')),
                'hover' => array('Efeito ao passar o mouse', 'checkbox', '',
                    array('rotulo' => 'Aumentar levemente o botão (desligado para '
                        . 'quem pede menos movimento no sistema)')),
            )),
            'painel' => array('Painel de controles', array(
                'panel_bg' => array('Cor de fundo', 'color', ''),
                'panel_text' => array('Cor do texto', 'color', ''),
                'panel_border' => array('Cor da borda', 'color', ''),
                'panel_radius' => array('Arredondamento', 'number',
                    'Raio dos cantos do painel, em pixels.'),
                'control_bg' => array('Fundo dos botões', 'color',
                    'Botões Ouvir, Pausar, Continuar e Parar. Vazio usa a cor do botão flutuante.'),
                'control_text' => array('Texto dos botões', 'color',
                    'Vazio escolhe preto ou branco automaticamente.'),
            )),
        );
    }

    /** Campos que ganham aviso de contraste, calculado pelo JS do painel. */
    const CONTRASTE = array('icon_color', 'panel_text', 'control_text');

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

        foreach (self::secoes() as $secao => $dados) {
            list($titulo, $campos) = $dados;
            $id = self::PAGE . '-' . $secao;

            add_settings_section($id, $titulo, '__return_false', self::PAGE);

            foreach ($campos as $chave => $campo) {
                list($rotulo, $tipo, $ajuda) = $campo;
                $extras = isset($campo[3]) ? $campo[3] : array();

                $args = array_merge($extras, array(
                    'chave' => $chave,
                    'tipo' => $tipo,
                    'ajuda' => $ajuda,
                ));

                // Um grupo de rádios tem vários controles: o label_for do
                // WordPress apontaria para apenas um deles.
                if ($tipo !== 'radio') {
                    $args['label_for'] = 'aw-voice-reader-' . $chave;
                }

                add_settings_field(
                    'aw-voice-reader-' . $chave,
                    $rotulo,
                    array(__CLASS__, 'campo'),
                    self::PAGE,
                    $id,
                    $args
                );
            }
        }
    }

    public static function campo($args) {
        $options = self::all();
        $chave = $args['chave'];
        $valor = $options[$chave];
        $id = 'aw-voice-reader-' . $chave;
        $nome = self::OPTION . '[' . $chave . ']';

        switch ($args['tipo']) {
            case 'select':
                ?>
                <select id="<?php echo esc_attr($id); ?>" name="<?php echo esc_attr($nome); ?>">
                    <?php foreach ($args['opcoes'] as $opcao => $rotulo) : ?>
                        <option value="<?php echo esc_attr($opcao); ?>"
                            <?php selected($valor, $opcao); ?>>
                            <?php echo esc_html($rotulo); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <?php
                break;

            case 'radio':
                ?>
                <fieldset>
                    <legend class="screen-reader-text">Posição do botão</legend>
                    <?php foreach ($args['opcoes'] as $opcao => $rotulo) : ?>
                        <label class="aw-voice-reader-radio">
                            <input type="radio"
                                name="<?php echo esc_attr($nome); ?>"
                                value="<?php echo esc_attr($opcao); ?>"
                                <?php checked($valor, $opcao); ?>>
                            <?php echo esc_html($rotulo); ?>
                        </label>
                    <?php endforeach; ?>
                </fieldset>
                <?php
                break;

            case 'number':
                list($minimo, $maximo) = self::LIMITES[$chave];
                ?>
                <input
                    type="number"
                    id="<?php echo esc_attr($id); ?>"
                    name="<?php echo esc_attr($nome); ?>"
                    value="<?php echo esc_attr($valor); ?>"
                    min="<?php echo esc_attr($minimo); ?>"
                    max="<?php echo esc_attr($maximo); ?>"
                    step="1"
                    class="<?php echo $chave === 'z_index' ? 'regular-text' : 'small-text'; ?>"
                >
                <?php
                break;

            case 'color':
                $padrao = self::defaults()[$chave];
                ?>
                <input
                    type="text"
                    class="aw-voice-reader-color"
                    id="<?php echo esc_attr($id); ?>"
                    name="<?php echo esc_attr($nome); ?>"
                    value="<?php echo esc_attr($valor); ?>"
                    data-default-color="<?php echo esc_attr($padrao); ?>"
                    maxlength="7"
                >
                <?php
                break;

            case 'checkbox':
                ?>
                <input type="hidden" name="<?php echo esc_attr($nome); ?>" value="0">
                <label>
                    <input type="checkbox"
                        id="<?php echo esc_attr($id); ?>"
                        name="<?php echo esc_attr($nome); ?>"
                        value="1"
                        <?php checked($valor, '1'); ?>>
                    <?php echo esc_html($args['rotulo']); ?>
                </label>
                <?php
                break;
        }

        if ($args['ajuda'] !== '') {
            echo '<p class="description">' . esc_html($args['ajuda']) . '</p>';
        }

        // Preenchido pelo JS do painel, que recalcula a cada mudança de cor.
        if (in_array($chave, self::CONTRASTE, true)) {
            echo '<p class="aw-voice-reader-contraste" id="' . esc_attr($id . '-contraste')
                . '" aria-live="polite"></p>';
        }
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
            <div class="aw-voice-reader-layout">
                <form class="aw-voice-reader-form"
                    action="<?php echo esc_url(admin_url('options.php')); ?>" method="post">
                    <?php
                    // Adiciona nonce e campos do grupo; options.php checa a permissão.
                    settings_fields(self::GROUP);
                    do_settings_sections(self::PAGE);
                    submit_button('Salvar alterações');
                    ?>
                </form>
                <div class="aw-voice-reader-preview">
                    <h2>Pré-visualização</h2>
                    <?php
                    // Decorativa: os mesmos controles já existem no formulário,
                    // então leitores de tela não precisam passar por ela.
                    ?>
                    <div class="aw-voice-reader-stage" aria-hidden="true">
                        <p class="aw-voice-reader-sample">
                            <span class="aw-voice-reader-sample-frase">Esta frase está
                            sendo lida, e a <span class="aw-voice-reader-sample-palavra">palavra</span>
                            atual fica marcada.</span> O texto seguinte ainda não foi lido.
                        </p>
                        <?php Renderer::markup(self::all(), true); ?>
                    </div>
                    <p class="description">
                        Atualiza enquanto você edita, antes de salvar. As distâncias
                        são reduzidas para caber no quadro.
                    </p>
                </div>
            </div>
            <p>Desenvolvido por Alfamaweb.</p>
        </div>
        <?php
    }
}
