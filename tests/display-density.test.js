const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');

const script = fs.readFileSync('tn-display-density/display-density.js', 'utf8');

function browser(userAgent, dpr, width, height, settings, admin = false, options = {}) {
    const listeners = {};
    const elements = {};
    const opened = [];
    const appended = [];
    const adminBarItem = {textContent: ''};
    for (const id of ['tdd_current_summary', 'tdd_current_value',
        'tdd_save_current', 'tdd_preview_frontend', 'tdd_preview_admin', 'tdd_preview_status',
        'tdd_current_platform', 'tdd_current_dpr', 'tdd_current_width', 'tdd_current_height',
        'tdd_current_frontend', 'tdd_current_admin']) {
        elements[id] = {value: '', textContent: '', disabled: true, hidden: false, checked: false, max: '', listeners: {},
            addEventListener(name, callback) {this.listeners[name] = callback;}};
    }
    for (const area of ['frontend', 'admin']) {
        elements['tdd_current_' + area].value = area;
    }
    const sliders = [];
    if (admin) {
        for (const id of ['tdd_current_value', 'tdd_windows_frontend_compensation',
            'tdd_windows_admin_compensation', 'tdd_mac_frontend_compensation', 'tdd_mac_admin_compensation']) {
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
        readyState: options.readyState || 'complete',
        body: {appendChild: child => appended.push(child)},
        createElement: () => ({style: {}, appendChild(child) {this.child = child;}}),
        getElementById: id => elements[id],
        querySelector: () => options.adminBar && options.barReady !== false ? adminBarItem : null,
        querySelectorAll: () => sliders,
        addEventListener: (name, callback) => {listeners[name] = callback;}
    };
    const window = {
        TDDSettings: settings,
        TDDAdmin: admin,
        TDDIsAdmin: admin || !!options.isAdmin,
        TDDPreviewAllowed: options.previewAllowed || false,
        TDDPreviewUrl: 'https://example.test/',
        TDDAdminPreviewUrl: 'https://example.test/wp-admin/index.php',
        location: {href: 'https://example.test/' + (options.isAdmin ? 'wp-admin/' : '') + (options.query || ''), search: options.query || ''},
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
    return {window, root, elements, listeners, sliders, opened, appended, adminBarItem};
}

const windows = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)';
const mac = 'Mozilla/5.0 (Macintosh; Intel Mac OS X)';
const defaults = {windows_frontend_compensation: 0.85, windows_admin_compensation: 0,
    mac_frontend_compensation: 0, mac_admin_compensation: 0, profiles: {}};

const win = browser(windows, 1.25, 1920, 1080, defaults, false, {adminBar: true});
assert.ok(Math.abs(Number(win.root.style.zoom) - 0.83) < 1e-10);
assert.equal(win.adminBarItem.textContent, 'DDS: 0.83');
win.window.devicePixelRatio = 2;
win.listeners.resolution();
assert.equal(win.root.style.zoom, '0.575');
assert.equal(win.adminBarItem.textContent, 'DDS: 0.575');

const delayedBarOptions = {adminBar: true, barReady: false, readyState: 'loading'};
const delayedBar = browser(windows, 1.25, 1920, 1080, defaults, false, delayedBarOptions);
assert.equal(delayedBar.adminBarItem.textContent, '');
delayedBarOptions.barReady = true;
delayedBar.listeners.DOMContentLoaded();
assert.equal(delayedBar.adminBarItem.textContent, 'DDS: 0.83');

const winProfile = browser(windows, 1.25, 1920, 1080, {
    ...defaults,
    profiles: {'windows|1.25|1920x1080|frontend': {value: 0, apply_frontend: true, apply_admin: false}}
});
assert.equal(winProfile.root.style.zoom, '1');

const strongerWindows = browser(windows, 1.25, 1920, 1080, {
    ...defaults,
    windows_frontend_compensation: 1.1,
    profiles: {}
});
assert.ok(Math.abs(Number(strongerWindows.root.style.zoom) - 0.78) < 1e-10);

const strongerProfile = browser(windows, 1.25, 1920, 1080, {
    ...defaults,
    profiles: {'windows|1.25|1920x1080|frontend': {value: 1.1, apply_frontend: true, apply_admin: false}}
}, true);
assert.equal(strongerProfile.elements.tdd_current_value.value, '1.1');
assert.equal(strongerProfile.elements.tdd_current_value.max, '');

const unboundedWindows = browser(windows, 1.25, 1920, 1080, {
    ...defaults, windows_frontend_compensation: 2.1, profiles: {}
});
assert.ok(Math.abs(Number(unboundedWindows.root.style.zoom) - 0.58) < 1e-10);
assert.equal(browser(windows, 2, 1920, 1080, {
    ...defaults, windows_frontend_compensation: 3, profiles: {}
}).root.style.zoom, '1');

const retina = browser(mac, 2, 1440, 900, defaults);
assert.equal(retina.root.style.zoom, '1');
assert.equal(browser(mac, 1, 1920, 1080, {
    ...defaults, mac_frontend_compensation: 0.5
}).root.style.zoom, '0.5');

const macSettings = {
    ...defaults,
    mac_frontend_compensation: 0.85,
    profiles: {'mac|2.00|1440x900|frontend': {value: 0.9, label: 'More Space', apply_frontend: true, apply_admin: false},
        'mac|2.00|1440x900|admin': {value: 0.7, label: 'More Space', apply_frontend: false, apply_admin: true}}
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
assert.equal(admin.root.style.zoom, '0.7');
assert.equal(admin.elements.tdd_current_frontend.checked, true);
assert.equal(admin.elements.tdd_preview_frontend.hidden, false);
assert.equal(admin.elements.tdd_preview_admin.hidden, true);
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
admin.elements.tdd_preview_frontend.listeners.click();
assert.equal(admin.opened[0].url, 'https://example.test/?tn_display_density_preview=0_8');
assert.equal(admin.opened[0].features, 'noopener');
assert.match(admin.elements.tdd_preview_status.textContent, /not saved/);
admin.elements.tdd_current_frontend.checked = false;
admin.elements.tdd_current_admin.checked = true;
admin.elements.tdd_current_admin.listeners.change();
assert.equal(admin.elements.tdd_preview_frontend.hidden, true);
assert.equal(admin.elements.tdd_preview_admin.hidden, false);
assert.equal(admin.elements.tdd_current_value.value, '0.7');
admin.elements.tdd_preview_admin.listeners.click();
assert.equal(admin.opened[1].url, 'https://example.test/wp-admin/index.php?tn_display_density_preview=0_7');
admin.elements.tdd_current_admin.checked = false;
admin.elements.tdd_current_frontend.checked = true;
admin.elements.tdd_current_frontend.listeners.change();
assert.equal(admin.elements.tdd_current_value.value, '0.8');
assert.equal(admin.elements.tdd_save_current.disabled, false);
assert.equal(admin.elements.tdd_preview_admin.disabled, false);
admin.window.screen.width = 1920;
admin.window.screen.height = 1080;
admin.window.devicePixelRatio = 1;
admin.listeners.focus();
assert.equal(admin.elements.tdd_current_value.value, '0.85');
assert.equal(admin.elements.tdd_current_dpr.value, '1.00');
assert.equal(admin.elements.tdd_current_frontend.checked, true);
assert.equal(admin.elements.tdd_preview_admin.hidden, true);

const preview = browser(mac, 2, 1440, 900, macSettings, false,
    {previewAllowed: true, query: '?tn_display_density_preview=0_7'});
assert.equal(preview.root.style.zoom, '0.7');
assert.equal(preview.appended.length, 1);
assert.equal(preview.appended[0].child.href, 'https://example.test/');
assert.equal(browser(mac, 2, 1440, 900, macSettings, false,
    {query: '?tn_display_density_preview=0_7'}).root.style.zoom, '0.9');
const adminPreview = browser(mac, 2, 1440, 900, macSettings, false,
    {isAdmin: true, previewAllowed: true, adminBar: true, query: '?tn_display_density_preview=0_7'});
assert.equal(adminPreview.root.style.zoom, '0.7');
assert.equal(adminPreview.adminBarItem.textContent, 'DDS: 0.7');
assert.equal(adminPreview.appended[0].child.href, 'https://example.test/wp-admin/');
assert.equal(browser(mac, 2, 1440, 900, macSettings, false,
    {previewAllowed: true, query: '?tn_display_density_preview=0_855'}).root.style.zoom, '0.855');
const preciseAdmin = browser(mac, 2, 1440, 900, macSettings, true);
preciseAdmin.elements.tdd_current_value.value = '0.855';
preciseAdmin.elements.tdd_preview_frontend.listeners.click();
assert.equal(preciseAdmin.opened[0].url, 'https://example.test/?tn_display_density_preview=0_855');

const adminOnly = browser(windows, 1.25, 1920, 1080, {
    ...defaults, windows_admin_compensation: 0.4,
    profiles: {'windows|1.25|1920x1080|admin': {value: 1.1, apply_frontend: false, apply_admin: true}}
}, false, {isAdmin: true});
assert.ok(Math.abs(Number(adminOnly.root.style.zoom) - 0.78) < 1e-10);
assert.ok(Math.abs(Number(browser(windows, 1.25, 1920, 1080, {
    ...defaults, windows_admin_compensation: 0.4,
    profiles: {'windows|1.25|1920x1080|admin': {value: 1.1, apply_frontend: false, apply_admin: true}}
}).root.style.zoom) - 0.83) < 1e-10);

const frontendOnly = browser(windows, 1.25, 1920, 1080, {
    ...defaults, windows_admin_compensation: 0.4,
    profiles: {'windows|1.25|1920x1080|frontend': {value: 1.1, apply_frontend: true, apply_admin: false}}
}, false, {isAdmin: true});
assert.ok(Math.abs(Number(frontendOnly.root.style.zoom) - 0.92) < 1e-10);
assert.equal(browser(mac, 1, 1920, 1080, {
    ...defaults, mac_admin_compensation: 0.6
}, false, {isAdmin: true}).root.style.zoom, '0.6');

console.log('Display scenario checks passed');
