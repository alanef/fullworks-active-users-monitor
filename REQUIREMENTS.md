# WordPress Plugin Development Prompt: Active Users Monitor

## Project Overview
Create a WordPress plugin called "Active Users Monitor" that provides real-time visibility of logged-in users for administrators. The plugin should use WordPress's native session tokens system to accurately track user login states and provide both visual indicators and filtering capabilities in the admin interface.

## Core Requirements

### 1. Session-Based User Tracking
- Use WordPress's built-in `WP_Session_Tokens` class to determine logged-in status
- Check for active session tokens rather than tracking login/logout events
- Do NOT create custom database tables or user meta for tracking
- Ensure the solution works with all authentication methods (standard, SSO, 2FA, etc.)
- Must accurately reflect real login state, not just activity

### 2. Admin Bar Display
**For administrators only**, add to the admin bar:
- A user count widget showing: "👥 Users Online: X"
- Breakdown on hover/click showing counts by role (e.g., "3 Admins, 5 Editors, 12 Subscribers")
- The count should link directly to the Users page (`/wp-admin/users.php`)
- Position the widget prominently but not obtrusively (suggest: before or after site name)
- Use AJAX to refresh the count every 30 seconds without page reload
- Include a subtle pulse animation when the count changes

### 3. Users List Page Enhancements

#### Visual Indicators:
- Add an "Online Status" column to the users table
- Use color-coded status dots:
	- Green (●) for online users
	- Gray (○) for offline users
- Add distinctive visual treatment for online users in the username column:
	- **Administrators**: Gold/orange ring border with glow effect
	- **Other roles**: Green ring border
	- Include subtle CSS animations (pulse effect) for online indicators
- Add an "ONLINE" badge next to usernames of logged-in users
- Apply light background tinting to entire rows of online users

#### Filtering System:
- Add a filter dropdown/button above the users table: "Show: All Users | Online Only | Offline Only"
- Integrate with WordPress's existing views filters (All, Administrator, Editor, etc.)
- Make the filter state persist using URL parameters
- Add a quick stats summary above the table: "X users online (Y admins, Z others)"

### 4. Performance Optimization
- Cache session check results using transients (suggest 30-second cache)
- Use efficient batch queries when checking multiple users
- Implement AJAX updates instead of full page refreshes
- Minimize database queries by checking sessions only for visible users on paginated lists
- Include option to disable real-time updates for high-traffic sites

### 5. User Experience Features
- Add tooltips showing "Last seen: X minutes ago" on hover over status indicators
- Include timezone-aware last activity display
- Provide clear visual distinction between role types using consistent color coding:
	- Administrators: Gold/Orange theme
	- Editors: Blue theme
	- Authors: Green theme
	- Contributors: Purple theme
	- Subscribers: Gray theme
- Ensure all visual elements are accessibility-compliant (not relying solely on color)
- Include screen reader text for status indicators

### 6. WordPress Coding Standards
- Follow WordPress.org plugin repository guidelines
- Use proper namespacing or class prefixing to avoid conflicts
- Implement proper capability checks (`current_user_can()`)
- Use WordPress nonce verification for AJAX requests
- Properly enqueue scripts and styles
- Include inline documentation following PHPDoc standards
- Escape all output appropriately
- Use WordPress's i18n functions for all user-facing strings
- Include proper plugin headers with complete metadata

### 7. Additional Features
- Add a dashboard widget showing online users summary (optional for admins)
- Include WP-CLI command support for checking online users
- Add filter hooks for developers to customize the online detection logic
- Provide action hooks for when users come online or go offline
- Include a settings page under Users menu with options:
	- Enable/disable admin bar counter
	- Adjust refresh interval (15-300 seconds)
	- Choose which roles can see online status
	- Color scheme customization

### 8. Security Considerations
- Ensure only users with 'list_users' capability can see online status
- Sanitize and validate all input data
- Prevent information disclosure to non-privileged users
- Rate-limit AJAX requests to prevent abuse
- Never expose sensitive session data

### 9. Edge Cases to Handle
- Users with multiple active sessions (multiple devices/browsers)
- Recently expired sessions (grace period handling)
- Super Admins in multisite installations
- Users who close browser without logging out
- Session timeout variations
- High-traffic sites with thousands of users

### 10. Testing Requirements
The plugin should be tested for:
- Compatibility with WordPress 5.9+
- PHP 7.4+ and PHP 8.x compatibility
- Multisite compatibility
- Proper functioning with various user roles
- Performance with 1000+ users
- Proper cleanup on plugin deactivation
- No JavaScript errors in console
- Mobile responsive admin display

## File Structure
Organize the plugin with proper file structure:
```
active-users-monitor/
├── active-users-monitor.php (main plugin file)
├── includes/
│   ├── class-user-tracker.php
│   ├── class-admin-bar.php
│   ├── class-users-list.php
│   └── class-ajax-handler.php
├── assets/
│   ├── css/
│   │   └── admin-style.css
│   └── js/
│       └── admin-script.js
├── languages/ (for translation files)
└── readme.txt (WordPress.org compliant)
```

## Expected Behavior Examples

1. **Admin logs in**: Counter in admin bar immediately updates, user appears with gold ring in users list
2. **Regular user logs in**: Counter updates, user appears with green ring
3. **User logs out**: Status changes to offline within 30 seconds
4. **Admin views users page**: Can filter to see only online users, sees real-time updates
5. **Multiple sessions**: User shown as online if ANY session is active

## Success Criteria
- Zero PHP errors or warnings
- No JavaScript console errors
- Page load time impact < 100ms
- Accurate online/offline status within 30 seconds
- Passes WordPress.org plugin review guidelines
- Clear, intuitive UI that matches WordPress admin design patterns
- Fully translatable with proper text domains
