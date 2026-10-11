#!/bin/sh
# Isolated MySQL integration suite. It never connects to the configured CMS database.
set -eu
widget_root=$(CDPATH= cd -- "$(dirname -- "$0")/.." && pwd)
widget_mysqld=${MYSQLD_BIN:-/Applications/MAMP/Library/bin/mysqld}
if [ ! -x "$widget_mysqld" ]; then widget_mysqld=$(command -v mysqld); fi
mkdir -p "$widget_root/tests/.tmp" "$widget_root/tests/artifacts/widgets"
widget_dir=$(mktemp -d "$widget_root/tests/.tmp/widgets-XXXXXX")
widget_pid=''
cleanup() {
    if [ -n "$widget_pid" ]; then kill "$widget_pid" 2>/dev/null || true; wait "$widget_pid" 2>/dev/null || true; fi
    rm -rf "$widget_dir"
}
trap cleanup EXIT INT TERM
mkdir -p "$widget_dir/mysql" "$widget_dir/mysql-tmp" "$widget_dir/cache" "$widget_dir/storage" "$widget_dir/sessions"
"$widget_mysqld" --no-defaults --initialize-insecure --datadir="$widget_dir/mysql" --log-error="$widget_dir/mysql-init.log"
"$widget_mysqld" --no-defaults --datadir="$widget_dir/mysql" --tmpdir="$widget_dir/mysql-tmp" --socket="$widget_dir/mysql.sock" --pid-file="$widget_dir/mysql.pid" --log-error="$widget_dir/mysql.log" --skip-networking &
widget_pid=$!
widget_attempt=0
while [ ! -S "$widget_dir/mysql.sock" ]; do
    widget_attempt=$((widget_attempt + 1))
    if [ "$widget_attempt" -gt 100 ] || ! kill -0 "$widget_pid" 2>/dev/null; then cat "$widget_dir/mysql.log"; exit 1; fi
    sleep 0.1
done
WIDGET_TEST_DIR="$widget_dir" php "$widget_root/tests/widgets.php"
