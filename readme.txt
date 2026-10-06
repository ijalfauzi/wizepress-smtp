=== Modifus SMTP ===
Contributors: ijalfauzi
Tags: smtp, email log, mail, email, deliverability
Requires at least: 6.2
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 2.0.1
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Make sure your WordPress emails reach the inbox, and see every one that's sent.

== Description ==

Modifus SMTP sends your WordPress email through your own SMTP server instead of the server's default mail function, and keeps a log of every outgoing message.

**SMTP**

* Works with any SMTP server: SSL, TLS or no encryption, with or without a login
* Password stored encrypted, or set in wp-config.php with `MODIFUS_SMTP_PASSWORD`
* Set the From email and name for every email, or only when WordPress would use its default
* Send a test email from the settings page, with the server's error shown if it fails

**Email log**

* Logs every email with recipient, sender, subject, content, attachments and delivery status
* Shows the error message for emails that failed
* Search, filter by month or status, and sort
* View the content as raw text or an HTML preview
* Resend any logged email
* Export to CSV, Excel (.xlsx) or a printable page
* Delete logs automatically after a set number of days
* Works with WordPress's personal data export and erase tools

Built by [Modifus](https://modifus.com).

== Installation ==

1. Upload the plugin to `/wp-content/plugins/modifus-smtp`, or install it from the Plugins screen.
2. Activate the plugin.
3. Go to **Settings > Modifus SMTP > SMTP Settings** and enter your SMTP server details.
4. Send a test email to check they work.

== Frequently Asked Questions ==

= I used WizePress SMTP. How do I switch? =

Modifus SMTP is the new name of WizePress SMTP. Install and activate Modifus SMTP, then deactivate WizePress SMTP. Your SMTP settings and email logs move over automatically, and you can then delete WizePress SMTP.

= How do I keep the SMTP password out of the database? =

Define the `MODIFUS_SMTP_PASSWORD` constant in wp-config.php, set to your SMTP password. The password field on the settings page is then disabled.

= The settings page says the saved password can't be read =

The password is encrypted with the secret keys in wp-config.php. If those keys change, enter the password again.

= Does it log email content? =

Yes, the full message is kept so you can view and resend it. That can include sensitive content such as password reset links, so set **Keep Logs For** to delete old logs automatically.

== Upgrade Notice ==

= 2.0.1 =
Code quality and security hardening for the WordPress.org plugin directory. Requires WordPress 6.2 or later.

== Changelog ==

= 2.0.1 =
* Requires WordPress 6.2 or later.
* Database queries use prepared table and column names.
* All request input is unslashed and sanitized.
* The print/PDF export page loads its styles and script as files.
* Coding standards fixes for the WordPress.org plugin directory.

= 2.0.0 =
* Renamed from WizePress SMTP to Modifus SMTP. Settings and logs move over automatically.
* SMTP without a login, configurable From email and name, encrypted password storage.
* Log retention, personal data export and erase, real .xlsx export.
* Fixed failed emails being logged without recipient or subject, times shown in the wrong timezone, and the log table not upgrading on plugin updates.

See CHANGELOG.md for the full history.
