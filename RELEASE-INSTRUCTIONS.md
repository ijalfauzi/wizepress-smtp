# Release Instructions for GitHub

## Creating a Proper Release

When creating a GitHub release, WordPress expects the plugin folder to be named `wizepress-smtp` (without version suffix).

GitHub's auto-generated source code archives create folders named `wizepress-smtp-1.2.0` which causes issues when users extract the ZIP directly to their plugins folder.

### Solution: Use Build Script

Run the build script to create a properly formatted release ZIP:

```bash
./build-release.sh
```

This creates: `wizepress-smtp-1.2.0.zip` containing a folder named `wizepress-smtp/`

### Upload to GitHub Release

After creating a tag and release on GitHub:

```bash
# Create and push tag
git tag -a v1.2.0 -m "Version 1.2.0"
git push origin v1.2.0

# Build release ZIP
./build-release.sh

# Upload to GitHub release
gh release create v1.2.0 --title "v1.2.0" --notes "Release notes here"
gh release upload v1.2.0 wizepress-smtp-1.2.0.zip
```

### What Gets Excluded

The build script excludes:
- `.git` and `.github` folders
- `node_modules`
- `build` folder
- `wporg-assets` folder (only needed for WordPress.org SVN)
- Build scripts (`*.sh`)
- Documentation files (`WORDPRESS-ORG-SUBMISSION.md`)
- `.claude` folder
- Existing ZIP files

### For Users

Users downloading from GitHub should:
1. Download the attached `wizepress-smtp-1.2.0.zip` (not the source code archives)
2. Extract to `wp-content/plugins/`
3. The folder will be correctly named `wizepress-smtp`

### GitHub Actions (Optional Future Enhancement)

Consider adding a GitHub Action to automate this:

```yaml
name: Build Release
on:
  release:
    types: [created]
jobs:
  build:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v2
      - name: Build ZIP
        run: ./build-release.sh
      - name: Upload Release Asset
        uses: actions/upload-release-asset@v1
        env:
          GITHUB_TOKEN: ${{ secrets.GITHUB_TOKEN }}
        with:
          upload_url: ${{ github.event.release.upload_url }}
          asset_path: ./wizepress-smtp-${{ github.event.release.tag_name }}.zip
          asset_name: wizepress-smtp-${{ github.event.release.tag_name }}.zip
          asset_content_type: application/zip
```
