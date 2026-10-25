=== TN Display Density ===
Contributors:
Tags: display, zoom, windows, macos
Requires at least: 7.0
Tested up to:
Stable tag: 0.4.1
Requires PHP: 7.4
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Compensate for desktop display scaling with Windows, Mac and display-scenario calibration controls.

== Description ==

TN Display Density uses browser zoom to compensate for desktop display scaling on Windows and macOS. Windows uses inverse device-pixel-ratio compensation with a default calibration of 0.85. macOS stays at native zoom by default; an administrator can set a direct zoom factor for standard-density displays. Retina-range displays remain native unless a matching scenario has been calibrated. Mobile and tablet devices are left unchanged.

Administrators can save separate calibrations for the browser-observed Windows or Mac environment, device pixel ratio and display dimensions. Switch a macOS display scaling choice or move to another monitor, then open Appearance > Display Density and save the current scenario. The same process works for Windows display scaling choices. The browser cannot read the exact operating-system display setting name.

Sliders move in 0.1 steps. Exact values can still be entered in the paired number fields. Administrators can preview an unsaved current-display value on the site homepage in a new tab, then exit the preview without saving. Scaling applies to frontend pages by default, with an option to include WordPress admin screens.

Browser data cannot reliably identify operating-system display scaling on every monitor. These settings provide a practical approximation.

== Installation ==

1. Upload the TN Display Density ZIP in Plugins > Add New > Upload Plugin and activate it.
2. Open Appearance > Display Density to adjust the Windows and Mac values if needed.

== Changelog ==

= 0.4.1 =
* Use compact full-width rows for saved calibrations and remove the optional scenario name field.

= 0.4.0 =
* Add 0.1-step sliders, temporary current-display preview, and frontend/admin scope control.

= 0.3.0 =
* Move calibration controls to Appearance, add a branded admin header and slider controls.

= 0.2.1 =
* Remove arbitrary calibration limits while rejecting invalid values and non-positive calculated zoom.

= 0.2.0 =
* Add per-display scenario calibration controls for Windows and macOS.

= 0.1.2 =
* Apply the optional Mac factor only when device pixel ratio drops below Retina range.

= 0.1.1 =
* Clarify the plugin's display-scaling purpose in its metadata and settings screen.

= 0.1.0 =
* Initial release with Windows and macOS calibration settings.

== External services ==

The display calibration itself sends no data to an external service. The optional TN Update Controller integration opens GitHub only after an administrator explicitly chooses to install the controller. That installation requests the public TN Update Controller release ZIP from GitHub; it does not send site visitor data. See GitHub's terms at https://docs.github.com/en/site-policy/github-terms/github-terms-of-service and privacy statement at https://docs.github.com/en/site-policy/privacy-policies/github-general-privacy-statement.
