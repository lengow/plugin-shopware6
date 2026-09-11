#!/usr/bin/env bash

set -Eeuo pipefail
IFS=$'\n\t'
umask 022

readonly PACKAGE_DIRECTORY='LengowConnector'
readonly ARCHIVE_PREFIX='lengow.shopware6'
readonly ADMINISTRATION_SOURCE='src/Resources/app/administration/src'
readonly ADMINISTRATION_OUTPUT='src/Resources/public/administration'
readonly SCRIPT_DIRECTORY="$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")" && pwd -P)"
readonly REPOSITORY_ROOT="$(cd -- "${SCRIPT_DIRECTORY}/.." && pwd -P)"

WORKSPACE=''

usage()
{
    cat <<'EOF'
Usage:
  tools/build.sh <version> [--output <directory>]

Build a Shopware plugin archive containing only composer.json and src/.
The default output directory is <repository>/dist. Relative output paths are
resolved from the repository root.
EOF
}

die()
{
    printf 'Error: %s\n' "$*" >&2
    exit 1
}

cleanup()
{
    if [[ -n "${WORKSPACE}" && -d "${WORKSPACE}" ]]; then
        rm -rf -- "${WORKSPACE}"
    fi
}

require_command()
{
    command -v "$1" >/dev/null 2>&1 || die "Missing required command: $1"
}

validate_version()
{
    local version="$1"

    [[ "${version}" =~ ^[0-9]+([.][0-9]+){0,2}([-+][0-9A-Za-z][0-9A-Za-z.-]*)?$ ]] \
        || die "Invalid version: ${version}"
}

