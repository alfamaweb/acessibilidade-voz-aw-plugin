<?php
/**
 * Plugin Name: Acessibilidade Voz AW
 * Description: Leitura por voz do conteúdo principal usando a API nativa do navegador.
 * Version: 1.4.0
 * Author: Alfamaweb
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Requires at least: 6.0
 * Requires PHP: 7.4
 */

namespace Alfamaweb\VoiceReader;

if (!defined('ABSPATH')) {
    exit;
}

define(__NAMESPACE__ . '\VERSION', '1.4.0');
define(__NAMESPACE__ . '\FILE', __FILE__);
define(__NAMESPACE__ . '\URL', plugin_dir_url(__FILE__));
define(__NAMESPACE__ . '\PATH', plugin_dir_path(__FILE__));

require_once PATH . 'includes/class-color.php';
require_once PATH . 'includes/class-settings.php';
require_once PATH . 'includes/class-assets.php';
require_once PATH . 'includes/class-renderer.php';
require_once PATH . 'includes/class-plugin.php';

Plugin::boot();
