#!/usr/bin/env bash
#
# Build a distributable EveryAlt plugin ZIP.
#
# Produces builds/everyalt-<version>.zip with a single top-level "everyalt/"
# folder, so it installs cleanly via the WordPress "Upload Plugin" screen and
# the plugin slug/path stays "everyalt".
#
# The ZIP contains committed plugin files plus the bundled update checker
# library (vendor/plugin-update-checker, pinned below and fetched at build
# time, since vendor/ is not committed). Dev-only files (build.sh, .gitignore,
# .gitattributes, .github/, builds/) are excluded via the `export-ignore`
# rules in .gitattributes.
#
# Usage:
#   ./build.sh             # build from the latest commit (HEAD)
#   ./build.sh <git-ref>   # build from a specific tag/branch/commit, e.g. v1.0.2
#
set -euo pipefail

# Pinned plugin-update-checker release bundled into the ZIP.
PUC_REPO="https://github.com/YahnisElsts/plugin-update-checker.git"
PUC_VERSION="v5.7"

# Always operate from the repository root.
cd "$(git rev-parse --show-toplevel)"

REF="${1:-HEAD}"

# Read the version from each place it is declared, at the ref being built.
MAIN_FILE="$(git show "${REF}:everyalt.php")"
VERSION="$(sed -nE "s/.*define\( *'EVERY_ALT_VERSION', *'([^']+)'.*/\1/p" <<<"${MAIN_FILE}")"
HEADER_VERSION="$(sed -nE 's/^[ *]*Version: *([^ ]+).*/\1/p' <<<"${MAIN_FILE}")"
# Badge text is shields.io-escaped: a literal "-" is written "--" (e.g. EveryAlt-1.1.0--beta.1-7c3aed).
README_VERSION="$(git show "${REF}:README.md" | sed -nE 's/.*badge\/EveryAlt-(.+)-[0-9a-fA-F]{6}\?.*/\1/p' | head -1 | sed 's/--/-/g')"

if [ -z "${VERSION}" ]; then
  echo "Error: could not determine version from everyalt.php" >&2
  exit 1
fi
if [ "${HEADER_VERSION}" != "${VERSION}" ] || [ "${README_VERSION}" != "${VERSION}" ]; then
  echo "Error: version mismatch — EVERY_ALT_VERSION=${VERSION}, plugin header=${HEADER_VERSION:-?}, README badge=${README_VERSION:-?}" >&2
  exit 1
fi

OUT_DIR="builds"
OUT="${OUT_DIR}/everyalt-${VERSION}.zip"

STAGE="$(mktemp -d)"
trap 'rm -rf "${STAGE}"' EXIT

# git archive ships only committed, non-export-ignored files.
git archive --format=tar --prefix="everyalt/" "${REF}" | tar -x -C "${STAGE}"

# Bundle the update checker so installed copies can see new GitHub releases.
PUC_DIR="${STAGE}/everyalt/vendor/plugin-update-checker"
git clone --quiet --depth 1 --branch "${PUC_VERSION}" "${PUC_REPO}" "${PUC_DIR}" 2>/dev/null \
  || { echo "Error: could not fetch plugin-update-checker ${PUC_VERSION}" >&2; exit 1; }
rm -rf "${PUC_DIR}/.git" "${PUC_DIR}/.github"
if [ ! -f "${PUC_DIR}/plugin-update-checker.php" ]; then
  echo "Error: plugin-update-checker.php missing from ${PUC_VERSION}" >&2
  exit 1
fi

mkdir -p "${OUT_DIR}"
rm -f "${OUT}"
( cd "${STAGE}" && zip -qr -X - everyalt ) > "${OUT}"

COUNT="$(unzip -l "${OUT}" | tail -1 | awk '{print $2}')"
echo "Built ${OUT}"
echo "  version: ${VERSION}  |  ref: ${REF}  |  update checker: ${PUC_VERSION}  |  files: ${COUNT}"
echo "Upload this ZIP via WordPress > Plugins > Add New > Upload Plugin."
