# Changelog

All notable changes to this project will be documented in this file.

## [2.0.0] - 2026-10-06

### Changed
- **Renamed from WizePress SMTP to Modifus SMTP.** New plugin folder and main file (`modifus-smtp/modifus-smtp.php`), text domain `modifus-smtp`, admin page `Settings > Modifus SMTP`.
- Author is now Modifus (https://modifus.com).
- Internal prefixes renamed from `wzp_` / `WZP_` to `modifus_smtp_` / `MODIFUS_SMTP_`.
- Settings option renamed to `modifus_smtp_settings` and log table to `{prefix}modifus_smtp_logs`.

### Added
- Automatic migration from WizePress SMTP: settings, email logs and the logs-per-page screen option are moved over once WizePress SMTP is deactivated. An admin notice asks to deactivate it while both are active.
- Index on `sent_at` in the log table.

### Fixed
- Log table schema now upgrades on in-place plugin updates. Previously `CREATE TABLE IF NOT EXISTS` stopped `dbDelta()` from adding new columns, and the activation hook doesn't run on updates, so sites installed before v1.1.0 could silently stop logging.

## [1.3.0] - 2026-01-02

### Added
- **Email Resend Feature**: Resend any logged email directly from the log viewer.
- **Export to CSV/Excel/PDF**: Download email logs in CSV, Excel (.xls), or print-friendly HTML format.
- **Error Details in Modal**: Failed emails now display the error message in the view content modal.

### Changed
- Export buttons now grouped in toolbar with filter-aware filenames.
- Exports use chronological ordering (oldest first) for audit trails.

## [1.2.1] - 2026-01-02

### Fixed
- Email content modal height increased to 500px for better viewing
- Modal now properly centered vertically and horizontally using flexbox
- Email log sorting now uses ID DESC as secondary sort for consistent ordering

### Added
- Build script (`build-release.sh`) for creating GitHub-compatible release packages
- Release instructions documentation

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
