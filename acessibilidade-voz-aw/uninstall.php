<?php
/**
 * Remove os dados do plugin ao desinstalar.
 *
 * Settings só depende de ABSPATH para carregar, então dá para usar a
 * constante da classe em vez de repetir o nome da option aqui — repetido,
 * ele sobreviveria a um rename e deixaria lixo no banco sem nenhum aviso.
 */

if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

require_once __DIR__ . '/includes/class-settings.php';

const AW_VOICE_READER_OPTION = \Alfamaweb\VoiceReader\Settings::OPTION;

if (is_multisite()) {
    foreach (get_sites(array('fields' => 'ids', 'number' => 0)) as $site) {
        switch_to_blog($site);
        delete_option(AW_VOICE_READER_OPTION);
        restore_current_blog();
    }
} else {
    delete_option(AW_VOICE_READER_OPTION);
}
