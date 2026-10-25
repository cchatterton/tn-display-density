<?php

define('ABSPATH', '/test/');
$GLOBALS['saved_tdd_settings'] = array();
$GLOBALS['tdd_errors'] = array();
$GLOBALS['tdd_enqueued'] = array();

function plugin_basename($file) { return 'tn-display-density/tn-display-density.php'; }
function sanitize_text_field($text) { return trim(strip_tags($text)); }
function add_filter() {}
function add_action() {}
function get_option($name, $default = null) { return $GLOBALS['saved_tdd_settings'] ?: $default; }
function wp_parse_args($saved, $defaults) { return array_merge($defaults, $saved); }
function __($text) { return $text; }
function add_settings_error($setting, $code, $message) { $GLOBALS['tdd_errors'][] = $message; }
function disabled($condition) { if ($condition) echo 'disabled'; }
function esc_html($text) { return htmlspecialchars($text, ENT_QUOTES); }
function esc_attr($text) { return htmlspecialchars($text, ENT_QUOTES); }
function esc_html_e($text) { echo htmlspecialchars($text, ENT_QUOTES); }
function esc_attr__($text) { return esc_attr($text); }
function esc_html__($text) { return esc_html($text); }
function checked($actual, $expected) { if ($actual === $expected) echo 'checked'; }
function current_user_can($capability) { return true; }
function plugins_url($path, $file) { return '/plugins/' . $path; }
function wp_json_encode($value) { return json_encode($value); }
function home_url($path) { return 'https://example.test' . $path; }
function admin_url($path) { return 'https://example.test/wp-admin/' . $path; }
function wp_enqueue_script($handle) { $GLOBALS['tdd_enqueued'][] = $handle; }
function wp_enqueue_style($handle) { $GLOBALS['tdd_enqueued'][] = $handle; }
function wp_add_inline_script() {}
function settings_errors() {}
function settings_fields() {}

require __DIR__ . '/../tn-display-density/tn-display-density.php';

function check($condition, $message) {
    if (!$condition) {
        fwrite(STDERR, $message . "\n");
        exit(1);
    }
}

$defaults = tdd_get_settings();
check($defaults['windows_frontend_compensation'] === 0.85, 'Windows frontend default');
check($defaults['windows_admin_compensation'] === 0, 'Windows admin default is native');
check($defaults['mac_frontend_compensation'] === 0, 'Mac frontend default');
check($defaults['mac_admin_compensation'] === 0, 'Mac admin default');
check($defaults['profiles'] === array(), 'Profiles default');
check(isset($GLOBALS['tnuc_clients']['tn-display-density/tn-display-density.php']), 'Controller client registration');
ob_start();
tdd_render_settings_page();
$settings_markup = ob_get_clean();
foreach (array('windows_frontend_compensation', 'windows_admin_compensation',
    'mac_frontend_compensation', 'mac_admin_compensation') as $key) {
    check(strpos($settings_markup, 'name="tdd_settings[' . $key . ']"') !== false, $key . ' is editable');
}
check(strpos($settings_markup, 'name="tdd_settings[scope]"') === false, 'Global scope control removed');

$GLOBALS['saved_tdd_settings'] = array(
    'windows_compensation' => 1.1, 'mac_compensation' => 0.5, 'scope' => 'all',
    'profiles' => array('mac|1.00|1920x1080' => array('platform' => 'mac', 'dpr' => 1,
        'width' => 1920, 'height' => 1080, 'value' => 0.8, 'label' => 'Old')),
);
$legacy = tdd_get_settings();
check($legacy['windows_frontend_compensation'] === 1.1 && $legacy['windows_admin_compensation'] === 1.1, 'Old all-scope Windows value migrates to both defaults');
check($legacy['mac_frontend_compensation'] === 0.5 && $legacy['mac_admin_compensation'] === 0.5, 'Old all-scope Mac value migrates to both defaults');
check($legacy['profiles']['mac|1.00|1920x1080']['apply_frontend'] && $legacy['profiles']['mac|1.00|1920x1080']['apply_admin'], 'Old all-scope profile migrates to both areas');
$GLOBALS['saved_tdd_settings']['scope'] = 'frontend';
$legacy = tdd_get_settings();
check($legacy['windows_admin_compensation'] === 0 && !$legacy['profiles']['mac|1.00|1920x1080']['apply_admin'], 'Old frontend-only setting keeps admin native');
$GLOBALS['saved_tdd_settings'] = array();

