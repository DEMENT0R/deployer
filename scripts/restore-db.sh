#!/usr/bin/env bash
#
# Восстановление БД инстанса из дампа, снятого backup-db.sh. Панель запускает скрипт
# в каталоге инстанса — доступы берём из его .env, как и при бэкапе.
#
# Перед накатом снимается свежий дамп: восстановление затирает базу целиком, и если
# выяснится, что взяли не тот файл, вернуться будет уже некуда.
#
# usage: restore-db.sh --file=DUMP [--root=DIR] [--slug=NAME] [--keep=N]

set -euo pipefail

file=
root=/var/backups/deployer
slug=
keep=10

for arg in "$@"; do
    case "$arg" in
        --file=*) file=${arg#*=} ;;
        --root=*) root=${arg#*=} ;;
        --slug=*) slug=${arg#*=} ;;
        --keep=*) keep=${arg#*=} ;;
        *) echo "[restore] unknown option: $arg" >&2; exit 2 ;;
    esac
done

[ -n "$file" ] || { echo "[restore] --file is required" >&2; exit 2; }
[ -f "$file" ] || { echo "[restore] no such dump: $file" >&2; exit 1; }
[ -f .env ] || { echo "[restore] no .env in $PWD" >&2; exit 1; }

env_get() {
    local raw
    raw=$(sed -n "s/^[[:space:]]*$1=//p" .env | head -n1)

    case "$raw" in
        '"'*) raw=${raw#\"}; raw=${raw%%\"*} ;;
        "'"*) raw=${raw#\'}; raw=${raw%%\'*} ;;
        *) raw=${raw%%[[:space:]]#*} ;;
    esac

    printf '%s' "$raw" | sed 's/[[:space:]]*$//'
}

connection=$(env_get DB_CONNECTION)
case "${connection:-mysql}" in
    mysql | mariadb) ;;
    *) echo "[restore] DB_CONNECTION=$connection is not supported by this script" >&2; exit 1 ;;
esac

database=$(env_get DB_DATABASE)
[ -n "$database" ] || { echo "[restore] DB_DATABASE is empty in $PWD/.env" >&2; exit 1; }

if command -v mysql >/dev/null 2>&1; then
    client=mysql
elif command -v mariadb >/dev/null 2>&1; then
    client=mariadb
else
    echo "[restore] neither mysql nor mariadb client is installed" >&2
    exit 1
fi

# Страховочный дамп — тем же скриптом, что и обычный бэкап, и в тот же каталог.
echo "[restore] taking a safety dump of $database first"
bash "$(dirname "$0")/backup-db.sh" --root="$root" --keep="$keep" ${slug:+"$slug"}

host=$(env_get DB_HOST); host=${host:-127.0.0.1}
port=$(env_get DB_PORT); port=${port:-3306}
user=$(env_get DB_USERNAME)
password=$(env_get DB_PASSWORD)

config=$(mktemp)
trap 'rm -f "$config"' EXIT
printf '[client]\nhost=%s\nport=%s\nuser=%s\npassword="%s"\n' \
    "$host" "$port" "$user" "$password" > "$config"

echo "[restore] restoring $database from $file"
gunzip -c "$file" | "$client" --defaults-extra-file="$config" \
    --default-character-set=utf8mb4 "$database"

echo "[restore] $database restored from $(basename "$file")"
