#!/usr/bin/env bash
# Local AssetTrack stack for tests, demo and screenshots (Linux/macOS/WSL):
# a throw-away MariaDB on port 13306 + PHP's built-in web server on port 8080.
#
#   tools/dev/stack.sh start   # start DB + web server, seed the demo school
#   tools/dev/stack.sh seed    # re-seed the demo database
#   tools/dev/stack.sh stop
#
# Never touches includes/config.php: the app reads tests/.tmp/config.php via
# the ASSETTRACK_CONFIG environment variable.
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/../.." && pwd)"
TMP="$ROOT/tests/.tmp"
DB_PORT="${AT_DB_PORT:-13306}"
WEB_PORT="${AT_WEB_PORT:-8080}"
DB_NAME="${AT_DB_NAME:-assettrack_demo}"
export ASSETTRACK_CONFIG="$TMP/config.php"

write_config() {
  mkdir -p "$TMP"
  cat > "$TMP/config.php" <<PHP
<?php
define('DB_HOST', '127.0.0.1;port=$DB_PORT');
define('DB_NAME', '$DB_NAME');
define('DB_USER', 'assettrack');
define('DB_PASS', 'assettrack');
define('BASE_URL', '');
define('INSTALLED', true);
define('DEMO_MODE', ${AT_DEMO_MODE:-true});
PHP
}

start_db() {
  if mariadb-admin --protocol=tcp -h127.0.0.1 -P"$DB_PORT" -uroot ping >/dev/null 2>&1; then return; fi
  if [ ! -d "$TMP/mysql" ]; then
    mkdir -p "$TMP/mysql"
    mariadb-install-db --no-defaults --datadir="$TMP/mysql" --auth-root-authentication-method=normal >/dev/null
  fi
  nohup mariadbd --no-defaults --datadir="$TMP/mysql" --port="$DB_PORT" --bind-address=127.0.0.1 \
    --socket="$TMP/mysql.sock" --user="$(id -un)" --character-set-server=utf8mb4 \
    --collation-server=utf8mb4_unicode_ci > "$TMP/mariadb.log" 2>&1 < /dev/null &
  for _ in $(seq 1 40); do
    mariadb-admin --protocol=tcp -h127.0.0.1 -P"$DB_PORT" -uroot ping >/dev/null 2>&1 && break
    sleep 0.5
  done
  mariadb --protocol=tcp -h127.0.0.1 -P"$DB_PORT" -uroot -e "
    CREATE DATABASE IF NOT EXISTS \`$DB_NAME\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
    CREATE DATABASE IF NOT EXISTS assettrack_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
    DELETE FROM mysql.global_priv WHERE User='';
    FLUSH PRIVILEGES;
    CREATE USER IF NOT EXISTS 'assettrack'@'localhost' IDENTIFIED BY 'assettrack';
    CREATE USER IF NOT EXISTS 'assettrack'@'127.0.0.1' IDENTIFIED BY 'assettrack';
    GRANT ALL ON \`$DB_NAME\`.* TO 'assettrack'@'localhost', 'assettrack'@'127.0.0.1';
    GRANT ALL ON assettrack_test.* TO 'assettrack'@'localhost', 'assettrack'@'127.0.0.1';"
}

start_web() {
  if curl -s -o /dev/null "http://127.0.0.1:$WEB_PORT/index.php"; then return; fi
  (cd "$ROOT" && nohup env ASSETTRACK_CONFIG="$ASSETTRACK_CONFIG" php -S 127.0.0.1:"$WEB_PORT" \
     > "$TMP/php.log" 2>&1 < /dev/null &)
  sleep 1
}

case "${1:-start}" in
  start) write_config; start_db; php "$ROOT/tools/demo/seed.php" --force; start_web
         echo "AssetTrack demo: http://127.0.0.1:$WEB_PORT  (demo / demo1234)";;
  seed)  write_config; php "$ROOT/tools/demo/seed.php" --force;;
  db)    write_config; start_db;;
  stop)  pkill -f "php -S 127.0.0.1:$WEB_PORT" || true
         mariadb-admin --protocol=tcp -h127.0.0.1 -P"$DB_PORT" -uroot shutdown 2>/dev/null || true;;
  *) echo "usage: $0 start|seed|db|stop"; exit 1;;
esac
