#!/bin/sh
set -eu
if ! command -v unzip >/dev/null 2>&1 && ! php -m | grep -qi '^zip$'; then
  apt-get update
  apt-get install -y --no-install-recommends unzip
  rm -rf /var/lib/apt/lists/*
fi
php -r 'copy("https://getcomposer.org/download/2.11.1/composer.phar", "/tmp/composer.phar");'
php -r 'copy("https://getcomposer.org/download/2.11.1/composer.phar.sha256sum", "/tmp/composer.phar.sha256sum");'
expected=$(awk 'NR==1 {print $1}' /tmp/composer.phar.sha256sum)
actual=$(php -r 'echo hash_file("sha256", "/tmp/composer.phar");')
pin=6f1b891e1d4927d62740276c540fb490e47689e19e102c49e8da849794882f8c
[ "$expected" = "$pin" ] && [ "$actual" = "$pin" ]
update_out=$(mktemp)
set +e
php /tmp/composer.phar update --no-interaction --no-progress --prefer-dist --no-ansi 2>&1 | sed 's/\x1b\[[0-9;]*[a-zA-Z]//g' | tee "$update_out"
update_status=$?
set -e
if [ "$update_status" -ne 0 ]; then
  exit "$update_status"
fi
if grep -nE 'Warning:|Deprecated:' "$update_out"; then
  exit 1
fi
out=$(mktemp)
set +e
php vendor/bin/phpunit --colors=never --do-not-cache-result >"$out" 2>&1
status=$?
set -e
sed 's/\x1b\[[0-9;]*[a-zA-Z]//g' "$out"
if grep -nE 'Warning:|Deprecated:' "$out"; then
  exit 1
fi
grep -Eq '^OK \([0-9]+ tests, [0-9]+ assertions\)$' "$out"
[ "$status" -eq 0 ]
php /tmp/composer.phar audit --no-interaction --no-ansi
