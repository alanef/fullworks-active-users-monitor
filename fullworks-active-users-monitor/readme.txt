=== Fullworks Active Users Monitor ===
Contributors: fullworks,alanfuller
Donate link: https://ko-fi.com/wpalan
Tags: users, monitoring, active users, online users, admin tools
Requires at least: 6.2
Tested up to: 7.1
Stable tag: 1.2.0-alpha.2
Requires PHP: 7.4
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html
Type: free

Real-time monitoring of logged-in WordPress users with visual indicators, filtering, and comprehensive admin tools.

== Description ==

**Fullworks Active Users Monitor** provides administrators with real-time visibility of logged-in users on your WordPress site. Using WordPress's native session tokens system, this plugin accurately tracks user login states and provides powerful monitoring tools.

= Key Features =

* **Real-Time Tracking** - Uses WordPress session tokens for accurate online/offline status
* **Comprehensive Audit Trail** - Track all login/logout events with detailed logging
* **Admin Bar Widget** - Quick overview of online users with role breakdown
* **Enhanced Users List** - Visual indicators, status columns, and filtering options
* **Dashboard Widget** - At-a-glance view of active users on your dashboard
* **Audit Log Export** - Export user activity to CSV, JSON, or Excel formats
* **Auto-Refresh** - Configurable automatic updates without page reload
* **Role-Based Display** - Color-coded indicators for different user roles
* **WP-CLI Support** - Command line tools for monitoring and management
* **Performance Optimized** - Smart caching and efficient queries
* **Fully Translatable** - Ready for localization

= Visual Indicators =

The plugin provides clear visual feedback for online users:

* Green status dots for online users
* Gold/orange borders for administrators
* Color-coded role indicators
* Animated pulse effects (optional)
* "ONLINE" badges in user lists
* Last seen timestamps for offline users

= Perfect For =

* Membership sites monitoring user activity
* Educational platforms tracking student engagement
* Multi-author blogs coordinating content creation
* Support teams managing customer interactions
* Any site requiring user activity insights

= Developer Friendly =

* Clean, well-documented code
* Action and filter hooks for customization
* WP-CLI commands for automation
* Follows WordPress coding standards
* Compatible with multisite installations

== Installation ==

= Automatic Installation =

1. Go to Plugins > Add New in your WordPress admin
2. Search for "Fullworks Active Users Monitor"
3. Click "Install Now" and then "Activate"
4. Configure settings under Settings > Active Users Monitor

= Manual Installation =

1. Download the plugin ZIP file
2. Upload to `/wp-content/plugins/` directory
3. Extract the ZIP file
4. Activate through the Plugins menu in WordPress
5. Configure under Settings > Active Users Monitor

= After Activation =

1. Visit Settings > Active Users Monitor to configure options
2. Check the admin bar for the online users counter
3. View the Users page to see enhanced status indicators
4. Optional: Add the dashboard widget for quick monitoring

== Frequently Asked Questions ==

= How does the plugin determine if a user is online? =

The plugin uses WordPress's built-in WP_Session_Tokens class to check for active session tokens. This ensures accurate detection regardless of the authentication method used (standard login, SSO, 2FA, etc.).

A user counts as online from the moment they log in until they log out or their session expires. WordPress sessions last 2 days, or 14 days with "Remember Me", so a user who closes the browser without logging out stays online until then.

= Does this plugin create custom database tables? =

Yes, one table (wp_fwaum_audit_log) for the audit trail, created on activation. Nothing is written to it unless you enable the audit trail. The online-status monitoring uses WordPress's existing session data and needs no custom tables. The table is removed when the plugin is deleted.

= Can I customize which roles can see online status? =

Yes. In the plugin settings, you can choose which user roles can see online status in the admin bar and dashboard widget. Administrators always can. The status column on the Users page additionally needs the ability to list users. Developers can override the check with the `fwaum_current_user_can_view` filter.

= How often does the plugin update the online status? =

The refresh interval is configurable from 15 to 300 seconds. The default is 30 seconds. You can adjust this in Settings > Active Users Monitor.

= My site is behind a proxy or CDN and the audit log shows the proxy's IP address =

By default only the connecting address (REMOTE_ADDR) is trusted, because headers such as X-Forwarded-For can be forged by anyone. If your site sits behind a proxy you control, list the header it sets with the `fwaum_client_ip_headers` filter, for example `array( 'HTTP_CF_CONNECTING_IP', 'REMOTE_ADDR' )` behind Cloudflare.

= Is this plugin compatible with caching plugins? =

Yes. The plugin uses AJAX for real-time updates, which works independently of page caching. The plugin also implements its own transient caching for optimal performance.

= Can I use this with multisite? =

Yes. On multisite each site shows its own members who are online, and each site keeps its own audit trail.

