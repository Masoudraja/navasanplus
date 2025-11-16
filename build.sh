#!/bin/bash

# Simple Production Build Script for MNS Navasan Plus
# Usage: ./build.sh

echo "🔨 Building MNS Navasan Plus production ZIP..."
echo ""

# Step 1: Clean temp directory
echo "📁 Preparing build directory..."
rm -rf /tmp/mns-navasan-plus-build
mkdir -p /tmp/mns-navasan-plus-build

# Step 2: Copy files
echo "📦 Copying production files..."
cd "$(dirname "$0")"

rsync -av --quiet \
  --exclude='node_modules' \
  --exclude='.git' \
  --exclude='.gitignore' \
  --exclude='.distignore' \
  --exclude='BUILD.md' \
  --exclude='build.sh' \
  --exclude='scripts' \
  --exclude='package.json' \
  --exclude='package-lock.json' \
  --exclude='composer.json' \
  --exclude='composer.lock' \
  --exclude='.DS_Store' \
  --exclude='.claude' \
  . /tmp/mns-navasan-plus-build/

# Step 3: Clean up development files
echo "🧹 Removing development files..."
cd /tmp/mns-navasan-plus-build

# Remove non-minified JS files (but keep .min.js and .min.js.map)
find assets/js -type f -name "*.js" ! -name "*.min.js" ! -name "*.min.js.map" -delete 2>/dev/null

# Remove non-minified CSS files (but keep .min.css)
find assets/css -type f -name "*.css" ! -name "*.min.css" -delete 2>/dev/null

# Remove .DS_Store files
find . -name ".DS_Store" -delete 2>/dev/null

# Remove source map files (optional - they're only for debugging)
find assets -type f -name "*.map" -delete 2>/dev/null

# Step 4: Create ZIP
echo "📦 Creating ZIP file..."
zip -rq ~/Desktop/mns-navasan-plus.zip . -x ".claude/*"

# Step 5: Show results
echo ""
echo "✅ Build complete!"
echo ""
echo "📍 Location: ~/Desktop/mns-navasan-plus.zip"
echo "📏 Size: $(du -sh ~/Desktop/mns-navasan-plus.zip | cut -f1)"
echo ""
echo "🚀 Ready to upload to WordPress!"
