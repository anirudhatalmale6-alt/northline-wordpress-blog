#!/bin/sh
# Builds dist/northline.zip, ready for Appearance -> Themes -> Add New -> Upload Theme.
set -eu

here=$(CDPATH= cd -- "$(dirname -- "$0")" && pwd)
dist="$here/dist"

rm -rf "$dist"
mkdir -p "$dist"

cd "$here/theme"
zip -r -q -X "$dist/northline.zip" northline \
	-x '*.DS_Store' -x '__MACOSX/*'

cd "$here"
echo "wrote dist/northline.zip"
unzip -l "$dist/northline.zip" | tail -1
