#!/usr/bin/env bash
# Builds the website's CSS (public/css/pizi-*.css) from the classes used in the code.
# Run it from anywhere after adding or changing Tailwind classes, then commit public/css:
#     bash scripts/build-css.sh
# Needs Node.js. The server needs nothing: it only serves the finished CSS files.
set -e
cd "$(dirname "$0")/.."
mkdir -p public/css
for name in site panel plain; do
    npx --yes tailwindcss@3.4.17 -c "resources/tailwind/$name.config.js" -i resources/tailwind/input.css -o "public/css/pizi-$name.css" --minify
done
ls -l public/css
