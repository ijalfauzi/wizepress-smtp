# Changelog

All notable changes to this project will be documented in this file.

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
