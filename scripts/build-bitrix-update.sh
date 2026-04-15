#!/usr/bin/env bash
# Сборка архива обновления модуля Битрикс (корень = каталог модуля).
#
# updater.php в архиве (по умолчанию UPDATER_MODE=auto):
#   • Не включается, если в диффе только «обычный» код без install/bitrix и без update.sql.
#   • Включается из репозитория, если updater.php уже среди изменённых файлов (ручные правки).
#   • Иначе, если нужен сценарий обновления (в диффе есть install/bitrix/ или
#     install/db/mysql/update.sql), в архив подставляется минимальный шаблон (CopyFiles ± SQL),
#     без requirements_check.
#   UPDATER_MODE=full — полный шаблон с requirements_check (редкие релизы); при WRITE_UPDATER=1
#     записывается и в корень модуля.
#
#   SKIP_UPDATER=1 — не генерировать updater в архив; в архив попадёт только updater.php из диффа.
#
# Переменные: UPDATE_BASE, UPDATE_ZIP, UPDATER_MODE, WRITE_UPDATER, SKIP_UPDATER, LIST_ONLY.

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
MODULE_ROOT="$(cd "$SCRIPT_DIR/.." && pwd)"
cd "$MODULE_ROOT"

UPDATE_ZIP="${UPDATE_ZIP:-changes.zip}"
LIST_ONLY="${LIST_ONLY:-0}"
SKIP_UPDATER="${SKIP_UPDATER:-0}"
UPDATER_MODE="${UPDATER_MODE:-auto}"
WRITE_UPDATER="${WRITE_UPDATER:-0}"

default_base() {
	if [[ -n "${UPDATE_BASE:-}" ]]; then
		echo "$UPDATE_BASE"
		return
	fi
	local tag
	tag="$(git tag --list 'v*' --sort=-v:refname 2>/dev/null | head -n1 || true)"
	if [[ -n "$tag" ]]; then
		echo "$tag"
	else
		echo "HEAD"
	fi
}

BASE="$(default_base)"

