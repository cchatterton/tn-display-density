# TN Display Density

Author: Techn  
Version: 0.1.0  
Branding mode: Author Branded  

A small WordPress plugin for desktop display zoom calibration. It uses the Settings API and vanilla JavaScript. Windows defaults to inverse-DPR calibration `0.85`; macOS defaults to native zoom (`0`). Mobile and tablet devices are untouched.

The runtime feature uses one PHP file and one JavaScript file. The package also includes the standard TN Update Controller client, `LICENSE`, and `readme.txt`. The plugin stores only one WordPress option, `tdd_settings`.

The controller is optional for display calibration. It provides the standard GitHub update flow and plugin-row actions. Its absence does not affect the feature.

## Build

Run `scripts/build-plugin-zip.sh`. This writes `dist/tn-display-density.zip` and the identical root `tn-display-density.zip`, each containing the `tn-display-density/` folder at the top level.

## Limits

Browser DPR does not reliably reveal operating-system display scaling, especially on Macs with external monitors. Administrators can adjust the two settings for their environment.
