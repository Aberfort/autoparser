#!/usr/bin/env bash
#
# Build a distributable sc-autoparser.zip for WordPress.org / manual install.
# Usage: ./build.sh
#
set -euo pipefail

PLUGIN_SLUG="sc-autoparser"
ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
BUILD_DIR="$(mktemp -d)"
STAGE_DIR="${BUILD_DIR}/${PLUGIN_SLUG}"
ZIP_PATH="${ROOT_DIR}/${PLUGIN_SLUG}.zip"

cleanup() {
    rm -rf "${BUILD_DIR}"
}
trap cleanup EXIT

echo "==> Installing production PHP dependencies"
cd "${ROOT_DIR}"
composer install --no-dev --optimize-autoloader --classmap-authoritative

echo "==> Installing JS dependencies and building admin bundle"
npm ci
npm run build

echo "==> Staging files (respecting .distignore) into ${STAGE_DIR}"
mkdir -p "${STAGE_DIR}"
rsync -a \
    --exclude-from="${ROOT_DIR}/.distignore" \
    "${ROOT_DIR}/" "${STAGE_DIR}/"

echo "==> Creating ${ZIP_PATH}"
rm -f "${ZIP_PATH}"
(cd "${BUILD_DIR}" && zip -r -q "${ZIP_PATH}" "${PLUGIN_SLUG}")

echo "==> Restoring dev dependencies for local development"
cd "${ROOT_DIR}"
composer install

echo "Done: ${ZIP_PATH}"
