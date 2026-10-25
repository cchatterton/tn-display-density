(function () {
    'use strict';

    const settings = window.TDDSettings || {};
    const isAdmin = !!window.TDDIsAdmin;
    const userAgent = navigator.userAgent || '';
    const isMobileOrTablet = /Android|iPad|iPhone|iPod|Mobile|Tablet/i.test(userAgent) ||
        (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1);
    const platform = isMobileOrTablet ? null :
        /Windows/i.test(userAgent) ? 'windows' :
        /Macintosh|Mac OS X/i.test(userAgent) ? 'mac' : null;
    let resolutionQuery = null;
    const previewParameter = !window.TDDAdmin && window.TDDPreviewAllowed && window.location
        ? new URLSearchParams(window.location.search).get('tn_display_density_preview') : null;
    const previewValue = previewParameter !== null && previewParameter !== ''
        ? Number(previewParameter) : null;

    function environment() {
        const dpr = window.devicePixelRatio;
        const displayWidth = window.screen && window.screen.width;
        const displayHeight = window.screen && window.screen.height;

        if (!platform || !Number.isFinite(dpr) || dpr < 0.5 || dpr > 8 ||
            !Number.isInteger(displayWidth) || displayWidth < 320 || displayWidth > 20000 ||
            !Number.isInteger(displayHeight) || displayHeight < 200 || displayHeight > 20000 ||
            !Number.isFinite(window.innerWidth) || window.innerWidth <= 0 ||
            !Number.isFinite(window.innerHeight) || window.innerHeight <= 0) {
            return null;
        }

        return {
            platform: platform,
            dpr: Number(dpr.toFixed(2)),
            rawDpr: dpr,
            width: displayWidth,
            height: displayHeight,
            viewportWidth: window.innerWidth,
            viewportHeight: window.innerHeight
        };
    }

    function profileKey(display) {
        return display.platform + '|' + display.dpr.toFixed(2) + '|' +
            display.width + 'x' + display.height;
    }

    function matchingProfile(display) {
        return settings.profiles && settings.profiles[profileKey(display)];
    }

    function profileApplies(profile) {
        if (!profile) {
            return false;
        }
        return isAdmin
            ? (profile.apply_admin === undefined ? settings.scope === 'all' : !!profile.apply_admin)
            : (profile.apply_frontend === undefined || !!profile.apply_frontend);
    }

    function validCalibration(value) {
        return Number.isFinite(value) && value >= 0;
    }

    function fallbackCalibration(display, forAdmin = isAdmin) {
        if (display.platform === 'windows') {
            const value = forAdmin
                ? settings.windows_admin_compensation ?? (settings.scope === 'all' ? settings.windows_compensation : 0)
                : settings.windows_frontend_compensation ?? settings.windows_compensation;
            return Number(value);
        }
        const value = forAdmin
            ? settings.mac_admin_compensation ?? (settings.scope === 'all' ? settings.mac_compensation : 0)
            : settings.mac_frontend_compensation ?? settings.mac_compensation;
        return display.dpr < 1.5 ? Number(value) : 0;
    }

    function zoomFor(display, calibration) {
        if (!display || !validCalibration(calibration)) {
            return 1;
        }
        const zoom = display.platform === 'windows'
            ? 1 + (1 / display.rawDpr - 1) * calibration
            : calibration === 0 ? 1 : calibration;
        return Number.isFinite(zoom) && zoom > 0 ? zoom : 1;
    }

    function applyDisplayDensity() {
        const display = environment();
        let zoom = 1;

        if (display) {
            const profile = matchingProfile(display);
            const calibration = validCalibration(previewValue) && previewParameter !== null
                ? previewValue : profileApplies(profile) ? Number(profile.value) : fallbackCalibration(display);
            zoom = zoomFor(display, calibration);
        }

        if (document.documentElement.style.zoom !== String(zoom)) {
            document.documentElement.style.zoom = String(zoom);
        }
        const scalingItem = document.querySelector('#wp-admin-bar-tdd-scaling .ab-item');
        if (scalingItem) {
            scalingItem.textContent = 'DDS: ' + String(Number(zoom.toPrecision(3)));
        }
    }

    function setupAdmin() {
        const sliders = document.querySelectorAll('[data-tdd-slider]');
        sliders.forEach(function (slider) {
            const range = slider.querySelector('[data-tdd-range]');
            const value = slider.querySelector('[data-tdd-value]');
            const maximum = slider.querySelector('[data-tdd-range-max]');

            function syncFromValue() {
                const number = Number(value.value);
                if (!Number.isFinite(number) || number < 0) {
                    return;
                }
                const ceiling = Math.max(2, Math.ceil(number));
                range.max = String(ceiling);
                maximum.textContent = String(ceiling);
                range.value = String(number);
            }

            range.addEventListener('input', function () {
                value.value = range.value;
            });
            value.addEventListener('input', syncFromValue);
            slider.syncFromValue = syncFromValue;
            syncFromValue();
        });

        const summary = document.getElementById('tdd_current_summary');
        const value = document.getElementById('tdd_current_value');
        const currentRange = document.getElementById('tdd_current_value_range');
        const button = document.getElementById('tdd_save_current');
        const previewFrontend = document.getElementById('tdd_preview_frontend');
        const previewAdmin = document.getElementById('tdd_preview_admin');
        const previewStatus = document.getElementById('tdd_preview_status');
        const currentScopes = ['frontend', 'admin', 'both'].map(function (area) {
            return document.getElementById('tdd_current_' + area);
        });
        let currentKey = null;

        if (!summary || !value || !currentRange || !button || !previewFrontend || !previewAdmin ||
            !previewStatus || currentScopes.some(function (scope) { return !scope; })) {
            return function () {};
        }

        function updatePreviewButtons() {
            const selected = currentScopes.find(function (scope) { return scope.checked; });
            const area = selected ? selected.value : 'frontend';
            previewFrontend.hidden = area === 'admin';
            previewAdmin.hidden = area === 'frontend';
        }

        function preview(area) {
            const display = environment();
            const calibration = Number(value.value);
            if (!display || value.value === '' || !validCalibration(calibration) ||
                (display.platform === 'windows' && 1 + (1 / display.rawDpr - 1) * calibration <= 0)) {
                previewStatus.textContent = 'Enter a value that produces a positive zoom to preview it.';
                return;
            }
            const url = new URL(area === 'admin' ? window.TDDAdminPreviewUrl : window.TDDPreviewUrl, window.location.href);
            url.searchParams.set('tn_display_density_preview', String(calibration));
            previewStatus.textContent = 'Opening a temporary ' + (area === 'admin' ? 'admin' : 'frontend') + ' preview. This value is not saved.';
            window.open(url.toString(), '_blank', 'noopener');
        }

        previewFrontend.addEventListener('click', function () { preview('frontend'); });
        previewAdmin.addEventListener('click', function () { preview('admin'); });
        currentScopes.forEach(function (scope) {
            scope.addEventListener('change', updatePreviewButtons);
        });

        return function updateAdminDisplay() {
            const display = environment();

            if (!display) {
                summary.textContent = 'This display could not be identified. Calibration is unavailable here.';
                value.disabled = true;
                currentRange.disabled = true;
                button.disabled = true;
                previewFrontend.disabled = true;
                previewAdmin.disabled = true;
                currentScopes.forEach(function (scope) { scope.disabled = true; });
                currentKey = null;
                return;
            }

            const key = profileKey(display);
            const profile = matchingProfile(display);
            const name = display.platform === 'mac' ? 'Mac' : 'Windows';
            summary.textContent = name + ' · DPR ' + display.dpr.toFixed(2) +
                ' · display ' + display.width + ' × ' + display.height +
                ' · viewport ' + display.viewportWidth + ' × ' + display.viewportHeight +
                (profile ? ' · saved calibration' : ' · using default');

            if (key !== currentKey) {
                const calibration = profile ? Number(profile.value) : fallbackCalibration(display, false);
                value.value = validCalibration(calibration) ? String(calibration) : '0';
                const appliesFrontend = profile ? (profile.apply_frontend === undefined || !!profile.apply_frontend) : true;
                const appliesAdmin = profile ? (profile.apply_admin === undefined ? settings.scope === 'all' : !!profile.apply_admin) : false;
                const selectedArea = appliesFrontend && appliesAdmin ? 'both' : (appliesAdmin ? 'admin' : 'frontend');
                currentScopes.forEach(function (scope) { scope.checked = scope.value === selectedArea; });
                updatePreviewButtons();
                const currentSlider = value.closest('[data-tdd-slider]');
                if (currentSlider && currentSlider.syncFromValue) {
                    currentSlider.syncFromValue();
                }
                document.getElementById('tdd_current_platform').value = display.platform;
                document.getElementById('tdd_current_dpr').value = display.dpr.toFixed(2);
                document.getElementById('tdd_current_width').value = String(display.width);
                document.getElementById('tdd_current_height').value = String(display.height);
                currentKey = key;
            }

            value.disabled = false;
            currentRange.disabled = false;
            button.disabled = false;
            previewFrontend.disabled = false;
            previewAdmin.disabled = false;
            currentScopes.forEach(function (scope) { scope.disabled = false; });
        };
    }

    if (isMobileOrTablet && !window.TDDAdmin) {
        return;
    }

    if (!window.TDDAdmin && previewParameter !== null && validCalibration(previewValue)) {
        const showPreviewNotice = function () {
            const notice = document.createElement('div');
            const exit = document.createElement('a');
            const exitUrl = new URL(window.location.href);
            exitUrl.searchParams.delete('tn_display_density_preview');
            notice.textContent = 'Temporary display calibration preview · ';
            exit.textContent = 'Exit preview';
            exit.href = exitUrl.toString();
            exit.style.cssText = 'color:#fff;text-decoration:underline;font-weight:700';
            notice.appendChild(exit);
            notice.style.cssText = 'position:fixed;z-index:2147483647;right:1rem;bottom:1rem;padding:.7rem 1rem;background:#002840;color:#fff;border-radius:.4rem;font:600 .9rem/1.4 sans-serif;box-shadow:0 .2rem .8rem #0005';
            document.body.appendChild(notice);
        };
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', showPreviewNotice, {once: true});
        } else {
            showPreviewNotice();
        }
    }

    const adminUpdate = window.TDDAdmin ? setupAdmin() : null;
    function update() {
        if (adminUpdate) {
            adminUpdate();
        }
        if (!isMobileOrTablet) {
            applyDisplayDensity();
        }
    }

    function watchResolution() {
        const dpr = window.devicePixelRatio;
        if (!window.matchMedia || !Number.isFinite(dpr) || dpr <= 0) {
            return;
        }

        if (resolutionQuery) {
            if (resolutionQuery.removeEventListener) {
                resolutionQuery.removeEventListener('change', onEnvironmentChange);
            } else if (resolutionQuery.removeListener) {
                resolutionQuery.removeListener(onEnvironmentChange);
            }
        }

        resolutionQuery = window.matchMedia('(resolution: ' + dpr + 'dppx)');
        if (resolutionQuery.addEventListener) {
            resolutionQuery.addEventListener('change', onEnvironmentChange);
        } else if (resolutionQuery.addListener) {
            resolutionQuery.addListener(onEnvironmentChange);
        }
    }

    function onEnvironmentChange() {
        update();
        watchResolution();
    }

    onEnvironmentChange();
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', update, {once: true});
    }
    window.addEventListener('resize', onEnvironmentChange);
    window.addEventListener('focus', onEnvironmentChange);
    document.addEventListener('visibilitychange', onEnvironmentChange);
})();
