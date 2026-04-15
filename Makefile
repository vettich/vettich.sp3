# Сборка архива обновления (changes.zip). Список: изменения относительно UPDATE_BASE + неотслеживаемые.
#
# updater.php в архиве по умолчанию не добавляется «на всякий случай»:
#   • только если он в диффе (ручные правки), или
#   • если в диффе есть install/bitrix/ или install/db/mysql/update.sql — тогда подставляется минимальный шаблон (CopyFiles ± SQL);
#   • редкий полный сценарий с requirements_check: UPDATER_MODE=full (опционально WRITE_UPDATER=1 — записать в корень модуля).
#
# Примеры:
#   make update-version
#   make update-version UPDATE_BASE=v3.0.60
#   make list-update-files
#   UPDATER_MODE=none make update-version
#   UPDATER_MODE=full WRITE_UPDATER=1 make update-version
#   SKIP_UPDATER=1 make update-version

UPDATE_BASE ?=
UPDATE_ZIP ?= changes.zip
UPDATER_MODE ?= auto
export UPDATE_BASE UPDATE_ZIP UPDATER_MODE WRITE_UPDATER SKIP_UPDATER LIST_ONLY

update-version:
	@chmod +x scripts/build-bitrix-update.sh
	@./scripts/build-bitrix-update.sh

list-update-files:
	@chmod +x scripts/build-bitrix-update.sh
	@LIST_ONLY=1 ./scripts/build-bitrix-update.sh

.PHONY: update-version list-update-files
