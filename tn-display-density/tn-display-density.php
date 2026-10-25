<?php
/**
 * Plugin Name: TN Display Density
 * Description: Compensates for desktop display scaling on Windows and macOS.
 * Version: 0.2.1
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

define('TDD_VERSION', '0.2.1');

require_once __DIR__ . '/controller-client.php';
tnuc_client_register(__FILE__, 'tn-display-density');

function tdd_get_settings() {
    $saved = get_option('tdd_settings', array());

    return wp_parse_args(is_array($saved) ? $saved : array(), array(
        'windows_compensation' => 0.85,
        'mac_compensation'     => 0,
        'profiles'             => array(),
    ));
}

function tdd_sanitize_settings($input) {
    $previous = tdd_get_settings();
    $settings = array();
    foreach (array('windows_compensation', 'mac_compensation') as $key) {
        $raw = is_array($input) && isset($input[$key]) ? $input[$key] : null;
        $valid = is_scalar($raw) && is_numeric($raw);
        $value = $valid ? (float) $raw : 0;
        $valid = $valid && is_finite($value) && $value >= 0;

        if (!$valid) {
            $settings[$key] = $previous[$key];
            add_settings_error(
                'tdd_settings',
                'tdd_invalid_' . $key,
                $key === 'windows_compensation'
                    ? __('Windows compensation must be a finite, non-negative number.', 'tn-display-density')
                    : __('Mac compensation must be 0 for native zoom or a positive, finite zoom factor.', 'tn-display-density')
            );
            continue;
        }

        $settings[$key] = $value;
    }

    $settings['profiles'] = array();
    $submitted = is_array($input) && isset($input['profiles']) && is_array($input['profiles'])
        ? $input['profiles'] : array();

    $previous_profiles = isset($previous['profiles']) && is_array($previous['profiles'])
        ? array_values($previous['profiles']) : array();

    foreach ($submitted as $index => $raw_profile) {
        if (is_array($raw_profile) && !empty($raw_profile['remove'])) {
            continue;
        }

        $profile = tdd_sanitize_profile($raw_profile);
        if ($profile === null) {
            add_settings_error('tdd_settings', 'tdd_invalid_profile', __('An invalid display calibration was kept at its previous value.', 'tn-display-density'));
            $profile = isset($previous_profiles[$index]) ? $previous_profiles[$index] : null;
        }
        if ($profile !== null) {
            $settings['profiles'][tdd_profile_key($profile)] = $profile;
        }
    }

    if (is_array($input) && !empty($input['save_current'])) {
        $profile = tdd_sanitize_profile(isset($input['current']) ? $input['current'] : null);
        if ($profile === null) {
            add_settings_error('tdd_settings', 'tdd_invalid_current', __('This display could not be identified. No calibration was saved for it.', 'tn-display-density'));
        } else {
            $settings['profiles'][tdd_profile_key($profile)] = $profile;
        }
    }

    return $settings;
}

function tdd_profile_key($profile) {
    return $profile['platform'] . '|' . number_format($profile['dpr'], 2, '.', '') . '|' . $profile['width'] . 'x' . $profile['height'];
}

function tdd_sanitize_profile($raw) {
    if (!is_array($raw) || !isset($raw['platform'], $raw['dpr'], $raw['width'], $raw['height'], $raw['value'])) {
        return null;
    }

    $platform = is_scalar($raw['platform']) ? (string) $raw['platform'] : '';
    $dpr = is_scalar($raw['dpr']) && is_numeric($raw['dpr']) ? (float) $raw['dpr'] : 0;
    $width = filter_var($raw['width'], FILTER_VALIDATE_INT);
    $height = filter_var($raw['height'], FILTER_VALIDATE_INT);
    $value = is_scalar($raw['value']) && is_numeric($raw['value']) ? (float) $raw['value'] : -1;

    if (!in_array($platform, array('windows', 'mac'), true) || !is_finite($dpr) || $dpr < 0.5 || $dpr > 8 ||
        $width === false || $width < 320 || $width > 20000 ||
        $height === false || $height < 200 || $height > 20000 || !is_finite($value)) {
        return null;
    }

    if ($value < 0) {
        return null;
    }

    return array(
        'platform' => $platform,
        'dpr'      => round($dpr, 2),
        'width'    => $width,
        'height'   => $height,
        'value'    => $value,
        'label'    => isset($raw['label']) && is_scalar($raw['label'])
            ? sanitize_text_field((string) $raw['label']) : '',
    );
}

function tdd_register_settings() {
    register_setting('tdd_settings', 'tdd_settings', array(
        'type'              => 'array',
        'sanitize_callback' => 'tdd_sanitize_settings',
        'default'           => array(
            'windows_compensation' => 0.85,
            'mac_compensation'     => 0,
            'profiles'             => array(),
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
    add_settings_field(
        'tdd_current_display',
        __('Current display', 'tn-display-density'),
        'tdd_render_current_display',
        'tdd_settings',
        'tdd_calibration'
    );
    add_settings_field(
        'tdd_saved_displays',
        __('Saved display scenarios', 'tn-display-density'),
        'tdd_render_saved_displays',
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
        min="0" step="any" required aria-describedby="tdd_windows_help">
    <p class="description" id="tdd_windows_help">
        <?php esc_html_e('Default 0.85. Use 0 for native zoom, 1 for full inverse-DPR compensation, or a higher value for a stronger adjustment.', 'tn-display-density'); ?>
    </p>
    <?php
}

function tdd_render_mac_field() {
    $settings = tdd_get_settings();
    ?>
    <input type="number" id="tdd_mac_compensation" name="tdd_settings[mac_compensation]"
        value="<?php echo esc_attr((string) $settings['mac_compensation']); ?>"
        min="0" step="any" required aria-describedby="tdd_mac_help">
    <p class="description" id="tdd_mac_help">
        <?php esc_html_e('Default 0 leaves macOS at native zoom. Set a positive factor for standard-density displays (for example, 0.85 for 85%). Retina-range displays stay native.', 'tn-display-density'); ?>
    </p>
    <?php
}

function tdd_render_current_display() {
    ?>
    <p id="tdd_current_summary" aria-live="polite">
        <?php esc_html_e('Detecting this display…', 'tn-display-density'); ?>
    </p>
    <label for="tdd_current_value"><?php esc_html_e('Calibration for this display', 'tn-display-density'); ?></label>
    <input type="number" id="tdd_current_value" name="tdd_settings[current][value]"
        min="0" step="any" disabled aria-describedby="tdd_current_help">
    <p>
        <label for="tdd_current_label"><?php esc_html_e('Scenario name (optional)', 'tn-display-density'); ?></label>
        <input type="text" id="tdd_current_label" name="tdd_settings[current][label]"
            class="regular-text" placeholder="<?php echo esc_attr__('For example, Dell monitor or More Space', 'tn-display-density'); ?>" disabled>
    </p>
    <input type="hidden" id="tdd_current_platform" name="tdd_settings[current][platform]">
    <input type="hidden" id="tdd_current_dpr" name="tdd_settings[current][dpr]">
    <input type="hidden" id="tdd_current_width" name="tdd_settings[current][width]">
    <input type="hidden" id="tdd_current_height" name="tdd_settings[current][height]">
    <p class="description" id="tdd_current_help">
        <?php esc_html_e('Switch the operating-system display mode or move this window to another monitor, then save a calibration for the observed scenario. Windows uses non-negative inverse-DPR strength; Mac uses 0 for native or a positive direct zoom factor.', 'tn-display-density'); ?>
    </p>
    <button type="submit" class="button button-secondary" id="tdd_save_current"
        name="tdd_settings[save_current]" value="1" disabled>
        <?php esc_html_e('Save calibration for this display', 'tn-display-density'); ?>
    </button>
    <?php
}

function tdd_render_saved_displays() {
    $settings = tdd_get_settings();
    $profiles = is_array($settings['profiles']) ? $settings['profiles'] : array();

    if (!$profiles) {
        echo '<p>' . esc_html__('No display scenarios saved yet.', 'tn-display-density') . '</p>';
        return;
    }
    ?>
    <table class="widefat striped">
        <thead><tr>
            <th scope="col"><?php esc_html_e('Observed scenario', 'tn-display-density'); ?></th>
            <th scope="col"><?php esc_html_e('Calibration', 'tn-display-density'); ?></th>
            <th scope="col"><?php esc_html_e('Remove', 'tn-display-density'); ?></th>
        </tr></thead>
        <tbody>
        <?php foreach (array_values($profiles) as $index => $profile) :
            $field = 'tdd_settings[profiles][' . $index . ']';
            $description = sprintf(
                '%s · DPR %s · %s × %s',
                $profile['platform'] === 'mac' ? 'Mac' : 'Windows',
                number_format((float) $profile['dpr'], 2, '.', ''),
                $profile['width'],
                $profile['height']
            );
            ?>
            <tr>
                <th scope="row">
                    <?php if (!empty($profile['label'])) : ?>
                        <strong><?php echo esc_html($profile['label']); ?></strong><br>
                    <?php endif; ?>
                    <?php echo esc_html($description); ?>
                    <input type="hidden" name="<?php echo esc_attr($field . '[label]'); ?>"
                        value="<?php echo esc_attr(isset($profile['label']) ? $profile['label'] : ''); ?>">
                </th>
                <td>
                    <label class="screen-reader-text" for="tdd_profile_<?php echo esc_attr((string) $index); ?>">
                        <?php echo esc_html(sprintf(__('Calibration for %s', 'tn-display-density'), $description)); ?>
                    </label>
                    <input type="number" id="tdd_profile_<?php echo esc_attr((string) $index); ?>"
                        name="<?php echo esc_attr($field . '[value]'); ?>"
                        value="<?php echo esc_attr((string) $profile['value']); ?>"
                        min="0" step="any">
                    <?php foreach (array('platform', 'dpr', 'width', 'height') as $key) : ?>
                        <input type="hidden" name="<?php echo esc_attr($field . '[' . $key . ']'); ?>"
                            value="<?php echo esc_attr((string) $profile[$key]); ?>">
                    <?php endforeach; ?>
                </td>
                <td>
                    <label>
                        <input type="checkbox" name="<?php echo esc_attr($field . '[remove]'); ?>" value="1">
                        <?php esc_html_e('Remove', 'tn-display-density'); ?>
                    </label>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <p class="description"><?php esc_html_e('Save settings to apply edits or removals.', 'tn-display-density'); ?></p>
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

function tdd_enqueue_admin_script($hook) {
    if ($hook !== 'settings_page_tn-display-density') {
        return;
    }

    wp_enqueue_script(
        'tdd-display-density',
        plugins_url('display-density.js', __FILE__),
        array(),
        TDD_VERSION,
        true
    );
    wp_add_inline_script(
        'tdd-display-density',
        'window.TDDSettings = ' . wp_json_encode(tdd_get_settings()) . '; window.TDDAdmin = true;',
        'before'
    );
}
add_action('admin_enqueue_scripts', 'tdd_enqueue_admin_script');