should_exclude() {
	local f="$1"
	case "$f" in
		"") return 0 ;;
		dev/*|dev) return 0 ;;
		vendor/*|vendor) return 0 ;;
		scripts/*|scripts) return 0 ;;
		.git/*) return 0 ;;
		*.zip) return 0 ;;
		Makefile) return 0 ;;
		update-deleted-files.txt) return 0 ;;
		.gitignore|.nvim.lua|.phpactor.yml) return 0 ;;
		.vim/*|.cursor/*) return 0 ;;
		log.txt|log*.txt) return 0 ;;
		config.local.json|local_config.json|*.local.json) return 0 ;;
	esac
	[[ "$f" == changes.zip ]] && return 0
	return 1
}

collect_paths() {
	# Изменения рабочей копии и индекса относительно BASE (без удалённых: в архив их не кладём).
	# Важно: не использовать D в --diff-filter — иначе в список попадут пути удалённых файлов.
	git diff --name-only --diff-filter=ACMRT "$BASE" -- . 2>/dev/null || true
	# Новые файлы, ещё не в git.
	git ls-files --others --exclude-standard -- . 2>/dev/null || true
}

filter_paths() {
	while IFS= read -r line || [[ -n "$line" ]]; do
		[[ -z "$line" ]] && continue
		should_exclude "$line" && continue
		echo "$line"
	done < <(collect_paths | sort -u)
	echo "install/version.php"
}

needs_install_bitrix_in_diff() {
	local p
	while IFS= read -r p; do
		[[ "$p" == install/bitrix/* ]] && return 0
	done < <(collect_paths | sort -u)
	return 1
}

needs_update_sql_in_diff() {
	local p
	while IFS= read -r p; do
		[[ "$p" == "install/db/mysql/update.sql" ]] && return 0
	done < <(collect_paths | sort -u)
	return 1
}

updater_php_in_diff() {
	local p
	while IFS= read -r p; do
		[[ "$p" == "updater.php" ]] && return 0
	done < <(collect_paths | sort -u)
	return 1
}

# Какой вид сгенерированного updater нужен в архиве: пусто | minimal | full
decide_generated_kind() {
	if [[ "$SKIP_UPDATER" == "1" ]]; then
		echo ""
		return
	fi
	case "$UPDATER_MODE" in
		none)
			echo ""
			return
			;;
		full)
			echo "full"
			return
			;;
		auto)
			if needs_install_bitrix_in_diff || needs_update_sql_in_diff; then
				echo "minimal"
			else
				echo ""
			fi
			return
			;;
		*)
			echo ""
			return
			;;
	esac
}

write_updater_from_template() {
	local template="$1"
	local out="$2"
	local sql_frag="$SCRIPT_DIR/updater-sql-batch.php"
	if [[ ! -f "$template" ]]; then
		echo "Нет шаблона: $template" >&2
		exit 1
	fi
	if [[ -f "$MODULE_ROOT/install/db/mysql/update.sql" ]] && [[ -f "$sql_frag" ]]; then
		sed -e "/{{SQL_INCLUDE}}/r $sql_frag" -e '/{{SQL_INCLUDE}}/d' "$template" >"$out.tmp"
	else
		sed '/{{SQL_INCLUDE}}/d' "$template" >"$out.tmp"
	fi
	mv "$out.tmp" "$out"
}

append_generated_updater_to_zip() {
	local kind="$1"
	local tmpdir
	tmpdir="$(mktemp -d)"
	if [[ "$kind" == "full" ]]; then
		write_updater_from_template "$SCRIPT_DIR/updater-template.php" "$tmpdir/updater.php"
	else
		write_updater_from_template "$SCRIPT_DIR/updater-minimal-template.php" "$tmpdir/updater.php"
	fi
	(cd "$tmpdir" && zip -q "$MODULE_ROOT/$UPDATE_ZIP" updater.php)
	rm -rf "$tmpdir"
}

# --- main ---

if ! git rev-parse --is-inside-work-tree >/dev/null 2>&1; then
	echo "Запускайте из git-репозитория модуля." >&2
	exit 1
fi

GEN_KIND="$(decide_generated_kind)"

# Список файлов без updater.php (добавим отдельно).
mapfile -t PATHS_SORTED < <(filter_paths | sort -u)

declare -a FILES=()
local_missing=0
for f in "${PATHS_SORTED[@]}"; do
	[[ "$f" == "updater.php" ]] && continue
	if [[ -f "$f" ]]; then
		FILES+=("$f")
	else
		echo "Пропуск (нет файла): $f" >&2
		local_missing=1
	fi
done

# Решение по updater.php в архиве
REPO_UPDATER=0
FINAL_GEN=""

if [[ "$SKIP_UPDATER" == "1" ]]; then
	if updater_php_in_diff && [[ -f "updater.php" ]]; then
		REPO_UPDATER=1
		FILES+=("updater.php")
	fi
else
	if [[ "$UPDATER_MODE" == "full" ]]; then
		FINAL_GEN="full"
	elif updater_php_in_diff && [[ -f "updater.php" ]]; then
		# Ручной updater в диффе — в архив как есть; автогенерация не перебивает.
		REPO_UPDATER=1
		FILES+=("updater.php")
	elif [[ -n "$GEN_KIND" ]]; then
		FINAL_GEN="$GEN_KIND"
	fi
fi

# Убрать updater.php из FILES если генерируем (на случай дубликата)
if [[ -n "$FINAL_GEN" ]]; then
	declare -a CLEAN=()
	for x in "${FILES[@]}"; do
		[[ "$x" == "updater.php" ]] && continue
		CLEAN+=("$x")
	done
	FILES=("${CLEAN[@]}")
fi

# Запись updater.php в корень модуля (только full + WRITE_UPDATER)
if [[ "$FINAL_GEN" == "full" ]] && [[ "$WRITE_UPDATER" == "1" ]]; then
	write_updater_from_template "$SCRIPT_DIR/updater-template.php" "$MODULE_ROOT/updater.php"
	echo "Записан updater.php из полного шаблона (WRITE_UPDATER=1)." >&2
fi

if [[ ${#FILES[@]} -eq 0 ]] && [[ -z "$FINAL_GEN" ]] && [[ "$REPO_UPDATER" -eq 0 ]]; then
	echo "Нет файлов для архива. Проверьте UPDATE_BASE=$BASE и наличие изменений." >&2
	exit 1
fi

if [[ "$LIST_ONLY" == "1" ]]; then
	printf '%s\n' "${FILES[@]}"
	if [[ -n "$FINAL_GEN" ]]; then
		echo "# + updater.php (сгенерирован, $FINAL_GEN)"
	elif [[ "$REPO_UPDATER" -eq 1 ]]; then
		echo "# updater.php — в списке выше (из репозитория)"
	else
		echo "# updater.php — не входит в архив"
	fi
	exit 0
fi

rm -f "$UPDATE_ZIP"

if [[ ${#FILES[@]} -gt 0 ]]; then
	printf '%s\n' "${FILES[@]}" | zip -q "$UPDATE_ZIP" -@
fi

if [[ -n "$FINAL_GEN" ]]; then
	if [[ ! -f "$UPDATE_ZIP" ]]; then
		# только updater
		touch "$UPDATE_ZIP"
	fi
	append_generated_updater_to_zip "$FINAL_GEN"
fi

if [[ "$REPO_UPDATER" -eq 1 ]] && [[ ! -f "$UPDATE_ZIP" ]]; then
	printf '%s\n' "updater.php" | zip -q "$UPDATE_ZIP" -@
fi

# Если архив пустой по ошибке
if [[ ! -f "$UPDATE_ZIP" ]]; then
	echo "Не удалось создать архив." >&2
	exit 1
fi

n_extra=0
[[ -n "$FINAL_GEN" ]] && n_extra=1
echo "Архив: $MODULE_ROOT/$UPDATE_ZIP ($BASE → рабочая копия, файлов: $((${#FILES[@]} + n_extra)))"
if [[ -n "$FINAL_GEN" ]]; then
	echo "В архив добавлен сгенерированный updater.php ($FINAL_GEN)." >&2
elif [[ "$REPO_UPDATER" -eq 1 ]]; then
	echo "В архив включён updater.php из репозитория." >&2
else
	echo "updater.php в архив не включён (не требуется по диффу)." >&2
fi

if [[ "$local_missing" -eq 1 ]]; then
	echo "Предупреждение: часть путей из git отсутствует на диске — см. сообщения выше." >&2
fi

deleted="$(git diff --name-only --diff-filter=D "$BASE" -- . 2>/dev/null | sort -u || true)"
if [[ -n "$deleted" ]]; then
	manifest="$MODULE_ROOT/update-deleted-files.txt"
	printf '%s\n' "$deleted" >"$manifest"
	echo "Удалённые относительно $BASE: $manifest" >&2
fi
