<?php
/**
 * Plugin Name: TN Display Density
 * Description: Compensates for desktop display scaling on Windows and macOS.
 * Version: 0.1.1
 * Requires at least: 7.0
 * Requires PHP: 7.4
 * Author: Techn
 * Author URI: https://techn.com.au
 * Update URI: https://github.com/cchatterton/tn-display-density
 * Techn Controller API: 1
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: tn-display-density
 */

if (!defined('ABSPATH')) {
    exit;
}

define('TDD_VERSION', '0.1.1');

require_once __DIR__ . '/controller-client.php';
tnuc_client_register(__FILE__, 'tn-display-density');

function tdd_get_settings() {
    $saved = get_option('tdd_settings', array());

    return wp_parse_args(is_array($saved) ? $saved : array(), array(
        'windows_compensation' => 0.85,
        'mac_compensation'     => 0,
    ));
}

function tdd_sanitize_settings($input) {
    $previous = tdd_get_settings();
    $settings = array();
    $limits = array(
        'windows_compensation' => array(0, 1),
        'mac_compensation'     => array(0, 1.25),
    );

    foreach ($limits as $key => $range) {
        $raw = is_array($input) && isset($input[$key]) ? $input[$key] : null;
        $valid = is_scalar($raw) && is_numeric($raw);
        $value = $valid ? (float) $raw : 0;
        $valid = $valid && is_finite($value) && $value >= $range[0] && $value <= $range[1];

        if ($key === 'mac_compensation' && $value !== 0.0 && $value < 0.65) {
            $valid = false;
        }

        if (!$valid) {
            $settings[$key] = $previous[$key];
            add_settings_error(
                'tdd_settings',
                'tdd_invalid_' . $key,
                $key === 'windows_compensation'
                    ? __('Windows compensation must be between 0 and 1.', 'tn-display-density')
                    : __('Mac compensation must be 0 for native zoom or between 0.65 and 1.25.', 'tn-display-density')
            );
            continue;
        }

        $settings[$key] = $value;
    }

    return $settings;
}

function tdd_register_settings() {
    register_setting('tdd_settings', 'tdd_settings', array(
        'type'              => 'array',
        'sanitize_callback' => 'tdd_sanitize_settings',
        'default'           => array(
            'windows_compensation' => 0.85,
            'mac_compensation'     => 0,
        ),
    ));

    add_settings_section('tdd_calibration', '', '__return_false', 'tdd_settings');
    add_settings_field(
        'tdd_windows_compensation',
        __('Windows compensation', 'tn-display-density'),
        'tdd_render_windows_field',
        'tdd_settings',
        'tdd_calibration'
    );
    add_settings_field(
        'tdd_mac_compensation',
        __('Mac compensation', 'tn-display-density'),
        'tdd_render_mac_field',
        'tdd_settings',
        'tdd_calibration'
    );
}
add_action('admin_init', 'tdd_register_settings');

function tdd_render_windows_field() {
    $settings = tdd_get_settings();
    ?>
    <input type="number" id="tdd_windows_compensation" name="tdd_settings[windows_compensation]"
        value="<?php echo esc_attr((string) $settings['windows_compensation']); ?>"
        min="0" max="1" step="0.01" required aria-describedby="tdd_windows_help">
    <p class="description" id="tdd_windows_help">
        <?php esc_html_e('Default 0.85. Use 0 to leave Windows at native zoom; 1 applies full inverse-DPR compensation.', 'tn-display-density'); ?>
    </p>
    <?php
}

function tdd_render_mac_field() {
    $settings = tdd_get_settings();
    ?>
    <input type="number" id="tdd_mac_compensation" name="tdd_settings[mac_compensation]"
        value="<?php echo esc_attr((string) $settings['mac_compensation']); ?>"
        min="0" max="1.25" step="0.01" required aria-describedby="tdd_mac_help">
    <p class="description" id="tdd_mac_help">
        <?php esc_html_e('Default 0 leaves macOS at native zoom. Otherwise enter the zoom factor directly, from 0.65 to 1.25 (for example, 0.85 for 85%).', 'tn-display-density'); ?>
    </p>
    <?php
}

function tdd_add_settings_page() {
    add_options_page(
        __('Display Density', 'tn-display-density'),
        __('Display Density', 'tn-display-density'),
        'manage_options',
        'tn-display-density',
        'tdd_render_settings_page'
    );
}
add_action('admin_menu', 'tdd_add_settings_page');

function tdd_render_settings_page() {
    if (!current_user_can('manage_options')) {
        return;
    }
    ?>
    <div class="wrap">
        <h1><?php esc_html_e('Display Density', 'tn-display-density'); ?></h1>
        <p><?php esc_html_e('Compensate for desktop display scaling. Mobile and tablet devices are left unchanged.', 'tn-display-density'); ?></p>
        <?php settings_errors('tdd_settings'); ?>
        <form action="options.php" method="post">
            <?php
            settings_fields('tdd_settings');
            do_settings_sections('tdd_settings');
            submit_button(__('Save settings', 'tn-display-density'));
            ?>
        </form>
        <p class="description"><?php echo esc_html(sprintf(__('TN Display Density v%s', 'tn-display-density'), TDD_VERSION)); ?></p>
    </div>
    <?php
}

function tdd_enqueue_script() {
    wp_enqueue_script(
        'tdd-display-density',
        plugins_url('display-density.js', __FILE__),
        array(),
        TDD_VERSION,
        false
    );

    $settings = tdd_get_settings();
    wp_add_inline_script(
        'tdd-display-density',
        'window.TDDSettings = ' . wp_json_encode($settings) . ';',
        'before'
    );
}
add_action('wp_enqueue_scripts', 'tdd_enqueue_script');
