#!/bin/sh
set -eu
project=$(CDPATH= cd -- "$(dirname -- "$0")/.." && pwd)
mkdir -p "$project/tests/.tmp"
test_root=$(mktemp -d "$project/tests/.tmp/tape-room-preview-XXXXXXXX")
mkdir -p "$test_root/sessions" "$test_root/uploads" "$test_root/php-tmp"
server_pid=''
cleanup() {
    if [ -n "$server_pid" ]; then
        pkill -TERM -P "$server_pid" 2>/dev/null || true
        kill "$server_pid" 2>/dev/null || true
        wait "$server_pid" 2>/dev/null || true
    fi
    rm -rf -- "$test_root"
}
trap cleanup EXIT
trap 'exit 130' INT
trap 'exit 143' TERM
cd "$project"
export REEL_PLAYER_TEST_ROOT="$test_root"
export PHP_CLI_SERVER_WORKERS="${PHP_CLI_SERVER_WORKERS:-4}"
printf 'Tape Room temporary data: %s\n' "$test_root"
php -d sys_temp_dir="$test_root/php-tmp" -d session.save_path="$test_root/sessions" \
    -d upload_tmp_dir="$test_root/uploads" -d upload_max_filesize=50M -d post_max_size=55M \
    -S 127.0.0.1:8897 plugins/reel-player/tests/browser-fixture.php >"$test_root/server.log" 2>&1 &
server_pid=$!
printf 'Tape Room preview: http://127.0.0.1:8897/admin/reel-player\n'
wait "$server_pid"
