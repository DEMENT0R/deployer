#!/usr/bin/env bash
#
# Дамп БД инстанса для шага backup панели деплоя. Панель запускает скрипт в каталоге
# инстанса, поэтому доступы берём из его .env, а имя подкаталога для дампов — из имени
# самого каталога инстанса (или из аргумента SLUG).
#
# usage: backup-db.sh [--root=DIR] [--keep=N] [SLUG]

set -euo pipefail

root=/var/backups/deployer
keep=10
slug=

for arg in "$@"; do
    case "$arg" in
        --root=*) root=${arg#*=} ;;
        --keep=*) keep=${arg#*=} ;;
        -*) echo "[backup] unknown option: $arg" >&2; exit 2 ;;
        *) slug=$arg ;;
    esac
done

[ -f .env ] || { echo "[backup] no .env in $PWD" >&2; exit 1; }

# .env разбираем сами, а не через `source`: там попадаются значения с пробелами, `#` и `$`,
# на которых шелл либо спотыкается (и роняет первый же шаг деплоя), либо исполняет их как код.
env_get() {
    local raw
    raw=$(sed -n "s/^[[:space:]]*$1=//p" .env | head -n1)

    case "$raw" in
        # Всё после закрывающей кавычки (и после ` #` у значения без кавычек) — комментарий.
        '"'*) raw=${raw#\"}; raw=${raw%%\"*} ;;
        "'"*) raw=${raw#\'}; raw=${raw%%\'*} ;;
        *) raw=${raw%%[[:space:]]#*} ;;
    esac

    printf '%s' "$raw" | sed 's/[[:space:]]*$//'
}

connection=$(env_get DB_CONNECTION)
case "${connection:-mysql}" in
    mysql | mariadb) ;;
    *) echo "[backup] DB_CONNECTION=$connection is not supported by this script" >&2; exit 1 ;;
esac

database=$(env_get DB_DATABASE)
[ -n "$database" ] || { echo "[backup] DB_DATABASE is empty in $PWD/.env" >&2; exit 1; }

if command -v mysqldump >/dev/null 2>&1; then
    dump=mysqldump
elif command -v mariadb-dump >/dev/null 2>&1; then
    dump=mariadb-dump
else
    echo "[backup] neither mysqldump nor mariadb-dump is installed" >&2
    exit 1
fi

host=$(env_get DB_HOST); host=${host:-127.0.0.1}
port=$(env_get DB_PORT); port=${port:-3306}
user=$(env_get DB_USERNAME)
password=$(env_get DB_PASSWORD)

destination="$root/${slug:-$(basename "$PWD")}"
mkdir -p "$destination"
chmod 700 "$destination"

# Пароль уходит во временный файл, а не в аргументы: аргументы видит `ps` любой на хосте.
config=$(mktemp)
trap 'rm -f "$config"' EXIT
printf '[client]\nhost=%s\nport=%s\nuser=%s\npassword="%s"\n' \
    "$host" "$port" "$user" "$password" > "$config"

file="$destination/${database}_$(date +%F_%H%M%S).sql.gz"

# Пишем в .part и переименовываем после успеха: оборванный дамп не должен выглядеть готовым.
# Ради этого же включён pipefail — иначе кодом возврата стал бы код gzip, и упавший
# mysqldump оставил бы обрезанный архив при зелёном шаге деплоя.
#
# --events намеренно нет: событий в Laravel-проектах почти не бывает, а привилегии EVENT
# у пользователя стенда обычно тоже, и дамп падал бы на ровном месте.
"$dump" --defaults-extra-file="$config" \
    --single-transaction --quick --no-tablespaces \
    --routines --triggers --default-character-set=utf8mb4 \
    "$database" | gzip -1 > "$file.part"

mv "$file.part" "$file"
chmod 600 "$file"
echo "[backup] $file ($(du -h "$file" | cut -f1))"

# Дамп нужен свежий — старые только занимают диск стенда, причём незаметно.
if [ "$keep" -gt 0 ]; then
    ls -1t "$destination"/*.sql.gz | tail -n +$((keep + 1)) | xargs -r rm -f || true
fi
