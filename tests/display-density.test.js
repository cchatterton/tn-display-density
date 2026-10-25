const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');

const script = fs.readFileSync('tn-display-density/display-density.js', 'utf8');

function browser(userAgent, dpr, width, height, settings, admin = false, options = {}) {
    const listeners = {};
    const elements = {};
    const opened = [];
    const appended = [];
    for (const id of ['tdd_current_summary', 'tdd_current_value',
        'tdd_save_current', 'tdd_preview_current', 'tdd_preview_status',
        'tdd_current_platform', 'tdd_current_dpr', 'tdd_current_width', 'tdd_current_height']) {
        elements[id] = {value: '', textContent: '', disabled: true, max: '', listeners: {},
            addEventListener(name, callback) {this.listeners[name] = callback;}};
    }
    const sliders = [];
    if (admin) {
        for (const id of ['tdd_current_value', 'tdd_windows_compensation', 'tdd_mac_compensation']) {
            const range = {value: '', max: '2', disabled: true, listeners: {}, addEventListener(name, callback) {this.listeners[name] = callback;}};
            const value = elements[id] || {value: '0', disabled: false, listeners: {}, addEventListener(name, callback) {this.listeners[name] = callback;}};
            value.listeners = {};
            value.addEventListener = function (name, callback) {this.listeners[name] = callback;};
            const maximum = {textContent: '2'};
            const slider = {querySelector: selector => selector === '[data-tdd-range]' ? range : selector === '[data-tdd-value]' ? value : maximum};
            value.closest = () => slider;
            elements[id] = value;
            elements[id + '_range'] = range;
            sliders.push(slider);
        }
    }
    const root = {style: {zoom: ''}};
    const document = {
        documentElement: root,
        readyState: 'complete',
        body: {appendChild: child => appended.push(child)},
        createElement: () => ({style: {}, appendChild(child) {this.child = child;}}),
        getElementById: id => elements[id],
        querySelectorAll: () => sliders,
        addEventListener: (name, callback) => {listeners[name] = callback;}
    };
    const window = {
        TDDSettings: settings,
        TDDAdmin: admin,
        TDDPreviewAllowed: options.previewAllowed || false,
        TDDPreviewUrl: 'https://example.test/',
        location: {href: 'https://example.test/' + (options.query || ''), search: options.query || ''},
        open: (url, target, features) => opened.push({url, target, features}),
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
    vm.runInNewContext(script, {window, document, navigator, Number, URL, URLSearchParams});
    return {window, root, elements, listeners, sliders, opened, appended};
}

const windows = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)';
const mac = 'Mozilla/5.0 (Macintosh; Intel Mac OS X)';
const defaults = {windows_compensation: 0.85, mac_compensation: 0, profiles: {}};

const win = browser(windows, 1.25, 1920, 1080, defaults);
assert.ok(Math.abs(Number(win.root.style.zoom) - 0.83) < 1e-10);
win.window.devicePixelRatio = 2;
win.listeners.resolution();
assert.equal(win.root.style.zoom, '0.575');

const winProfile = browser(windows, 1.25, 1920, 1080, {
    ...defaults,
    profiles: {'windows|1.25|1920x1080': {value: 0}}
});
assert.equal(winProfile.root.style.zoom, '1');

const strongerWindows = browser(windows, 1.25, 1920, 1080, {
    ...defaults,
    windows_compensation: 1.1,
    profiles: {}
});
assert.ok(Math.abs(Number(strongerWindows.root.style.zoom) - 0.78) < 1e-10);

const strongerProfile = browser(windows, 1.25, 1920, 1080, {
    ...defaults,
    profiles: {'windows|1.25|1920x1080': {value: 1.1}}
}, true);
assert.equal(strongerProfile.elements.tdd_current_value.value, '1.1');
assert.equal(strongerProfile.elements.tdd_current_value.max, '');

const unboundedWindows = browser(windows, 1.25, 1920, 1080, {
    ...defaults, windows_compensation: 2.1, profiles: {}
});
assert.ok(Math.abs(Number(unboundedWindows.root.style.zoom) - 0.58) < 1e-10);
assert.equal(browser(windows, 2, 1920, 1080, {
    ...defaults, windows_compensation: 3, profiles: {}
}).root.style.zoom, '1');

const retina = browser(mac, 2, 1440, 900, defaults);
assert.equal(retina.root.style.zoom, '1');
assert.equal(browser(mac, 1, 1920, 1080, {
    ...defaults, mac_compensation: 0.5
}).root.style.zoom, '0.5');

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
assert.equal(admin.elements.tdd_current_value_range.value, '0.9');
admin.elements.tdd_current_value_range.value = '1.1';
admin.elements.tdd_current_value_range.listeners.input();
assert.equal(admin.elements.tdd_current_value.value, '1.1');
admin.elements.tdd_current_value.value = '2.5';
admin.elements.tdd_current_value.listeners.input();
assert.equal(admin.elements.tdd_current_value_range.max, '3');
assert.equal(admin.elements.tdd_current_value_range.value, '2.5');
admin.elements.tdd_current_value.value = '0.8';
admin.elements.tdd_preview_current.listeners.click();
assert.equal(admin.opened[0].url, 'https://example.test/?tn_display_density_preview=0.8');
assert.equal(admin.opened[0].features, 'noopener');
assert.match(admin.elements.tdd_preview_status.textContent, /not saved/);
assert.equal(admin.elements.tdd_save_current.disabled, false);
assert.equal(admin.elements.tdd_preview_current.disabled, false);
admin.window.screen.width = 1920;
admin.window.screen.height = 1080;
admin.window.devicePixelRatio = 1;
admin.listeners.focus();
assert.equal(admin.elements.tdd_current_value.value, '0.85');
assert.equal(admin.elements.tdd_current_dpr.value, '1.00');

const preview = browser(mac, 2, 1440, 900, macSettings, false,
    {previewAllowed: true, query: '?tn_display_density_preview=0.7'});
assert.equal(preview.root.style.zoom, '0.7');
assert.equal(preview.appended.length, 1);
assert.equal(preview.appended[0].child.href, 'https://example.test/');
assert.equal(browser(mac, 2, 1440, 900, macSettings, false,
    {query: '?tn_display_density_preview=0.7'}).root.style.zoom, '0.9');

const allAdmin = browser(mac, 2, 1440, 900, {...macSettings, scope: 'all'}, true);
assert.equal(allAdmin.root.style.zoom, '0.9');

console.log('Display scenario checks passed');
