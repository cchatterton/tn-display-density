# TN Display Density

Author: Techn
Version: 0.5.5
Branding mode: Author Branded

A small WordPress plugin that uses browser zoom to compensate for desktop display scaling. It uses the Settings API and vanilla JavaScript. Windows defaults to inverse-DPR calibration `0.85`; macOS defaults to native zoom (`0`). A configured Mac default factor applies when effective DPR is below `1.5`; Retina-range displays remain native. Mobile and tablet devices are untouched.

Administrators can save a separate calibration for each browser-observed scenario, identified by operating system, rounded DPR and reported display dimensions. Each saved calibration applies to either the frontend or WordPress admin. The same display can have two separate calibrations, one for each area. Each overrides the matching platform default only in its area. The Appearance page reports the current scenario and lets administrators save a value after changing macOS or Windows display scaling, or moving to another monitor. The browser cannot read the operating-system setting names. Existing saved labels continue to display, but new scenarios use their observed display details as the name.

Sliders move in 0.1 increments, while the paired number fields accept precise values. A two-way control chooses Frontend or WordPress admin for each display calibration. The current value can be previewed in the selected area before saving. The saved calibration list gives this choice its own column. Temporary previews open in new tabs with an exit link. Windows and Mac each have separate frontend and WordPress admin defaults. Existing installations retain their prior frontend calibration; installations that used the old global frontend-and-admin option keep those values as two separate calibrations. Otherwise the new admin defaults start at native zoom.

Administrators see a small `DDS: 0.9` style indicator in the WordPress admin bar showing the effective scaling on the current page. It updates when the observed display changes and during temporary previews.

Windows compensation accepts any finite non-negative value. A value of 1 applies full inverse-DPR compensation; values above 1 make a stronger adjustment. Mac accepts 0 for native zoom or any positive finite factor. If a calculated zoom is not finite or positive, the page falls back to 100%.

The runtime feature uses one PHP file and one JavaScript file. Admin styling is scoped in one CSS file. The package also includes the standard TN Update Controller client, `LICENSE`, and `readme.txt`. The plugin stores only one WordPress option, `tdd_settings`.

The controller is optional for display calibration. It provides the standard GitHub update flow and plugin-row actions. Its absence does not affect the feature.

## Build

Run `scripts/build-plugin-zip.sh`. This writes `dist/tn-display-density.zip` and the identical root `tn-display-density.zip`, each containing the `tn-display-density/` folder at the top level.

## Limits

Browser DPR does not reliably reveal physical pixel density or the exact operating-system display choice, especially on Macs with external monitors. Two displays with the same browser-reported dimensions and DPR cannot be distinguished by this plugin. Administrators can calibrate the observable scenarios they use.
