#!/usr/bin/env bash

set -Eeuo pipefail
IFS=$'\n\t'
umask 022

readonly PACKAGE_DIRECTORY='LengowConnector'
readonly ARCHIVE_PREFIX='lengow.shopware6'
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

warn()
{
    printf 'Warning: %s\n' "$*" >&2
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

is_expected_root_entry()
{
    local entry="$1"
    local expected_entry
    local -a expected_entries=(
        '.agent'
        '.agent-kit'
        '.claude'
        '.codex'
        '.gemini'
        '.git'
        '.gitattributes'
        '.github'
        '.gitignore'
        '.idea'
        '.openai'
        '.opencode'
        '.skills'
        '.vscode'
        'AGENTS.md'
        'CHANGELOG.md'
        'CHANGELOG_de-DE.md'
        'CLAUDE.md'
        'COPILOT.md'
        'GEMINI.md'
        'Jenkinsfile'
        'LICENCE.md'
        'README.md'
        'bin'
        'composer.json'
        'dist'
        'dod.md'
        'node_modules'
        'package-lock.json'
        'phpunit.xml.dist'
        'project-context.md'
        'scripts'
        'src'
        'tests'
        'tools'
    )

    for expected_entry in "${expected_entries[@]}"; do
        if [[ "${entry}" == "${expected_entry}" ]]; then
            return 0
        fi
    done

    return 1
}

audit_root_entries()
{
    local path
    local entry

    while IFS= read -r -d '' path; do
        entry="${path##*/}"
        case "${entry}" in
            .DS_Store|._*|.Spotlight-V100|.Trashes|Thumbs.db|Desktop.ini|ehthumbs.db|__MACOSX)
                continue
                ;;
        esac
        if ! is_expected_root_entry "${entry}"; then
            warn "unexpected project-root entry excluded: ${entry}"
        fi
    done < <(find "${REPOSITORY_ROOT}" -mindepth 1 -maxdepth 1 -print0)
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

    require_command php
    require_command rsync
    require_command unzip
    require_command zip
    php -r 'exit(function_exists("yaml_parse_file") ? 0 : 1);' \
        || die 'The PHP YAML extension is required'

    audit_root_entries

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
