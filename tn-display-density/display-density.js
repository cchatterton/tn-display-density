(function () {
    'use strict';

    const settings = window.TDDSettings || {};
    const userAgent = navigator.userAgent || '';
    const isMobileOrTablet = /Android|iPad|iPhone|iPod|Mobile|Tablet/i.test(userAgent) ||
        (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1);
    const platform = isMobileOrTablet ? null :
        /Windows/i.test(userAgent) ? 'windows' :
        /Macintosh|Mac OS X/i.test(userAgent) ? 'mac' : null;
    let resolutionQuery = null;

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

    function validCalibration(value) {
        return Number.isFinite(value) && value >= 0;
    }

    function fallbackCalibration(display) {
        if (display.platform === 'windows') {
            return Number(settings.windows_compensation);
        }
        return display.dpr < 1.5 ? Number(settings.mac_compensation) : 0;
    }

    function applyDisplayDensity() {
        const display = environment();
        let zoom = 1;

        if (display) {
            const profile = matchingProfile(display);
            const calibration = profile ? Number(profile.value) : fallbackCalibration(display);

            if (validCalibration(calibration)) {
                if (display.platform === 'windows') {
                    const relativeScale = display.rawDpr / 1;
                    const compensation = 1 / relativeScale;
                    zoom = 1 + (compensation - 1) * calibration;
                } else {
                    zoom = calibration === 0 ? 1 : calibration;
                }
            }
        }

        if (!Number.isFinite(zoom) || zoom <= 0) {
            zoom = 1;
        }

        if (document.documentElement.style.zoom !== String(zoom)) {
            document.documentElement.style.zoom = String(zoom);
        }
    }

    function setupAdmin() {
        const summary = document.getElementById('tdd_current_summary');
        const value = document.getElementById('tdd_current_value');
        const label = document.getElementById('tdd_current_label');
        const button = document.getElementById('tdd_save_current');
        let currentKey = null;

        if (!summary || !value || !label || !button) {
            return function () {};
        }

        return function updateAdminDisplay() {
            const display = environment();

            if (!display) {
                summary.textContent = 'This display could not be identified. Calibration is unavailable here.';
                value.disabled = true;
                label.disabled = true;
                button.disabled = true;
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
                const calibration = profile ? Number(profile.value) : fallbackCalibration(display);
                value.value = validCalibration(calibration) ? String(calibration) : '0';
                label.value = profile && profile.label ? profile.label : '';
                document.getElementById('tdd_current_platform').value = display.platform;
                document.getElementById('tdd_current_dpr').value = display.dpr.toFixed(2);
                document.getElementById('tdd_current_width').value = String(display.width);
                document.getElementById('tdd_current_height').value = String(display.height);
                currentKey = key;
            }

            value.disabled = false;
            label.disabled = false;
            button.disabled = false;
        };
    }

    if (isMobileOrTablet && !window.TDDAdmin) {
        return;
    }

    const update = window.TDDAdmin ? setupAdmin() : applyDisplayDensity;

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
    window.addEventListener('resize', onEnvironmentChange);
    window.addEventListener('focus', onEnvironmentChange);
    document.addEventListener('visibilitychange', onEnvironmentChange);
})();
