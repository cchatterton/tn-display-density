=== TN Display Density ===
Contributors:
Tags: display, zoom, windows, macos
Requires at least: 7.0
Tested up to:
Stable tag: 0.1.1
Requires PHP: 7.4
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Compensate for desktop display scaling on Windows and macOS with two WordPress settings.

== Description ==

TN Display Density uses browser zoom to compensate for desktop display scaling on Windows and macOS. Windows uses inverse device-pixel-ratio compensation with a default calibration of 0.85. macOS stays at native zoom by default; an administrator can set a direct zoom factor. Mobile and tablet devices are left unchanged.

Browser data cannot reliably identify operating-system display scaling on every monitor. These settings provide a practical approximation.

== Installation ==

1. Upload the TN Display Density ZIP in Plugins > Add New > Upload Plugin and activate it.
2. Open Settings > Display Density to adjust the Windows and Mac values if needed.

== Changelog ==

= 0.1.1 =
* Clarify the plugin's display-scaling purpose in its metadata and settings screen.

= 0.1.0 =
* Initial release with Windows and macOS calibration settings.

== External services ==

The display calibration itself sends no data to an external service. The optional TN Update Controller integration opens GitHub only after an administrator explicitly chooses to install the controller. That installation requests the public TN Update Controller release ZIP from GitHub; it does not send site visitor data. See GitHub's terms at https://docs.github.com/en/site-policy/github-terms/github-terms-of-service and privacy statement at https://docs.github.com/en/site-policy/privacy-policies/github-general-privacy-statement.
