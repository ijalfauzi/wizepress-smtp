#!/bin/bash

# Build script for creating WordPress.org compatible release ZIP

set -e

# Get version from plugin file
VERSION=$(grep -oP "Version:\s*\K[\d.]+" wizepress-smtp.php)
PLUGIN_SLUG="wizepress-smtp"
BUILD_DIR="build"
RELEASE_DIR="$BUILD_DIR/$PLUGIN_SLUG"

echo "Building $PLUGIN_SLUG version $VERSION..."

# Clean build directory
rm -rf "$BUILD_DIR"
mkdir -p "$RELEASE_DIR"

# Copy plugin files
echo "Copying plugin files..."
rsync -av --progress . "$RELEASE_DIR" \
    --exclude='.git' \
    --exclude='.gitignore' \
    --exclude='gitignore' \
    --exclude='.github' \
    --exclude='node_modules' \
    --exclude='build' \
    --exclude='wporg-assets' \
    --exclude='*.sh' \
    --exclude='WORDPRESS-ORG-SUBMISSION.md' \
    --exclude='RELEASE-INSTRUCTIONS.md' \
    --exclude='.claude' \
    --exclude='*.zip'

# Create ZIP
echo "Creating ZIP archive..."
cd "$BUILD_DIR"
zip -r "../${PLUGIN_SLUG}-${VERSION}.zip" "$PLUGIN_SLUG"
cd ..

# Cleanup
rm -rf "$BUILD_DIR"

echo "✅ Release ZIP created: ${PLUGIN_SLUG}-${VERSION}.zip"
echo ""
echo "To upload to GitHub release:"
echo "gh release upload v${VERSION} ${PLUGIN_SLUG}-${VERSION}.zip"
