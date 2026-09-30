# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Security

- An expired login cookie no longer crashes the request. The audit logger looked up the current user from inside the hook WordPress fires while it is still working that out, which recursed until PHP gave up, and anyone could trigger it with a made-up expired cookie. Expired sessions are now identified from the cookie itself, only genuine (correctly signed) cookies are logged, and each is logged once rather than on every request.
- Audit log CSV exports neutralise values that start with `=`, `+`, `-` or `@`. Usernames from failed logins and user agents are chosen by whoever makes the request, and could otherwise run as formulas when an administrator opened the export in a spreadsheet.
- The audit log records the connecting address (`REMOTE_ADDR`) instead of trusting `X-Forwarded-For` and similar headers, which any client can forge. Sites behind a proxy they control can trust its header with the new `fwaum_client_ip_headers` filter. Private and LAN addresses are no longer recorded as `0.0.0.0`.
- The users list, dashboard widget and admin bar escape role names and build role links safely.
- The online users AJAX response no longer includes email addresses.

### Added

- Personal data exporter and eraser for the audit log, and suggested privacy policy text under Settings > Privacy.
- `fwaum_current_user_can_view` filter to override who can see online status.

### Fixed

- "Who Can See Online Status" now takes effect: administrators always see online status, other roles only when chosen. Previously the setting was saved but ignored, and anyone who could list users saw it.
- "IP Address Privacy" now anonymizes IP addresses in the audit log after the chosen period. Previously the setting was saved but nothing was ever anonymized.
- "Track Failed Logins" now takes effect. Previously failed logins were always logged.
- Finding online users takes one query instead of one per user, and counts every user instead of stopping at the first 1,000. The admin bar, dashboard and users list refreshes reuse the 30-second cache instead of rescanning every user on each poll from each open admin tab.
- Failed logins with usernames longer than 60 characters are logged; before, the insert failed silently and the attempt went unrecorded.
- Retention cleanup and IP anonymization compare dates in the site's timezone, matching how entries are stored, so they are no longer off by the site's UTC offset.
- The Excel export is valid SpreadsheetML (missing namespace, wrong date format and empty number cells made it fail to open).
- Export filenames are sanitised.
- The daily audit cleanup is scheduled on activation and removed on deactivation instead of being re-checked on every request and left behind.
- Uninstall removes plugin user meta in one query and cleans every site on large networks (it stopped at 100), without flushing the whole object cache.
- The WordPress Playground preview uses the current WordPress and PHP 8.3. It had been pinned to WordPress 5.9, below the plugin's minimum, so the plugin could not activate.
- readme.txt: the privacy section described no data collection although the audit trail stores IPs and user agents; FAQ answers about the database table, role permissions, multisite and styling hooks now match what the plugin does. Tested up to WordPress 7.1.

### Removed

- The unused Fullworks Free Plugin Library dependency. It was never included in a release, so the settings-page integration listed in the 1.1.0 changelog never ran; that line has been removed from the readme changelog.

## [1.1.0]

- Baseline. Earlier history is in `fullworks-active-users-monitor/readme.txt`.
