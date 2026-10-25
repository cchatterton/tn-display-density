const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');

const script = fs.readFileSync('tn-display-density/display-density.js', 'utf8');

function browser(userAgent, dpr, width, height, settings, admin = false) {
    const listeners = {};
    const elements = {};
    for (const id of ['tdd_current_summary', 'tdd_current_value', 'tdd_current_label',
        'tdd_save_current', 'tdd_current_platform', 'tdd_current_dpr',
        'tdd_current_width', 'tdd_current_height']) {
        elements[id] = {value: '', textContent: '', disabled: true, max: ''};
    }
    const root = {style: {zoom: ''}};
    const document = {
        documentElement: root,
        getElementById: id => elements[id],
        addEventListener: (name, callback) => {listeners[name] = callback;}
    };
    const window = {
        TDDSettings: settings,
        TDDAdmin: admin,
        devicePixelRatio: dpr,
        screen: {width, height},
        innerWidth: 1200,
        innerHeight: 800,
        addEventListener: (name, callback) => {listeners[name] = callback;},
        matchMedia: () => ({
            addEventListener: (name, callback) => {listeners.resolution = callback;},
            removeEventListener: () => {}
        })
    };
    const navigator = {userAgent, platform: '', maxTouchPoints: 0};
    vm.runInNewContext(script, {window, document, navigator, Number});
    return {window, root, elements, listeners};
}

const windows = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)';
const mac = 'Mozilla/5.0 (Macintosh; Intel Mac OS X)';
const defaults = {windows_compensation: 0.85, mac_compensation: 0, profiles: {}};

const win = browser(windows, 1.25, 1920, 1080, defaults);
assert.ok(Math.abs(Number(win.root.style.zoom) - 0.83) < 1e-10);
win.window.devicePixelRatio = 2;
win.listeners.resolution();
assert.equal(win.root.style.zoom, '0.65');

const winProfile = browser(windows, 1.25, 1920, 1080, {
    ...defaults,
    profiles: {'windows|1.25|1920x1080': {value: 0}}
});
assert.equal(winProfile.root.style.zoom, '1');

const retina = browser(mac, 2, 1440, 900, defaults);
assert.equal(retina.root.style.zoom, '1');

const macSettings = {
    ...defaults,
    mac_compensation: 0.85,
    profiles: {'mac|2.00|1440x900': {value: 0.9, label: 'More Space'}}
};
const moving = browser(mac, 2, 1440, 900, macSettings);
assert.equal(moving.root.style.zoom, '0.9');
moving.window.devicePixelRatio = 1;
moving.window.screen.width = 1920;
moving.window.screen.height = 1080;
moving.listeners.resolution();
assert.equal(moving.root.style.zoom, '0.85');
moving.window.devicePixelRatio = 2;
moving.window.screen.width = 1440;
moving.window.screen.height = 900;
moving.listeners.resize();
assert.equal(moving.root.style.zoom, '0.9');

assert.equal(browser('iPad; CPU OS 16_0 like Mac OS X', 2, 1024, 768, defaults).root.style.zoom, '');
assert.equal(browser('Unknown desktop', 2, 1440, 900, defaults).root.style.zoom, '1');

const admin = browser(mac, 2, 1440, 900, macSettings, true);
assert.equal(admin.root.style.zoom, '');
assert.equal(admin.elements.tdd_current_value.value, '0.9');
assert.equal(admin.elements.tdd_current_label.value, 'More Space');
assert.equal(admin.elements.tdd_save_current.disabled, false);
admin.window.screen.width = 1920;
admin.window.screen.height = 1080;
admin.window.devicePixelRatio = 1;
admin.listeners.focus();
assert.equal(admin.elements.tdd_current_value.value, '0.85');
assert.equal(admin.elements.tdd_current_dpr.value, '1.00');

console.log('Display scenario checks passed');
