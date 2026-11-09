#!/bin/sh
set -eu
cd "$(dirname "$0")/.."
SUDO=
if [ "${USE_SUDO_DOCKER:-0}" = "1" ]; then
  SUDO=sudo
fi
run() {
  $SUDO docker run --rm -v "$PWD":/src:ro "$1" \
    sh -c 'cp -a /src /tmp/chronos && cd /tmp/chronos && sh ci/strict-test.sh'
}
run php:8.1-cli-bookworm@sha256:482717722f08d9a6c2b4f98c40bfee6d7935bd1a17fef928881ecd5884dfd76e
run php:8.2-cli-bookworm@sha256:ce09ca1d4b532edf9b0347b44a1968a80d9175addb581c450ba0c9a43fa0c5ea
