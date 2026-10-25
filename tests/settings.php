<?php

define('ABSPATH', '/test/');
$GLOBALS['saved_tdd_settings'] = array();
$GLOBALS['tdd_errors'] = array();

function plugin_basename($file) { return 'tn-display-density/tn-display-density.php'; }
function sanitize_text_field($text) { return trim(strip_tags($text)); }
function add_filter() {}
function add_action() {}
function get_option($name, $default = null) { return $GLOBALS['saved_tdd_settings'] ?: $default; }
function wp_parse_args($saved, $defaults) { return array_merge($defaults, $saved); }
function __($text) { return $text; }
function add_settings_error($setting, $code, $message) { $GLOBALS['tdd_errors'][] = $message; }

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
check($defaults['profiles'] === array(), 'Profiles default');
check(isset($GLOBALS['tnuc_clients']['tn-display-density/tn-display-density.php']), 'Controller client registration');

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

$invalid = tdd_sanitize_settings(array('windows_compensation' => 'bad', 'mac_compensation' => '0.4',
    'profiles' => array_values($saved['profiles'])));
check($invalid['windows_compensation'] === 0.85, 'Invalid Windows value preserved');
check((float) $invalid['mac_compensation'] === 0.0, 'Invalid Mac value preserved');
check(count($GLOBALS['tdd_errors']) >= 2, 'Validation errors reported');

echo "Settings and controller registration checks passed\n";
