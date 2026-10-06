# WordPress.org submission checklist: Modifus SMTP 2.0.1

The release zip is built with `./build-release.sh` and creates `modifus-smtp-2.0.1.zip`, which contains a top-level `modifus-smtp/` folder. This file, `RELEASE-INSTRUCTIONS.md`, `build-release.sh` and `.claude/` are not included in the zip.

## Already done (v2.0.1)

- [x] Plugin Check 2.1.0, all categories, static and runtime, with low-severity and experimental checks: **no errors or warnings**
- [x] Every `phpcs:ignore` has a reason. They are limited to direct queries on the plugin's own table, read-only list filters, `php://output`, and streaming the temporary export file
- [x] No `load_plugin_textdomain()`, no inline `<style>`/`onclick`, no `@unlink()`
- [x] readme.txt name matches the header (`Modifus SMTP`), five tags, none of them another plugin's name
- [x] Version 2.0.1 in the header, `MODIFUS_SMTP_VERSION`, Stable tag and changelog
- [x] Tested on WordPress 7.1.2 / PHP 8.3 / MariaDB: sending, failed-send logging, list, filters, sorting, search, view/resend/delete, bulk delete, CSV/Excel/print exports, settings, test email, privacy export and erase, retention cleanup, migration from WizePress SMTP, in-place update from 2.0.0, uninstall

## Before submitting

- [ ] Log in to WordPress.org as **ijalfauzi**, the account in `Contributors:`. It will own the plugin.
- [ ] Turn on two-factor authentication for that account (Profile → Account & Security). Plugin committers need it.
- [ ] Check that `https://modifus.com/plugins/modifus-smtp` (Plugin URI) and `https://modifus.com` (Author URI) load. Reviewers check both, and they must point to different pages.
- [ ] Allow emails from `plugins@wordpress.org` and `*@wordpress.org` so review emails don't land in spam.

## Submit

1. Go to <https://wordpress.org/plugins/developers/add/>.
2. Upload `modifus-smtp-2.0.1.zip`.
3. Check the slug shown on the confirmation page. It should be **`modifus-smtp`**. If it's wrong, reply to the review email asking for `modifus-smtp` **before** approval, because slugs can't be changed afterwards.
4. Accept the guidelines checkboxes and submit.
5. Wait for the review email. The queue can take several weeks. **Don't resubmit**: that puts the plugin back at the end of the queue. To change the code while waiting, upload a new zip from the "Add your plugin" page (the pending submission can be updated there) or reply to the review email.
6. Reply to every reviewer email from the same account/email, and attach or upload the fixed zip as they ask. Don't argue for phpcs-ignores they reject; fix the code.

Things a reviewer may still ask about, and the answers:

- **Direct database queries.** The plugin keeps its own log table, `{prefix}modifus_smtp_logs`, and every query is prepared, with `%i` for table and column names.
- **Storing email content.** It's needed to view and resend emails. It's covered by the privacy policy text, the personal data exporter and eraser, and the retention setting.
- **WizePress SMTP references.** WizePress SMTP is this plugin's previous name, by the same author. The code only migrates its own old data.

## After approval: SVN

You'll get an email with the SVN URL `https://plugins.svn.wordpress.org/modifus-smtp`. Your SVN password is set at Profile → Account & Security → "SVN password". It is not your login password.

```bash
# 1. Check out the empty repository
svn co https://plugins.svn.wordpress.org/modifus-smtp modifus-smtp-svn
cd modifus-smtp-svn

# 2. trunk = the plugin files (the contents of the zip's modifus-smtp/ folder, not the folder itself)
unzip -q /path/to/modifus-smtp-2.0.1.zip -d /tmp/modifus-release
rsync -a --delete /tmp/modifus-release/modifus-smtp/ trunk/
svn add --force trunk
svn status            # check: no stray files, nothing missing ("!")

# 3. tags/2.0.1 = a copy of trunk (Stable tag in trunk/readme.txt must match)
svn cp trunk tags/2.0.1

# 4. assets/ = directory page images (NOT in trunk; never shipped to sites)
cp /path/to/wporg-assets/* assets/
svn add --force assets
svn propset svn:mime-type image/png assets/*.png
svn propset svn:mime-type image/jpeg assets/*.jpg   # if any
svn propset svn:mime-type image/svg+xml assets/*.svg  # if any

# 5. Commit
svn ci -m "Release 2.0.1" --username ijalfauzi
```

### Assets to prepare (`assets/` in SVN, keep them in `wporg-assets/` in the repo, which the build excludes)

| File | Size | Required |
|---|---|---|
| `icon-256x256.png` | 256×256 | Recommended |
| `icon-128x128.png` | 128×128 | Recommended |
| `icon.svg` | vector | Optional (instead of or as well as the PNGs) |
| `banner-1544x500.png` | 1544×500 | Recommended (retina) |
| `banner-772x250.png` | 772×250 | Recommended |
| `screenshot-1.png`, `screenshot-2.png`, … | any | Optional |

For screenshots, add a `== Screenshots ==` section to `readme.txt` with one numbered caption per file, for example:

```
== Screenshots ==

1. Email log with filters and export buttons.
2. Email content viewer.
3. SMTP settings.
```

Changing `readme.txt` only for screenshots doesn't need a new version: update it in both `trunk/` and `tags/2.0.1/` and commit.

### After the SVN commit

- [ ] Within about 15 minutes, <https://wordpress.org/plugins/modifus-smtp/> shows 2.0.1, and the zip downloads from `https://downloads.wordpress.org/plugin/modifus-smtp.2.0.1.zip`.
- [ ] Install from Plugins → Add New on a test site and activate it.
- [ ] Tag the GitHub release: `git tag -a v2.0.1 -m "Version 2.0.1" && git push origin v2.0.1`, then attach the same zip.

### Future releases

1. Bump the version in the header, `MODIFUS_SMTP_VERSION`, `Stable tag`, `== Changelog ==` and CHANGELOG.md. Bump `MODIFUS_SMTP_DB_VERSION` only when the table schema changes.
2. Run Plugin Check.
3. Copy the new files into `trunk/`, then `svn cp trunk tags/X.Y.Z` and commit. `Stable tag` must point to an existing tag.
4. Raise `Tested up to` when a new WordPress major version comes out. You can change that in `trunk/readme.txt` and the current tag without a release.