function settings_input($changes = array()) {
    return array_merge(array(
        'windows_frontend_compensation' => '0.85',
        'windows_admin_compensation' => '0',
        'mac_frontend_compensation' => '0',
        'mac_admin_compensation' => '0',
    ), $changes);
}

ob_start();
tdd_render_slider('windows', 'tdd_settings[windows_frontend_compensation]', 1.1, 'Windows compensation', 'Help');
$windows_field = ob_get_clean();
check(strpos($windows_field, 'type="range"') !== false, 'Windows calibration has a slider');
check(strpos($windows_field, 'type="number"') !== false, 'Windows calibration has an exact number input');
check(strpos($windows_field, 'max="2"') !== false, 'Slider has a soft range');
check(strpos($windows_field, 'step="0.1"') !== false, 'Slider uses tenths');
check(strpos($windows_field, 'step="any"') !== false, 'Windows input accepts precise values');

ob_start();
tdd_render_current_display();
$current_field = ob_get_clean();
check(strpos($current_field, 'id="tdd_preview_frontend"') !== false && strpos($current_field, 'id="tdd_preview_admin"') !== false, 'Current display has separate preview controls');
check(strpos($current_field, 'tdd_current_label') === false, 'Current display has no optional scenario name');
check(strpos($current_field, 'id="tdd_current_frontend"') !== false && strpos($current_field, 'id="tdd_current_admin"') !== false && strpos($current_field, 'id="tdd_current_both"') !== false, 'Current display has one three-way scope control');
check(strpos($current_field, 'name="tdd_settings[current][apply_to]"') !== false && strpos($current_field, 'type="checkbox"') === false, 'Current scope uses radios, not checkboxes');

$stronger = tdd_sanitize_settings(settings_input(array('windows_frontend_compensation' => '1.1',
    'save_current' => '1', 'current' => array('platform' => 'windows', 'dpr' => '1.25',
        'width' => '1920', 'height' => '1080', 'value' => '1.1', 'apply_to' => 'frontend'))));
check($stronger['windows_frontend_compensation'] === 1.1, 'Windows frontend default accepts 1.1');
check($stronger['profiles']['windows|1.25|1920x1080']['value'] === 1.1, 'Windows scenario accepts 1.1');

$unbounded = tdd_sanitize_settings(settings_input(array('windows_frontend_compensation' => '2.1', 'mac_admin_compensation' => '0.5')));
check($unbounded['windows_frontend_compensation'] === 2.1, 'Windows accepts larger finite values');
check($unbounded['mac_admin_compensation'] === 0.5, 'Mac admin accepts positive factors');
$both = tdd_sanitize_settings(settings_input(array('mac_admin_compensation' => '0.85')));

$current = array('platform' => 'mac', 'dpr' => '1', 'width' => '1920', 'height' => '1080',
    'value' => '0.85', 'label' => 'Dell <b>monitor</b>', 'apply_to' => 'admin');
$saved = tdd_sanitize_settings(settings_input(array('save_current' => '1', 'current' => $current)));
check(isset($saved['profiles']['mac|1.00|1920x1080']), 'Current scenario saved');
check($saved['profiles']['mac|1.00|1920x1080']['label'] === 'Dell monitor', 'Scenario label sanitized');
check(!$saved['profiles']['mac|1.00|1920x1080']['apply_frontend'] && $saved['profiles']['mac|1.00|1920x1080']['apply_admin'], 'Admin-only scenario saved');

