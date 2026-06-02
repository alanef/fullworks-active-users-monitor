# True Activity Tracking Feature Specification

This document outlines the plan and technical specifications for implementing a "True Activity" tracking feature in the Fullworks Active Users Monitor plugin.

## 1. Feature Overview

The current plugin determines if a user is "online" based on the existence of a WordPress session. This can be inaccurate, as users may leave sessions open without being actively engaged with the site.

The "True Activity" feature will provide a more accurate representation of user engagement by tracking actual user interactions (mouse movements, clicks, scrolling) via a client-side heartbeat.

### 1.1. User Status Definitions

To provide administrators with a clear and nuanced view of user presence, we will introduce the following statuses, which will be reflected in the UI:

*   **Active:** A logged-in user who has shown interaction with the site (mouse move, click, scroll, etc.) within a configurable time threshold. This is the highest level of certainty that the user is currently engaged.
*   **Online:** A logged-in user who has an active session but has *not* shown any interaction within the "Active" threshold. This indicates the user may have left a tab open but is not actively using it.
*   **Offline:** A user who does not have an active session.
*   **Last Activity:** For offline users, this will display the time of their last recorded interaction, giving a more accurate "last seen" time than the session login time.

## 2. Technical Approach

### 2.1. Client-Side Heartbeat

*   A new JavaScript file (`true-activity-heartbeat.js`) will be created.
*   This script will be enqueued with the handle `fwaum-true-activity-heartbeat` and will only be initialized for **logged-in users** using the `is_user_logged_in()` check in PHP.
*   The script will listen for user interaction events (`mousemove`, `click`, `scroll`, `keydown`).
*   To avoid overwhelming the server, these events will be **throttled**. An activity "ping" will be sent to the server at most once every X seconds (this will be a configurable setting).
*   The ping will be a lightweight AJAX POST request to a new WordPress AJAX action.

### 2.2. Server-Side Tracking

*   A new AJAX action, `fwaum_true_activity_heartbeat`, will be registered in `class-ajax-handler.php`.
*   The handler function will perform the following steps:
    1.  Verify the request using a WordPress nonce for security.
    2.  Check that the request is from a logged-in user.
    3.  If valid, it will update a user meta field for the current user: `fwaum_last_activity_timestamp`, with the current server time (`time()`).
*   A new method will be added to `class-user-tracker.php`, `is_user_truly_active($user_id)`, which will:
    1.  Retrieve the `fwaum_last_activity_timestamp` for the given user.
    2.  Compare this timestamp against the current time and the configurable "Active" threshold.
    3.  Return `true` if the last activity was within the threshold, otherwise `false`.

## 3. Settings and Configuration

The following new settings will be added to the plugin's settings page (`includes/class-settings.php`). All setting keys are stored within the main `fwaum_settings` option array.

| Setting                           | Type    | Section          | Description                                                                                             | Default Value |
| --------------------------------- | ------- | ---------------- | ------------------------------------------------------------------------------------------------------- | ------------- |
| `fwaum_enable_true_activity`      | Checkbox| General          | Enables or disables the entire True Activity tracking feature.                                          | `false`       |
| `fwaum_true_activity_threshold`   | Number  | General          | The number of minutes of inactivity before a user is no longer considered "Active".                    | `5` minutes   |
| `fwaum_heartbeat_frequency`       | Number  | General          | How often (in seconds) the browser should send an activity ping to the server. A higher value is less resource-intensive. | `60` seconds  |

## 4. Implementation Plan

### 4.1. File Changes

*   **Create:** `fullworks-active-users-monitor/assets/js/true-activity-heartbeat.js`
*   **Modify:** `fullworks-active-users-monitor/fullworks-active-users-monitor.php` (to enqueue the new script)
*   **Modify:** `fullworks-active-users-monitor/includes/class-settings.php` (to add new settings fields)
*   **Modify:** `fullworks-active-users-monitor/includes/class-ajax-handler.php` (to add the new AJAX endpoint)
*   **Modify:** `fullworks-active-users-monitor/includes/class-user-tracker.php` (to add new activity tracking logic)
*   **Modify:** `fullworks-active-users-monitor/includes/class-users-list.php` (to update UI)
*   **Modify:** `fullworks-active-users-monitor/includes/class-dashboard-widget.php` (to update UI)
*   **Modify:** `fullworks-active-users-monitor/includes/class-admin-bar.php` (to update UI)

### 4.2. UI/UX Changes

The UI will be updated to reflect the new statuses:

*   **User List Table:**
    *   **Active** users will have a solid green indicator.
    *   **Online** (session active, but not "Active") users will have a hollow green or solid yellow indicator.
    *   **Offline** users will have a gray indicator.
    *   The "Last Seen" column will be updated to show "Last Activity" where available, providing a more accurate time.
*   **Dashboard Widget & Admin Bar:** The count of "online" users will be updated to specifically count "Active" users. The tooltip or breakdown could show the distinction between Active and Online users.

## 5. WordPress Plugin Standards

*   **Security:** All AJAX endpoints will be protected with nonces (`wp_create_nonce`, `check_ajax_referer`). All inputs will be sanitized.
*   **Performance:** The heartbeat will be throttled. The server-side code will be efficient, using direct database updates (`update_user_meta`) which are well-optimized.
*   **Internationalization:** All new user-facing strings in the UI and settings will be wrapped in the appropriate translation functions (e.g., `esc_html__`, `sprintf`).
*   **Coding Standards:** All new code will adhere to the WordPress PHP Coding Standards and will be checked with `phpcs`.

## 6. Testing Plan

### 6.1. Unit Tests

*   New PHPUnit tests will be written for the `is_user_truly_active()` method in `class-user-tracker.php` to cover various scenarios (active, inactive, no timestamp).

### 6.2. Manual Testing

1.  **Enable/Disable:** Verify that the entire feature turns on and off with the `enable_true_activity` setting.
2.  **Heartbeat:**
    *   Log in as a user.
    *   Use the browser's developer tools to confirm the AJAX heartbeat is sent only once per `heartbeat_frequency` interval while interacting with the page.
    *   Confirm the heartbeat stops if the user is idle.
    *   Confirm no heartbeat is sent for logged-out users.
3.  **User Status UI:**
    *   Log in with a test user and interact with the site. Log in as an admin in a separate browser. Verify the test user shows as "Active" in the user list.
    *   Stop interacting with the site as the test user. Wait for the `true_activity_threshold` to pass. Verify the user's status changes from "Active" to "Online".
    *   Log out with the test user. Verify the status changes to "Offline" and the "Last Activity" timestamp is accurate.
4.  **Settings:** Verify that changing the `true_activity_threshold` and `heartbeat_frequency` settings correctly changes the behavior of the feature.

## 7. Documentation (README / FAQs)

The plugin's `README.md` and any marketing materials will be updated to include:

*   A clear explanation of the "True Activity" feature and how it differs from the default session-based tracking.
*   A new FAQ section:
    *   **Q: What's the difference between "Active" and "Online"?**
    *   **A:** "Online" means the user has an active login session with your site, but they may not be actively using it. "Active" means they are currently interacting with your site (e.g., moving their mouse, scrolling, or typing). This provides a much more accurate, real-time view of user engagement.
    *   **Q: Will this feature slow down my site?**
    *   **A:** The feature is designed to be lightweight. The browser-based activity pings are throttled (limited) to a configurable interval, and the server-side processing is highly efficient. The default settings should have no noticeable impact on site performance.
