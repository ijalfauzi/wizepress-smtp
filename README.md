# Modifus SMTP

**SMTP & Email Log — make sure your WordPress emails reach the inbox, and see every one that's sent.**

Modifus SMTP sends your WordPress email through your own SMTP server and keeps a detailed log of every outgoing message. Built with performance and simplicity in mind.

## ✨ Features

**SMTP**
- Works with any SMTP server: SSL, TLS or no encryption, with or without a login
- Password stored encrypted, or set in `wp-config.php` with `MODIFUS_SMTP_PASSWORD`
- Set the From email and name for every email, or only replace WordPress's default
- Send a test email from the settings page, with the server's error shown if it fails

**Email log**
- Logs every outgoing email, including the error for ones that failed
- Search, filter by month or status, and sort
- View content as raw text or an HTML preview
- Resend any logged email
- Export to CSV, Excel (.xlsx) or print/PDF
- Delete logs automatically after a set number of days
- Works with WordPress's personal data export and erase tools

## 📦 Installation

1. Upload the plugin to `/wp-content/plugins/modifus-smtp`
2. Activate the plugin from **Plugins > Installed Plugins**
3. Go to **Settings > Modifus SMTP** to configure your SMTP server

## 🔁 Upgrading from WizePress SMTP

Modifus SMTP is the new name of WizePress SMTP. To switch without losing anything:

1. Install and activate Modifus SMTP.
2. Deactivate WizePress SMTP. Your SMTP settings, email logs and screen options move over automatically.
3. Delete WizePress SMTP.

## 📄 Changelog

See [CHANGELOG.md](./CHANGELOG.md) for version history and updates.

## 🧑‍💻 Author

Built by [Ijal Fauzi](https://github.com/ijalfauzi) at [Modifus](https://modifus.com).

---

Modifus SMTP | Version 2.0.1
