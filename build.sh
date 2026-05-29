#!/usr/bin/env bash
#
# Build a distributable EveryAlt plugin ZIP.
#
# Produces builds/everyalt-<version>.zip with a single top-level "everyalt/"
# folder, so it installs cleanly via the WordPress "Upload Plugin" screen and
# the plugin slug/path stays "everyalt".
#
# The ZIP contains committed plugin files only. Dev-only files (build.sh,
# .gitignore, .gitattributes, builds/) are excluded via the `export-ignore`
# rules in .gitattributes.
#
# Usage:
#   ./build.sh             # build from the latest commit (HEAD)
#   ./build.sh <git-ref>   # build from a specific tag/branch/commit, e.g. v1.0.2
#
set -euo pipefail

# Always operate from the repository root.
cd "$(git rev-parse --show-toplevel)"

REF="${1:-HEAD}"

# Read the plugin version from the EVERY_ALT_VERSION constant in everyalt.php.
VERSION="$(sed -nE "s/.*define\( *'EVERY_ALT_VERSION', *'([^']+)'.*/\1/p" everyalt.php)"
if [ -z "${VERSION}" ]; then
  echo "Error: could not determine version from everyalt.php" >&2
  exit 1
fi

OUT_DIR="builds"
OUT="${OUT_DIR}/everyalt-${VERSION}.zip"

mkdir -p "${OUT_DIR}"
rm -f "${OUT}"

# git archive ships only committed, non-export-ignored files, nested under everyalt/.
git archive --format=zip --prefix="everyalt/" -o "${OUT}" "${REF}"

COUNT="$(unzip -l "${OUT}" | tail -1 | awk '{print $2}')"
echo "Built ${OUT}"
echo "  version: ${VERSION}  |  ref: ${REF}  |  files: ${COUNT}"
echo "Upload this ZIP via WordPress > Plugins > Add New > Upload Plugin."
