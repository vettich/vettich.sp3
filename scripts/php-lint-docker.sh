#!/usr/bin/env bash
# Синтаксическая проверка PHP-файлов модуля в официальных образах php:*-cli.
#
# По умолчанию: 7.4, 8.1, 8.2, 8.3, 8.4, 8.5 (без 8.0).
# Переопределение: PHP_VERSIONS="8.1 8.3" ./scripts/php-lint-docker.sh
#                  PHP_VERSIONS=8.1 make lint
#
# Исключения: vendor/, .git/, dev/

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
MODULE_ROOT="$(cd "$SCRIPT_DIR/.." && pwd)"
cd "$MODULE_ROOT"

if [[ -z "${PHP_VERSIONS:-}" ]]; then
	PHP_VERSIONS="7.4 8.1 8.2 8.3 8.4 8.5"
fi

if ! command -v docker >/dev/null 2>&1; then
	echo "docker не найден в PATH" >&2
	exit 1
fi

ok_versions=()
fail_versions=()

lint_version() {
	local ver="$1"
	# bash: process substitution, иначе fail внутри pipe-while не всплывает.
	# php -l принимает только первый файл — по одному.
	docker run --rm \
		-v "$MODULE_ROOT":/app \
		-w /app \
		"php:${ver}-cli" \
		bash -c '
			fail=0
			while IFS= read -r -d "" f; do
				if ! out=$(php -l "$f" 2>&1); then
					printf "%s\n" "$out"
					fail=1
				fi
			done < <(find . \( -path "./vendor/*" -o -path "./.git/*" -o -path "./dev/*" \) -prune -o -name "*.php" -type f -print0)
			exit "$fail"
		'
}

for ver in $PHP_VERSIONS; do
	echo "=== PHP ${ver} (php:${ver}-cli) ==="
	if lint_version "$ver"; then
		echo "OK"
		ok_versions+=("$ver")
	else
		echo "FAIL"
		fail_versions+=("$ver")
	fi
	echo
done

echo "=== summary ==="
if ((${#ok_versions[@]} > 0)); then
	for ver in "${ok_versions[@]}"; do
		echo "  OK   PHP $ver"
	done
fi
if ((${#fail_versions[@]} > 0)); then
	for ver in "${fail_versions[@]}"; do
		echo "  FAIL PHP $ver"
	done
	exit 1
fi