$GLOBALS['saved_tdd_settings'] = $saved;
ob_start();
tdd_render_saved_displays();
$saved_markup = ob_get_clean();
check(strpos($saved_markup, 'class="tdd-saved-row"') !== false, 'Saved display uses a compact row');
check(strpos($saved_markup, 'tdd-slider--compact') !== false, 'Saved slider uses compact layout');
check(strpos($saved_markup, '<table') === false, 'Saved display does not use the redundant table');
check(strpos($saved_markup, 'Slider steps:') === false, 'Saved row omits repeated slider instructions');
check(strpos($saved_markup, 'tdd-slider__help') === false, 'Saved row omits calibration helper text');
check(strpos($saved_markup, 'aria-describedby="tdd_profile_0_help"') === false, 'Saved slider has no missing help reference');
check(strpos($saved_markup, 'id="tdd_profile_0_frontend"') !== false && strpos($saved_markup, 'id="tdd_profile_0_admin"') !== false && strpos($saved_markup, 'id="tdd_profile_0_both"') !== false, 'Saved display has a three-way scope control');
check(strpos($saved_markup, 'name="tdd_settings[profiles][0][apply_to]"') !== false, 'Saved display sends one scope value');
$both_areas = tdd_sanitize_profile(array_merge($current, array('apply_to' => 'both')));
check($both_areas['apply_frontend'] && $both_areas['apply_admin'], 'Both scope applies calibration in both areas');
$changed_area = tdd_sanitize_settings(settings_input(array('profiles' => array(array_merge(
    $saved['profiles']['mac|1.00|1920x1080'], array('apply_to' => 'frontend')
)))));
check($changed_area['profiles']['mac|1.00|1920x1080']['apply_frontend'] &&
    !$changed_area['profiles']['mac|1.00|1920x1080']['apply_admin'], 'Saved control changes an admin-only calibration to frontend-only');
$resaved = tdd_sanitize_settings(settings_input(array(
    'profiles' => array_values($saved['profiles']), 'save_current' => '1',
    'current' => array_diff_key($current, array('label' => true)))));
check($resaved['profiles']['mac|1.00|1920x1080']['label'] === 'Dell monitor', 'Existing scenario label survives save without name field');
$removed = tdd_sanitize_settings(settings_input(array(
    'profiles' => array(array_merge($saved['profiles']['mac|1.00|1920x1080'], array('remove' => '1'))))));
check($removed['profiles'] === array(), 'Scenario removal');

$invalid = tdd_sanitize_settings(settings_input(array('windows_frontend_compensation' => 'bad', 'mac_admin_compensation' => '-0.4',
    'profiles' => array_values($saved['profiles']))));
check($invalid['windows_frontend_compensation'] === 0.85, 'Invalid Windows value preserved');
check((float) $invalid['mac_admin_compensation'] === 0.0, 'Invalid Mac value preserved');
check(count($GLOBALS['tdd_errors']) >= 2, 'Validation errors reported');

$no_scope = tdd_sanitize_settings(settings_input(array('save_current' => '1', 'current' => array_merge($current, array('apply_to' => '')))));
check($no_scope['profiles'] === array(), 'A calibration requires frontend or admin');

$GLOBALS['saved_tdd_settings'] = array();
tdd_enqueue_admin_script('dashboard');
check($GLOBALS['tdd_enqueued'] === array(), 'Default does not enqueue on other admin screens');
$_GET['tn_display_density_preview'] = '0.8';
tdd_enqueue_admin_script('dashboard');
check(in_array('tdd-display-density', $GLOBALS['tdd_enqueued'], true), 'Temporary admin preview enqueues even when admin defaults are native');
unset($_GET['tn_display_density_preview']);
$GLOBALS['tdd_enqueued'] = array();
tdd_enqueue_admin_script('appearance_page_tn-display-density');
check(in_array('tdd-display-density-admin', $GLOBALS['tdd_enqueued'], true), 'Settings page styling enqueued');
$GLOBALS['tdd_enqueued'] = array();
$GLOBALS['saved_tdd_settings'] = $both;
tdd_enqueue_admin_script('dashboard');
check(in_array('tdd-display-density', $GLOBALS['tdd_enqueued'], true), 'Admin scaling enqueues on other screens');

echo "Settings and controller registration checks passed\n";
