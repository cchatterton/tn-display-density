# TN Display Density

Author: Techn
Version: 0.2.1
Branding mode: Author Branded

A small WordPress plugin that uses browser zoom to compensate for desktop display scaling. It uses the Settings API and vanilla JavaScript. Windows defaults to inverse-DPR calibration `0.85`; macOS defaults to native zoom (`0`). A configured Mac default factor applies when effective DPR is below `1.5`; Retina-range displays remain native. Mobile and tablet devices are untouched.

Administrators can save a separate calibration for each browser-observed scenario, identified by operating system, rounded DPR and reported display dimensions. Saved scenarios override the platform defaults. The Settings page reports the current scenario and lets administrators save a value after changing macOS or Windows display scaling, or moving to another monitor. Optional labels such as “More Space” or “Dell monitor” are for reference; the browser cannot read those operating-system setting names.

Windows compensation accepts any finite non-negative value. A value of 1 applies full inverse-DPR compensation; values above 1 make a stronger adjustment. Mac accepts 0 for native zoom or any positive finite factor. If a calculated zoom is not finite or positive, the page falls back to 100%.

The runtime feature uses one PHP file and one JavaScript file. The package also includes the standard TN Update Controller client, `LICENSE`, and `readme.txt`. The plugin stores only one WordPress option, `tdd_settings`.

The controller is optional for display calibration. It provides the standard GitHub update flow and plugin-row actions. Its absence does not affect the feature.

## Build

Run `scripts/build-plugin-zip.sh`. This writes `dist/tn-display-density.zip` and the identical root `tn-display-density.zip`, each containing the `tn-display-density/` folder at the top level.

## Limits

Browser DPR does not reliably reveal physical pixel density or the exact operating-system display choice, especially on Macs with external monitors. Two displays with the same browser-reported dimensions and DPR cannot be distinguished by this plugin. Administrators can calibrate the observable scenarios they use.
