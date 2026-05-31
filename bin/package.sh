#!/usr/bin/env bash
# =============================================================================
# bin/package.sh - Build & verify the ariham-seoagent plugin ZIP
#
# Usage:
#   ./bin/package.sh             # run all checks, then build the ZIP
#   ./bin/package.sh --skip-checks   # skip PHPCS/Plugin Check, just build
#   ./bin/package.sh --check-only    # run checks only, no ZIP
#
# Override the WordPress install path:
#   WP_ROOT=/path/to/wp ./bin/package.sh
# =============================================================================
set -euo pipefail

# ---------------------------------------------------------------------------
# Paths
# ---------------------------------------------------------------------------
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PLUGIN_DIR="$(cd "${SCRIPT_DIR}/.." && pwd)"
PLUGIN_SLUG="ariham-seoagent"
WP_ROOT="${WP_ROOT:-/Users/adityashah/Studio/my-wordpress-website}"
DIST_DIR="${PLUGIN_DIR}/dist"

# ---------------------------------------------------------------------------
# Flags
# ---------------------------------------------------------------------------
SKIP_CHECKS=false
CHECK_ONLY=false
for arg in "$@"; do
	case "$arg" in
		--skip-checks) SKIP_CHECKS=true ;;
		--check-only)  CHECK_ONLY=true  ;;
	esac
done

# ---------------------------------------------------------------------------
# Helpers
# ---------------------------------------------------------------------------
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
CYAN='\033[0;36m'
NC='\033[0m'
info()    { echo -e "${CYAN}[info]${NC}  $*"; }
success() { echo -e "${GREEN}[ok]${NC}    $*"; }
warn()    { echo -e "${YELLOW}[warn]${NC}  $*"; }
fail()    { echo -e "${RED}[fail]${NC}  $*" >&2; exit 1; }

# ---------------------------------------------------------------------------
# Read plugin version from the main PHP header
# ---------------------------------------------------------------------------
VERSION=$(grep -m1 '^ \* Version:' "${PLUGIN_DIR}/${PLUGIN_SLUG}.php" | sed 's/.*Version:[[:space:]]*//')
[[ -z "${VERSION}" ]] && fail "Could not read plugin version from ${PLUGIN_SLUG}.php"

ZIP_NAME="${PLUGIN_SLUG}-${VERSION}.zip"
ZIP_PATH="${DIST_DIR}/${ZIP_NAME}"

echo ""
info "Plugin: ${PLUGIN_SLUG}  v${VERSION}"
echo ""

# ---------------------------------------------------------------------------
# 1. PHPCS
# ---------------------------------------------------------------------------
if [[ "${SKIP_CHECKS}" == false ]]; then
	info "Running PHPCS (WordPress Coding Standards)..."

	PHPCS="${PLUGIN_DIR}/vendor/bin/phpcs"
	[[ ! -x "${PHPCS}" ]] && fail "PHPCS not found - run 'composer install' first"

	SCAN_TARGETS=(
		"${PLUGIN_DIR}/ariham-seoagent.php"
		"${PLUGIN_DIR}/uninstall.php"
		"${PLUGIN_DIR}/includes"
	)

	# Display full output (errors + warnings shown for review)
	set +e
	"${PHPCS}" --standard="${PLUGIN_DIR}/phpcs.xml" --severity=5 "${SCAN_TARGETS[@]}" 2>&1
	set -e

	# Fail only on ERRORS (warnings are advisory, not blocking)
	set +e
	"${PHPCS}" --standard="${PLUGIN_DIR}/phpcs.xml" --warning-severity=0 "${SCAN_TARGETS[@]}" > /dev/null 2>&1
	PHPCS_EXIT=$?
	set -e

	if [[ ${PHPCS_EXIT} -ne 0 ]]; then
		fail "PHPCS reported errors - fix them before packaging."
	fi
	success "PHPCS: 0 errors"
	echo ""
fi