= Does it work with custom user roles? =

Yes. The plugin automatically detects and supports all custom user roles in addition to WordPress default roles.

= How can I style the online indicators differently? =

Every element has its own CSS class, so you can override the styles in your theme's CSS.

= Is WP-CLI support included? =

Yes. The plugin includes comprehensive WP-CLI support with commands for monitoring, automation, and scripting. See the WP-CLI Commands section below for details.

= Will this slow down my site? =

No. The plugin is optimized for performance with smart caching, efficient queries, and optional features you can disable if needed.

== Screenshots ==

1. Users list page showing highlighted online users with visual indicators
2. Settings page giving control over who sees online status
3. Dashboard widget displaying online users summary
4. Admin bar dropdown showing online users count and role breakdown

== Changelog ==

= 1.1.0 =
* Added comprehensive audit trail functionality to track user login/logout events
* New audit log table under Users menu with advanced filtering and search
* Export audit logs to CSV, JSON, or Excel formats
* Track login methods (standard, social, two-factor, API, etc.)
* Monitor session durations and failed login attempts
* Configurable retention periods and privacy settings
* Database migration support for existing user data
* Updated minimum WordPress version to 6.2 for enhanced security features
* Fully compliant with WordPress Coding Standards

= 1.0.1 =
* Fixed contributor name and donation link
* Added WordPress Playground blueprint for easy preview
* Added plugin assets to readme
* Minor documentation improvements

= 1.0.0 =
* Initial release
* Real-time user monitoring using session tokens
* Admin bar counter with role breakdown
* Enhanced users list with visual indicators
* Dashboard widget for quick overview
* Configurable auto-refresh intervals
* WP-CLI command support
* Comprehensive settings page
* Full internationalization support

== Upgrade Notice ==

= 1.1.0 =
Major update: Adds comprehensive audit trail functionality to track all user login/logout events with export capabilities. Requires WordPress 6.2 or higher.

= 1.0.0 =
Initial release of Fullworks Active Users Monitor. Install to start monitoring your logged-in users in real-time.

== WP-CLI Commands ==

The plugin provides powerful WP-CLI commands for monitoring and automation:

= Basic Commands =

* `wp active-users list` - List all online users
* `wp active-users stats` - Display online user statistics
* `wp active-users check <user>` - Check if a specific user is online
* `wp active-users monitor` - Real-time monitoring in terminal
* `wp active-users clear-cache` - Clear the online users cache

= Automation Commands =

**Check if any users are online (for scripting):**

`wp active-users any [--quiet] [--count] [--json]`

* `--quiet` - Returns exit code only (0 = users online, 1 = no users online)
* `--count` - Returns just the number of online users
* `--json` - Returns detailed JSON output

**Wait until no users are online:**

`wp active-users wait-clear [--timeout=<seconds>] [--check-interval=<seconds>] [--quiet]`

* `--timeout` - Maximum time to wait (default: 300 seconds)
* `--check-interval` - How often to check (default: 30 seconds)
* `--quiet` - Suppress progress messages

= Example Automation Scripts =

**Safe upgrade script:**
```bash
#!/bin/bash
# Only upgrade when no users are online
if wp active-users any --quiet; then
    echo "Users are online, postponing upgrade"
else
    echo "No users online, safe to upgrade"
    wp core update
    wp plugin update --all
fi
```

**Maintenance with user wait:**
```bash
# Wait for users to go offline, then perform maintenance
wp active-users wait-clear --timeout=600 && {
    wp maintenance-mode activate
    wp db optimize
    wp cache flush
    wp maintenance-mode deactivate
}
```

**Monitoring script:**
```bash
# Get online user count for monitoring dashboard
ONLINE_COUNT=$(wp active-users any --count)
if [ "$ONLINE_COUNT" -gt "100" ]; then
    # Send alert about high user activity
    echo "High activity: $ONLINE_COUNT users online"
fi
```

These commands make it easy to create maintenance scripts that respect user activity, ensuring updates and maintenance tasks only run when appropriate.

== Privacy Policy ==

Online status is read from the session data WordPress already keeps. The plugin also stores each user's most recent login time with their account.

When you enable the audit trail, the plugin records logins, logouts, failed login attempts and expired sessions. Each record holds the username and display name, the IP address and browser user agent of the request, the login method and the time. Failed login attempts are recorded with the username that was tried, even when no such account exists.

Records are deleted after the retention period you choose, and IP addresses are anonymized after the period you choose. The plugin adds suggested text to Settings > Privacy and supports WordPress's personal data export and erasure tools.

No data is sent to external services.

== Credits ==

Developed by [Fullworks](https://fullworks.net/)

Icons and visual elements use WordPress core styles for consistency.
