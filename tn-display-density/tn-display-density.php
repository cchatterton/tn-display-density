<?php
/**
 * Plugin Name: TN Display Density
 * Description: Compensates for desktop display scaling on Windows and macOS.
 * Version: 0.3.0
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

define('TDD_VERSION', '0.3.0');

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
}
add_action('admin_init', 'tdd_register_settings');

function tdd_render_slider($id, $name, $value, $label, $help, $disabled = false) {
    $slider_max = max(2, ceil((float) $value));
    ?>
    <div class="tdd-slider" data-tdd-slider>
        <div class="tdd-slider__top">
            <label class="tdd-slider__label" for="<?php echo esc_attr($id . '_range'); ?>">
                <?php echo esc_html($label); ?>
            </label>
            <div class="tdd-slider__exact">
                <label for="<?php echo esc_attr($id); ?>"><?php esc_html_e('Value', 'tn-display-density'); ?></label>
                <input type="number" id="<?php echo esc_attr($id); ?>" name="<?php echo esc_attr($name); ?>"
                    value="<?php echo esc_attr((string) $value); ?>" min="0" step="any" required
                    data-tdd-value aria-describedby="<?php echo esc_attr($id . '_help'); ?>"
                    <?php disabled($disabled); ?>>
            </div>
        </div>
        <input type="range" id="<?php echo esc_attr($id . '_range'); ?>"
            min="0" max="<?php echo esc_attr((string) $slider_max); ?>" step="0.01"
            value="<?php echo esc_attr((string) $value); ?>" data-tdd-range
            aria-describedby="<?php echo esc_attr($id . '_help'); ?>" <?php disabled($disabled); ?>>
        <div class="tdd-slider__scale" aria-hidden="true">
            <span>0</span><span data-tdd-range-max><?php echo esc_html((string) $slider_max); ?></span>
        </div>
        <p class="tdd-slider__help" id="<?php echo esc_attr($id . '_help'); ?>">
            <?php echo esc_html($help); ?>
        </p>
    </div>
    <?php
}

function tdd_render_current_display() {
    ?>
    <p class="tdd-environment" id="tdd_current_summary" aria-live="polite">
        <?php esc_html_e('Detecting this display…', 'tn-display-density'); ?>
    </p>
    <?php
    tdd_render_slider(
        'tdd_current_value',
        'tdd_settings[current][value]',
        0,
        __('Calibration for this display', 'tn-display-density'),
        __('Drag the slider or enter an exact value. Windows uses inverse-DPR strength; Mac uses 0 for native or a positive zoom factor.', 'tn-display-density'),
        true
    );
    ?>
    <div class="tdd-name-field">
        <label for="tdd_current_label"><?php esc_html_e('Scenario name (optional)', 'tn-display-density'); ?></label>
        <input type="text" id="tdd_current_label" name="tdd_settings[current][label]"
            class="regular-text" placeholder="<?php echo esc_attr__('For example, Dell monitor or More Space', 'tn-display-density'); ?>" disabled>
    </div>
    <input type="hidden" id="tdd_current_platform" name="tdd_settings[current][platform]">
    <input type="hidden" id="tdd_current_dpr" name="tdd_settings[current][dpr]">
    <input type="hidden" id="tdd_current_width" name="tdd_settings[current][width]">
    <input type="hidden" id="tdd_current_height" name="tdd_settings[current][height]">
    <div class="tdd-panel__footer">
        <p><?php esc_html_e('Switch a display mode or move this window, then save a value for the observed scenario.', 'tn-display-density'); ?></p>
        <button type="submit" class="button tdd-secondary" id="tdd_save_current"
            name="tdd_settings[save_current]" value="1" disabled>
            <?php esc_html_e('Save this display calibration', 'tn-display-density'); ?>
        </button>
    </div>
    <?php
}

function tdd_render_saved_displays() {
    $settings = tdd_get_settings();
    $profiles = is_array($settings['profiles']) ? $settings['profiles'] : array();

    if (!$profiles) {
        echo '<p class="tdd-empty">' . esc_html__('No display scenarios saved yet. Save the current display to add one.', 'tn-display-density') . '</p>';
        return;
    }
    ?>
    <div class="tdd-table-scroll">
        <table class="widefat striped tdd-profiles">
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
                $title = !empty($profile['label']) ? $profile['label'] : $description;
                ?>
                <tr>
                    <th scope="row">
                        <strong><?php echo esc_html($title); ?></strong>
                        <?php if ($title !== $description) : ?>
                            <span class="tdd-profile-detail"><?php echo esc_html($description); ?></span>
                        <?php endif; ?>
                        <input type="hidden" name="<?php echo esc_attr($field . '[label]'); ?>"
                            value="<?php echo esc_attr(isset($profile['label']) ? $profile['label'] : ''); ?>">
                    </th>
                    <td>
                        <?php
                        tdd_render_slider(
                            'tdd_profile_' . $index,
                            $field . '[value]',
                            $profile['value'],
                            sprintf(__('Calibration for %s', 'tn-display-density'), $title),
                            $profile['platform'] === 'mac'
                                ? __('0 uses native zoom; a positive value sets the zoom factor.', 'tn-display-density')
                                : __('0 uses native zoom; 1 applies full inverse-DPR compensation.', 'tn-display-density')
                        );
                        foreach (array('platform', 'dpr', 'width', 'height') as $key) :
                            ?>
                            <input type="hidden" name="<?php echo esc_attr($field . '[' . $key . ']'); ?>"
                                value="<?php echo esc_attr((string) $profile[$key]); ?>">
                        <?php endforeach; ?>
                    </td>
                    <td>
                        <label class="tdd-remove">
                            <input type="checkbox" name="<?php echo esc_attr($field . '[remove]'); ?>" value="1">
                            <?php esc_html_e('Remove', 'tn-display-density'); ?>
                        </label>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <p class="tdd-help"><?php esc_html_e('Save changes to apply edits or removals.', 'tn-display-density'); ?></p>
    <?php
}

function tdd_add_settings_page() {
    add_theme_page(
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
    $settings = tdd_get_settings();
    ?>
    <div class="wrap tdd-admin">
        <h1 class="screen-reader-text"><?php esc_html_e('TN Display Density', 'tn-display-density'); ?></h1>
        <?php settings_errors('tdd_settings'); ?>
        <header class="tdd-hero">
            <span class="tdd-hero__version" aria-label="<?php echo esc_attr(sprintf(__('Version %s', 'tn-display-density'), TDD_VERSION)); ?>">
                <?php echo esc_html('v' . TDD_VERSION); ?>
            </span>
            <div class="tdd-hero__copy">
                <p class="tdd-hero__eyebrow"><?php esc_html_e('DISPLAY. CALIBRATE. CONTINUE.', 'tn-display-density'); ?></p>
                <h2><?php esc_html_e('Display Density', 'tn-display-density'); ?></h2>
                <p class="tdd-hero__lead"><?php esc_html_e('Keep desktop pages comfortable as you move between monitors and display scaling modes.', 'tn-display-density'); ?></p>
            </div>
            <div class="tdd-hero__chips" aria-label="<?php echo esc_attr__('Capabilities', 'tn-display-density'); ?>">
                <span><?php esc_html_e('Windows compensation', 'tn-display-density'); ?></span>
                <span><?php esc_html_e('Mac calibration', 'tn-display-density'); ?></span>
                <span><?php esc_html_e('Display scenarios', 'tn-display-density'); ?></span>
            </div>
        </header>
        <form action="options.php" method="post">
            <?php settings_fields('tdd_settings'); ?>
            <section class="tdd-panel" aria-labelledby="tdd-current-title">
                <div class="tdd-panel__heading">
                    <p class="tdd-panel__eyebrow"><?php esc_html_e('CURRENT DISPLAY', 'tn-display-density'); ?></p>
                    <h2 id="tdd-current-title"><?php esc_html_e('Calibrate this display', 'tn-display-density'); ?></h2>
                    <p><?php esc_html_e('Save a value for the Windows or Mac display you are using now.', 'tn-display-density'); ?></p>
                </div>
                <?php tdd_render_current_display(); ?>
            </section>
            <section class="tdd-panel" aria-labelledby="tdd-defaults-title">
                <div class="tdd-panel__heading">
                    <p class="tdd-panel__eyebrow"><?php esc_html_e('DEFAULTS', 'tn-display-density'); ?></p>
                    <h2 id="tdd-defaults-title"><?php esc_html_e('Platform defaults', 'tn-display-density'); ?></h2>
                    <p><?php esc_html_e('These apply when no saved display scenario matches.', 'tn-display-density'); ?></p>
                </div>
                <div class="tdd-default-grid">
                    <?php
                    tdd_render_slider(
                        'tdd_windows_compensation',
                        'tdd_settings[windows_compensation]',
                        $settings['windows_compensation'],
                        __('Windows compensation', 'tn-display-density'),
                        __('0 uses native zoom; 1 applies full inverse-DPR compensation. Higher values make a stronger adjustment.', 'tn-display-density')
                    );
                    tdd_render_slider(
                        'tdd_mac_compensation',
                        'tdd_settings[mac_compensation]',
                        $settings['mac_compensation'],
                        __('Mac compensation', 'tn-display-density'),
                        __('0 uses native zoom. A positive factor applies to standard-density displays; Retina-range displays stay native unless calibrated separately.', 'tn-display-density')
                    );
                    ?>
                </div>
            </section>
            <section class="tdd-panel" aria-labelledby="tdd-saved-title">
                <div class="tdd-panel__heading">
                    <p class="tdd-panel__eyebrow"><?php esc_html_e('SAVED SCENARIOS', 'tn-display-density'); ?></p>
                    <h2 id="tdd-saved-title"><?php esc_html_e('Display calibrations', 'tn-display-density'); ?></h2>
                    <p><?php esc_html_e('Edit or remove values saved for observed displays.', 'tn-display-density'); ?></p>
                </div>
                <?php tdd_render_saved_displays(); ?>
            </section>
            <div class="tdd-save-bar">
                <p><?php esc_html_e('Changes take effect after you save and reload the site.', 'tn-display-density'); ?></p>
                <button type="submit" class="button button-primary tdd-primary">
                    <?php esc_html_e('Save changes', 'tn-display-density'); ?>
                </button>
            </div>
        </form>
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
    if ($hook !== 'appearance_page_tn-display-density') {
        return;
    }

    wp_enqueue_style(
        'tdd-display-density-admin',
        plugins_url('display-density.css', __FILE__),
        array(),
        TDD_VERSION
    );
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