# ---------------------------------------------------------------------------
# 2. WP Plugin Check
# ---------------------------------------------------------------------------
if [[ "${SKIP_CHECKS}" == false ]]; then
	info "Running WP Plugin Check..."

	if [[ ! -d "${WP_ROOT}" ]]; then
		warn "WP_ROOT not found (${WP_ROOT}) - skipping Plugin Check."
		warn "Set WP_ROOT=/path/to/wp-install to enable this step."
	else
		WP_CLI=$(command -v wp 2>/dev/null || true)
		[[ -z "${WP_CLI}" ]] && fail "wp-cli not found in PATH"

		set +e
		PC_OUTPUT=$(cd "${WP_ROOT}" && wp plugin check "${PLUGIN_SLUG}" \
			--exclude-directories='.claude,bin' \
			--exclude-files='.gitignore,.distignore' \
			2>/dev/null)
		set -e

		# Strip wp-cli deprecation noise
		CLEAN_OUTPUT=$(echo "${PC_OUTPUT}" | grep -v "^Deprecated:" | grep -v "Case statements" || true)

		# Real errors: ERROR lines not from the always-excluded dev files
		REAL_ERRORS=$(echo "${CLEAN_OUTPUT}" \
			| grep -E "^[[:space:]]+[0-9]+[[:space:]]+[0-9]+[[:space:]]+ERROR" \
			|| true)

		# Identify which files those errors belong to (strip .gitignore/.distignore)
		FILE_BLOCK=""
		CURRENT_FILE=""
		while IFS= read -r line; do
			if [[ "${line}" =~ ^FILE: ]]; then
				CURRENT_FILE="${line}"
			elif [[ "${line}" =~ ERROR ]]; then
				if [[ "${CURRENT_FILE}" != *".gitignore"* && "${CURRENT_FILE}" != *".distignore"* ]]; then
					FILE_BLOCK+="${CURRENT_FILE}"$'\n'"${line}"$'\n'
				fi
			fi
		done <<< "${CLEAN_OUTPUT}"

		if [[ -n "${REAL_ERRORS}" && -n "${FILE_BLOCK}" ]]; then
			echo "${CLEAN_OUTPUT}"
			fail "Plugin Check reported errors - fix them before packaging."
		fi

		echo "${CLEAN_OUTPUT}" | grep -v "^$" | head -60 || true
		success "Plugin Check: no blocking errors"
		echo ""
	fi
fi

[[ "${CHECK_ONLY}" == true ]] && { success "Checks complete - skipping ZIP (--check-only)."; exit 0; }

# ---------------------------------------------------------------------------
# 3. Build the ZIP
# ---------------------------------------------------------------------------
info "Building ${ZIP_NAME}..."

mkdir -p "${DIST_DIR}"

# Patterns to exclude from the ZIP - always-excluded dev/build files
EXCLUDES=()
ALWAYS_EXCLUDE=(
	".git/"
	".git/*"
	".claude/"
	".claude/*"
	".wordpress-org/"
	".wordpress-org/*"
	"bin/"
	"bin/*"
	"dist/"
	"dist/*"
	"vendor/"
	"vendor/*"
	"node_modules/"
	"node_modules/*"
	".gitignore"
	".distignore"
	"CLAUDE.md"
	"composer.json"
	"composer.lock"
	"phpcs.xml"
	"*.log"
	"*.log.*"
	".DS_Store"
	"Thumbs.db"
	"*.bak"
	"*.orig"
	"*.swp"
	"*.map"
)

for pattern in "${ALWAYS_EXCLUDE[@]}"; do
	EXCLUDES+=( --exclude="${PLUGIN_SLUG}/${pattern}" )
done

# Also read .distignore for any additional entries
if [[ -f "${PLUGIN_DIR}/.distignore" ]]; then
	while IFS= read -r dist_line || [[ -n "${dist_line}" ]]; do
		[[ -z "${dist_line}" || "${dist_line}" == \#* ]] && continue
		EXCLUDES+=( --exclude="${PLUGIN_SLUG}/${dist_line}" )
		EXCLUDES+=( --exclude="${PLUGIN_SLUG}/${dist_line}*" )
	done < "${PLUGIN_DIR}/.distignore"
fi

# Build from parent directory so the ZIP contains ariham-seoagent/ at root
cd "${PLUGIN_DIR}/.."
zip -r "${ZIP_PATH}" "${PLUGIN_SLUG}" -x "*/.DS_Store" "${EXCLUDES[@]}" > /dev/null

success "Created: dist/${ZIP_NAME}"
echo ""

# ---------------------------------------------------------------------------
# 4. Show ZIP contents summary
# ---------------------------------------------------------------------------
info "ZIP contents:"
unzip -l "${ZIP_PATH}" \
	| grep -v "^Archive\|^---\|files$" \
	| awk '{print $NF}' \
	| grep -v "^$" \
	| sort \
	| head -80
echo ""

FILECOUNT=$(unzip -l "${ZIP_PATH}" | tail -1 | awk '{print $2}')
ZIPSIZE=$(du -sh "${ZIP_PATH}" | cut -f1)
success "Done - ${FILECOUNT} files, ${ZIPSIZE} on disk -> dist/${ZIP_NAME}"
echo ""
