# Changelog

All notable changes to this project will be documented in this file.

## [1.2.0] - 2026-01-02

### Added
- Native WordPress WP_List_Table for email logs with bulk actions, search, and pagination.
- Date filter (months dropdown) for email logs.
- Status filter (Success/Failed) for email logs.
- Uninstall cleanup (removes database table and options on plugin deletion).
- Screen options for customizing logs per page.

### Changed
- Email Logs is now the default tab (previously SMTP Settings).
- Tab order updated: Email Logs | SMTP Settings.
- Restructured "Sent At" column to display date, time, ID, and row actions.
- Reordered columns: Sent At, Status, To, Subject, Attachments, IP Address.
- Replaced Message column with View Content and Delete action links.

### Security
- Added nonce verification to all AJAX handlers.
- Added capability checks (`manage_options`) to AJAX handlers.
- Sanitized `error_message` before database insertion.
- Used `$wpdb->prepare()` for all SQL queries.

### Fixed
- Duplicate logging when sending test emails.
- CSS redundancy in admin stylesheet.

## [1.1.0] - 2025-06-13

### Added
- Attachments count and filename display in log viewer.
- IP address logging for each email sent.
- Email result indicator in log table.
- HTML preview support with clean iframe viewer.
- WordPress timezone-based formatting for "Sent At".
- Manual logging for test emails to prevent duplicates.

### Changed
- `to_email` now stored as a clean string instead of serialized array for better readability.

## [1.0.0] - 2025-06-13

### Added
- SMTP settings page with host, port, encryption, username, password
- Test email functionality
- Email log viewer with modal (raw + HTML preview)
- Tabbed admin interface (Settings / Logs)
- Enqueued external CSS/JS for clean UI

### Changed
- Refactored code into `/admin`, `/includes`, `/assets`
- Removed inline JS/CSS and replaced with separate files
- Improved modal with WordPress-native styles

### Fixed
- Activation errors due to path issues
- Duplicate form ID warnings in admin
- JS modal issues due to missing localization

Initial stable release after internal prototype.
