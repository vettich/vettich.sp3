---
name: bitrix-marketplace-release
description: Собирает релизный пакет Bitrix-модуля для Marketplace по указанной версии (по умолчанию из install/version.php). Делает строго diff <PREV_VERSION>..<VERSION> (или <PREV_VERSION>..HEAD, если тега <VERSION> ещё нет), генерирует description.ru на русском в человеческих формулировках (без технических деталей реализации), генерирует updater.php (SQL-апдейт по diff install.sql/local_queue.sql инлайнится в updater.php; отдельный update.sql в zip не включается), затем создаёт zip обновления versions/<VERSION>/<VERSION>.zip (корень архива <VERSION>/). Опционально собирает полную сборку .last_version.zip.
---

# bitrix-marketplace-release

## Когда использовать

Нужно подготовить релизный архив модуля `vettich.sp3` для загрузки в Bitrix Marketplace:
- **по умолчанию** — архив обновления `versions/<VERSION>/<VERSION>.zip`;
- **опционально** — полная сборка `.last_version.zip` (по запросу разработчика).

## Входные параметры

Необязательные:
- `VERSION` (например `3.1.3`) — целевая версия релиза. Если не передана, берётся из `install/version.php`.
- `UPDATE_TARGET` (по умолчанию `HEAD`)
- `full=1` — собрать дополнительно `.last_version.zip`

## Источник версии для имени релиза

Версия релиза (`<VERSION>`) определяется так:
- если передан параметр `VERSION` — используем его;
- иначе читаем `$arModuleVersion['VERSION']` из `install/version.php`.

## Как определяется diff (строго, без расширений)

Skill определяет целевой снапшот так:
- если в git есть тег `v<VERSION>` — берём его (`v<VERSION>`);
- иначе берём `HEAD`.

Далее skill делает diff **только** между:
- `v<PREV_VERSION>` и
- выбранным целевым снапшотом (`v<VERSION>` или `HEAD`).

В diff не должны попадать файлы из рабочей копии, если целевой снапшот уже является тегом.

## Определение PREV_VERSION

`PREV_VERSION` выбирается по git-тегам `v*` как предыдущая версия (обычно максимальный тег по semver, отличный от `v<VERSION>`).

## Обязательная проверка (без догадок)

Перед сборкой skill обязан проверить:
1) значение `VERSION` в `install/version.php` на целевом снапшоте (в `v<VERSION>` или на `HEAD`, если тега нет) равно выбранной `<VERSION>`;
2) если `PREV_VERSION` выбран по тегу `v<PREV_VERSION>`, то значение `VERSION` в `install/version.php` на `PREV_VERSION` соответствует `<PREV_VERSION>`.

Если проверка не проходит — сборка останавливается с понятной ошибкой.

## Что попадает в zip обновления (важно)

Для `versions/<VERSION>/<VERSION>.zip`:
- корень архива = `<VERSION>/`
- внутри должны быть **только**:
  - `<VERSION>/install/version.php`
  - `<VERSION>/description.ru`
  - `<VERSION>/updater.php`
  - и **все изменённые файлы** из diff (после применения исключений `should_exclude` из `scripts/build-bitrix-update.sh`)

**Запрещено** включать в zip:
- `install/db/mysql/update.sql` или любые другие временные SQL файлы;
- файлы, которые не являются частью релизной сборки.

SQL-апдейт (если нужен) должен быть инлайнен в `updater.php`.

## Генерация `description.ru` (русский, человеческие формулировки)

`description.ru` формируется автоматически по изменениям diff и сообщениям коммитов:
- на **русском языке**;
- только буллеты в формате `<ul><li>...</li></ul>`;
- формулировки должны быть “человеческими”, без технических деталей реализации (без упоминания файлов/классов/функций/полей/констант);
- **по одному буллету на заметную пользовательскую область** (например: “страница постов”, “шаблоны”, “публикация/обновление постов”, “настройки/подключение” и т.п.);
- если изменение в основном UI (css/js/php-шаблоны/admin-страницы), допустимо обобщение:
  - `Небольшие правки ui`
  - `Мелкие ui улучшения`
  - при очевидном scope: `Улучшение ui в списке постов`

### Как определять “пользовательскую область”

Skill должен использовать:
- `git log --format='%s' v<PREV_VERSION>..<TARGET>` как подсказку;
- `git diff --name-only v<PREV_VERSION>..<TARGET>` для определения scope по путям файлов;
- при необходимости `git diff` по ключевым файлам, чтобы понять, что именно изменилось для пользователя.

Если scope неочевиден — писать без scope (просто “улучшения/исправления”).

## Генерация `updater.php`

### Копирование файлов из `install/bitrix/*`

Если в diff попали файлы внутри `install/bitrix/<subdir>/...`, skill добавляет в `updater.php`:
- `$updater->CopyFiles("install/bitrix/<subdir>", "<subdir>");`

Это делается для **всех подкаталогов** `install/bitrix/*`, затронутых изменениями.

### SQL апдейт из diff install.sql/local_queue.sql

SQL генерируется автоматически по diff следующих файлов:
- `install/db/mysql/install.sql`
- `install/db/mysql/local_queue.sql`

Если изменения затрагивают структуру БД (CREATE/ALTER/индексы/поля/таблицы), skill должен:
- сгенерировать необходимый SQL,
- вставить его в `updater.php` и выполнить при установке обновления (например через `$DB->RunSQLBatch()` или эквивалентную логику),
- **не** выкладывать отдельный `update.sql` в архив.

Изменения `install/db/mysql/uninstall.sql` не должны приводить к генерации SQL апдейта.

### Удаление файлов

Удаление файлов из diff в `updater.php` пока не генерируем (по текущему решению).

## Сборка полной сборки (опционально, `full=1`)

Если `full=1`, skill дополнительно собирает `.last_version.zip`:
- `.last_version/` должна содержать все файлы модуля на состояние `UPDATE_TARGET` (обычно `HEAD` или тег `v<VERSION>`), кроме тех, что исключает `should_exclude` из `scripts/build-bitrix-update.sh`
- архив `.last_version.zip` должен иметь корень `.last_version/`

## Порядок действий skill (workflow)

1) Определить `<VERSION>` (параметр `VERSION` или из `install/version.php`).
2) Найти `PREV_VERSION` по тегам `v*` (предыдущая версия).
3) Определить целевой снапшот:
   - если существует тег `v<VERSION>` → `TARGET=v<VERSION>`
   - иначе → `TARGET=HEAD`
4) Сформировать diff `v<PREV_VERSION>..<TARGET>` (строго этот диапазон).
5) Проверить, что `install/version.php` на целевом снапшоте равен `<VERSION>`.
6) Собрать список изменённых файлов (после `should_exclude`).
7) Сгенерировать `description.ru` на русском и `updater.php` (включая SQL по diff install.sql/local_queue.sql, если нужно).
8) Создать `versions/<VERSION>/` и заполнить её содержимым.
9) Упаковать `versions/<VERSION>/<VERSION>.zip` с корнем `<VERSION>/`.
10) Если `full=1` — собрать `.last_version.zip` по правилам выше.
11) Вернуть краткий отчёт: пути к архивам, был ли SQL апдейт, какие файлы вошли.

## Требования к отчёту

В ответе skill должен указать:
- `VERSION` (итоговая выбранная версия)
- `PREV_VERSION`
- `TARGET` (или диапазон diff)
- путь к `versions/<VERSION>/<VERSION>.zip`
- был ли сгенерирован SQL апдейт (и по каким таблицам/файлам)
- был ли собран `.last_version.zip` (если `full=1`)
