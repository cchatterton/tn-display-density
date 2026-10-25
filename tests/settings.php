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
function wp_enqueue_script($handle) { $GLOBALS['tdd_enqueued'][] = $handle; }
function wp_enqueue_style($handle) { $GLOBALS['tdd_enqueued'][] = $handle; }
function wp_add_inline_script() {}

require __DIR__ . '/../tn-display-density/tn-display-density.php';

function check($condition, $message) {
    if (!$condition) {
        fwrite(STDERR, $message . "\n");
        exit(1);
    }
}

$defaults = tdd_get_settings();
check($defaults['windows_compensation'] === 0.85, 'Windows default');
check($defaults['mac_compensation'] === 0, 'Mac default');
check($defaults['scope'] === 'frontend', 'Frontend-only default');
check($defaults['profiles'] === array(), 'Profiles default');
check(isset($GLOBALS['tnuc_clients']['tn-display-density/tn-display-density.php']), 'Controller client registration');

ob_start();
tdd_render_slider('windows', 'tdd_settings[windows_compensation]', 1.1, 'Windows compensation', 'Help');
$windows_field = ob_get_clean();
check(strpos($windows_field, 'type="range"') !== false, 'Windows calibration has a slider');
check(strpos($windows_field, 'type="number"') !== false, 'Windows calibration has an exact number input');
check(strpos($windows_field, 'max="2"') !== false, 'Slider has a soft range');
check(strpos($windows_field, 'step="0.1"') !== false, 'Slider uses tenths');
check(strpos($windows_field, 'step="any"') !== false, 'Windows input accepts precise values');

ob_start();
tdd_render_current_display();
$current_field = ob_get_clean();
check(strpos($current_field, 'id="tdd_preview_current"') !== false, 'Current display has a preview control');

$stronger = tdd_sanitize_settings(array('windows_compensation' => '1.1', 'mac_compensation' => '0',
    'save_current' => '1', 'current' => array('platform' => 'windows', 'dpr' => '1.25',
        'width' => '1920', 'height' => '1080', 'value' => '1.1')));
check($stronger['windows_compensation'] === 1.1, 'Windows default accepts 1.1');
check($stronger['profiles']['windows|1.25|1920x1080']['value'] === 1.1, 'Windows scenario accepts 1.1');

$unbounded = tdd_sanitize_settings(array('windows_compensation' => '2.1', 'mac_compensation' => '0.5'));
check($unbounded['windows_compensation'] === 2.1, 'Windows accepts larger finite values');
check($unbounded['mac_compensation'] === 0.5, 'Mac accepts positive factors below the old limit');
$both = tdd_sanitize_settings(array('windows_compensation' => '0.85', 'mac_compensation' => '0', 'scope' => 'all'));
check($both['scope'] === 'all', 'Admin scaling option saved');

$current = array('platform' => 'mac', 'dpr' => '1', 'width' => '1920', 'height' => '1080',
    'value' => '0.85', 'label' => 'Dell <b>monitor</b>');
$saved = tdd_sanitize_settings(array('windows_compensation' => '0.85', 'mac_compensation' => '0',
    'save_current' => '1', 'current' => $current));
check(isset($saved['profiles']['mac|1.00|1920x1080']), 'Current scenario saved');
check($saved['profiles']['mac|1.00|1920x1080']['label'] === 'Dell monitor', 'Scenario label sanitized');

$GLOBALS['saved_tdd_settings'] = $saved;
$removed = tdd_sanitize_settings(array('windows_compensation' => '0.85', 'mac_compensation' => '0',
    'profiles' => array(array_merge($saved['profiles']['mac|1.00|1920x1080'], array('remove' => '1')))));
check($removed['profiles'] === array(), 'Scenario removal');

$invalid = tdd_sanitize_settings(array('windows_compensation' => 'bad', 'mac_compensation' => '-0.4',
    'profiles' => array_values($saved['profiles'])));
check($invalid['windows_compensation'] === 0.85, 'Invalid Windows value preserved');
check((float) $invalid['mac_compensation'] === 0.0, 'Invalid Mac value preserved');
check(count($GLOBALS['tdd_errors']) >= 2, 'Validation errors reported');

$GLOBALS['saved_tdd_settings'] = array();
tdd_enqueue_admin_script('dashboard');
check($GLOBALS['tdd_enqueued'] === array(), 'Default does not enqueue on other admin screens');
tdd_enqueue_admin_script('appearance_page_tn-display-density');
check(in_array('tdd-display-density-admin', $GLOBALS['tdd_enqueued'], true), 'Settings page styling enqueued');
$GLOBALS['tdd_enqueued'] = array();
$GLOBALS['saved_tdd_settings'] = $both;
tdd_enqueue_admin_script('dashboard');
check(in_array('tdd-display-density', $GLOBALS['tdd_enqueued'], true), 'Admin scaling enqueues on other screens');

echo "Settings and controller registration checks passed\n";
