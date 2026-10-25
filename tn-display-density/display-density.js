(function () {
    'use strict';

    const root = document.documentElement;
    const settings = window.TDDSettings || {};
    const userAgent = navigator.userAgent || '';
    const isMobileOrTablet = /Android|iPad|iPhone|iPod|Mobile|Tablet/i.test(userAgent) ||
        (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1);
    const platform = isMobileOrTablet ? null :
        /Windows/i.test(userAgent) ? 'windows' :
        /Macintosh|Mac OS X/i.test(userAgent) ? 'mac' : null;

    if (!root || isMobileOrTablet) {
        return;
    }

    let resolutionQuery = null;

    function clamp(value, min, max) {
        return Math.min(max, Math.max(min, value));
    }

    function applyDisplayDensity() {
        const dpr = window.devicePixelRatio;
        const viewportWidth = window.innerWidth;
        const viewportHeight = window.innerHeight;
        let zoom = 1;

        if (platform && Number.isFinite(dpr) && dpr > 0 &&
            Number.isFinite(viewportWidth) && viewportWidth > 0 &&
            Number.isFinite(viewportHeight) && viewportHeight > 0) {
            if (platform === 'windows') {
                const calibration = Number(settings.windows_compensation);

                if (Number.isFinite(calibration) && calibration >= 0 && calibration <= 1) {
                    const relativeScale = dpr / 1;
                    const compensation = 1 / relativeScale;
                    zoom = clamp(1 + (compensation - 1) * calibration, 0.65, 1.25);
                }
            } else {
                const macFactor = Number(settings.mac_compensation);

                if (Number.isFinite(macFactor) && (macFactor === 0 ||
                    (macFactor >= 0.65 && macFactor <= 1.25))) {
                    zoom = macFactor === 0 ? 1 : macFactor;
                }
            }
        }

        if (root.style.zoom !== String(zoom)) {
            root.style.zoom = String(zoom);
        }

        watchResolution(dpr);
    }

    function watchResolution(dpr) {
        if (!window.matchMedia || !Number.isFinite(dpr) || dpr <= 0) {
            return;
        }

        if (resolutionQuery) {
            if (resolutionQuery.removeEventListener) {
                resolutionQuery.removeEventListener('change', applyDisplayDensity);
            } else if (resolutionQuery.removeListener) {
                resolutionQuery.removeListener(applyDisplayDensity);
            }
        }

        resolutionQuery = window.matchMedia('(resolution: ' + dpr + 'dppx)');

        if (resolutionQuery.addEventListener) {
            resolutionQuery.addEventListener('change', applyDisplayDensity);
        } else if (resolutionQuery.addListener) {
            resolutionQuery.addListener(applyDisplayDensity);
        }
    }

    applyDisplayDensity();
    window.addEventListener('resize', applyDisplayDensity);
})();