resolve_output_directory()
{
    local output_directory="$1"

    if [[ "${output_directory}" != /* ]]; then
        output_directory="${REPOSITORY_ROOT}/${output_directory}"
    fi

    mkdir -p -- "${output_directory}"
    cd -- "${output_directory}" && pwd -P
}

validate_administration_build()
{
    local source_directory="${REPOSITORY_ROOT}/${ADMINISTRATION_SOURCE}"
    local output_directory="${REPOSITORY_ROOT}/${ADMINISTRATION_OUTPUT}"
    local legacy_bundle="${output_directory}/js/lengow-connector.js"
    local vite_manifest="${output_directory}/.vite/entrypoints.json"
    local vite_bundle
    local bundle
    local newer

    [[ -d "${source_directory}" ]] || die 'Missing administration sources'

    # Shopware resolves the administration bundle differently depending on the major:
    #   6.6 loads administration/js/<plugin>.js by convention
    #   6.7 reads administration/.vite/entrypoints.json and loads assets/<plugin>-<hash>.js
    # Each major ignores the other output, so a single archive carries both. Producing
    # them means running the administration build once per major and keeping both
    # results side by side: a build overwrites only its own format.
    [[ -f "${legacy_bundle}" ]] \
        || die "Missing the 6.6 administration bundle (${ADMINISTRATION_OUTPUT}/js/): run the administration build on Shopware 6.6"

    [[ -f "${vite_manifest}" ]] \
        || die "Missing the 6.7 administration manifest (${ADMINISTRATION_OUTPUT}/.vite/): run the administration build on Shopware 6.7"

    vite_bundle="$(find "${output_directory}/assets" -maxdepth 1 -name '*.js' ! -name '*.map' -print -quit 2>/dev/null || true)"
    [[ -n "${vite_bundle}" ]] \
        || die "Missing the 6.7 administration bundle (${ADMINISTRATION_OUTPUT}/assets/): run the administration build on Shopware 6.7"

    # An administration source newer than a built bundle means the archive would ship
    # compiled code that does not match the sources it is built from.
    for bundle in "${legacy_bundle}" "${vite_bundle}"; do
        newer="$(find "${source_directory}" -type f -newer "${bundle}" -print -quit)"
        [[ -z "${newer}" ]] \
            || die "Administration bundle is stale (${newer#"${REPOSITORY_ROOT}/"} is newer than ${bundle#"${REPOSITORY_ROOT}/"}): rebuild the administration"
    done
}

validate_archive()
{
    local archive_path="$1"
    local archive_contents="${WORKSPACE}/archive-contents.txt"
    local entry

    unzip -Z1 "${archive_path}" > "${archive_contents}"

    if ! grep -Fxq "${PACKAGE_DIRECTORY}/composer.json" "${archive_contents}"; then
        die 'Archive is missing composer.json'
    fi

    if ! grep -Fxq "${PACKAGE_DIRECTORY}/src/LengowConnector.php" "${archive_contents}"; then
        die 'Archive is missing the plugin entry point'
    fi

    if ! grep -Fxq "${PACKAGE_DIRECTORY}/src/Config/checkmd5.csv" "${archive_contents}"; then
        die 'Archive is missing generated checksums'
    fi

    if ! grep -Fxq "${PACKAGE_DIRECTORY}/src/Translations/en-GB.csv" "${archive_contents}"; then
        die 'Archive is missing generated translations'
    fi

    if ! grep -Fxq "${PACKAGE_DIRECTORY}/${ADMINISTRATION_OUTPUT}/js/lengow-connector.js" "${archive_contents}"; then
        die 'Archive is missing the 6.6 administration bundle'
    fi

    if ! grep -Fxq "${PACKAGE_DIRECTORY}/${ADMINISTRATION_OUTPUT}/.vite/entrypoints.json" "${archive_contents}"; then
        die 'Archive is missing the 6.7 administration manifest'
    fi

    while IFS= read -r entry; do
        case "${entry}" in
            "${PACKAGE_DIRECTORY}/")
                continue
                ;;
            "${PACKAGE_DIRECTORY}/composer.json"|"${PACKAGE_DIRECTORY}/src/"*)
                ;;
            *)
                die "Archive contains an unexpected root entry: ${entry}"
                ;;
        esac

        case "${entry}" in
            */.DS_Store|*/._*|*/.Spotlight-V100/*|*/.Trashes/*|*/Thumbs.db|*/Desktop.ini|*/ehthumbs.db|*/__MACOSX/*)
                die "Archive contains operating-system metadata: ${entry}"
                ;;
            *.map)
                die "Archive contains a source map: ${entry}"
                ;;
            "${PACKAGE_DIRECTORY}/src/Config/marketplaces.json")
                die 'Archive contains local marketplace configuration'
                ;;
            "${PACKAGE_DIRECTORY}/src/Translations/yml/"*)
                die "Archive contains translation source: ${entry}"
                ;;
            "${PACKAGE_DIRECTORY}/src/Logs/"|"${PACKAGE_DIRECTORY}/src/Logs/index.php")
                ;;
            "${PACKAGE_DIRECTORY}/src/Logs/"*)
                die "Archive contains runtime log data: ${entry}"
                ;;
            "${PACKAGE_DIRECTORY}/src/Export/"|"${PACKAGE_DIRECTORY}/src/Export/index.php")
                ;;
            "${PACKAGE_DIRECTORY}/src/Export/"*)
                die "Archive contains export data: ${entry}"
                ;;
        esac
    done < "${archive_contents}"
}

main()
{
    local version
    local output_directory="${REPOSITORY_ROOT}/dist"
    local archive_name
    local archive_path
    local temporary_archive
    local staging_root

    if [[ "${1:-}" == '--help' || "${1:-}" == '-h' ]]; then
        usage
        return 0
    fi

    [[ $# -ge 1 ]] || {
        usage >&2
        die 'Version parameter is required'
    }

    version="$1"
    shift
    validate_version "${version}"

    while [[ $# -gt 0 ]]; do
        case "$1" in
            --output)
                [[ $# -ge 2 ]] || die 'Missing directory after --output'
                output_directory="$2"
                shift 2
                ;;
            --output=*)
                output_directory="${1#--output=}"
                [[ -n "${output_directory}" ]] || die 'Missing directory after --output='
                shift
                ;;
            *)
                die "Unknown option: $1"
                ;;
        esac
    done

    [[ -f "${REPOSITORY_ROOT}/composer.json" ]] || die 'Missing composer.json'
    [[ -d "${REPOSITORY_ROOT}/src" ]] || die 'Missing src directory'
    [[ -f "${REPOSITORY_ROOT}/tools/translate.php" ]] || die 'Missing translation generator'
    [[ -f "${REPOSITORY_ROOT}/tools/checkmd5.php" ]] || die 'Missing checksum generator'

    validate_administration_build

    require_command php
    require_command rsync
    require_command unzip
    require_command zip
    php -r 'exit(function_exists("yaml_parse_file") ? 0 : 1);' \
        || die 'The PHP YAML extension is required'

    output_directory="$(resolve_output_directory "${output_directory}")"
    case "${output_directory}" in
        "${REPOSITORY_ROOT}/src"|"${REPOSITORY_ROOT}/src/"*)
            die 'Output directory must not be inside src'
            ;;
    esac
    archive_name="${ARCHIVE_PREFIX}.${version}.zip"
    archive_path="${output_directory}/${archive_name}"

    WORKSPACE="$(mktemp -d "${TMPDIR:-/tmp}/lengow-shopware6.XXXXXX")"
    trap cleanup EXIT
    staging_root="${WORKSPACE}/${PACKAGE_DIRECTORY}"
    temporary_archive="${WORKSPACE}/${archive_name}"

    mkdir -p -- "${staging_root}"
    rsync -a -- "${REPOSITORY_ROOT}/composer.json" "${staging_root}/"

    # Keep only runtime placeholders in the local log and export directories.
    rsync -a -m \
        --include='/Logs/' \
        --include='/Logs/index.php' \
        --exclude='/Logs/***' \
        --include='/Export/' \
        --include='/Export/index.php' \
        --exclude='/Export/***' \
        --exclude='/Config/marketplaces.json' \
        --exclude='*.map' \
        --exclude='.DS_Store' \
        --exclude='._*' \
        --exclude='.Spotlight-V100/***' \
        --exclude='.Trashes/***' \
        --exclude='Thumbs.db' \
        --exclude='Desktop.ini' \
        --exclude='ehthumbs.db' \
        --exclude='__MACOSX/***' \
        -- "${REPOSITORY_ROOT}/src/" "${staging_root}/src/"

    mkdir -p -- "${staging_root}/tools"
    cp -- "${REPOSITORY_ROOT}/tools/translate.php" "${REPOSITORY_ROOT}/tools/checkmd5.php" "${staging_root}/tools/"
    php "${staging_root}/tools/translate.php"
    php "${staging_root}/tools/checkmd5.php"
    rm -rf -- "${staging_root}/tools" "${staging_root}/src/Translations/yml"

    (
        cd -- "${WORKSPACE}"
        zip -qr "${temporary_archive}" "${PACKAGE_DIRECTORY}"
    )
    validate_archive "${temporary_archive}"
    mv -f -- "${temporary_archive}" "${archive_path}"

    printf 'Archive created: %s\n' "${archive_path}"
}

main "$@"
