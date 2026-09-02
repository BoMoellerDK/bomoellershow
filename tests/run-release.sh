#!/usr/bin/env bash
set -euo pipefail

test_temp_dir="${RUNNER_TEMP:-/tmp}"
runtime_file="$test_temp_dir/bomoellershow-youtube-runtime-test.json"
server_log="$test_temp_dir/bomoellershow-php-server.log"
rss_snapshot_file="$test_temp_dir/bomoellershow-rss-snapshot-test.xml"
synthetic_runtime_file="$test_temp_dir/bomoellershow-youtube-runtime-synthetic.json"
synthetic_server_log="$test_temp_dir/bomoellershow-php-server-synthetic.log"
synthetic_rss_snapshot_file="$test_temp_dir/bomoellershow-rss-snapshot-synthetic.xml"
fixture_root="$PWD/tests/fixtures"
server_pids=()

cleanup_servers() {
  for running_pid in "${server_pids[@]}"; do
    kill "$running_pid" 2>/dev/null || true
  done
}
trap cleanup_servers EXIT

wait_for_server() {
  local server_url="$1"
  local log_file="$2"
  local server_ready=0
  for attempt in {1..20}; do
    if curl --fail --silent "$server_url" >/dev/null; then
      server_ready=1
      break
    fi
    sleep 0.25
  done
  if [[ "$server_ready" != "1" ]]; then
    echo "Testserveren startede ikke: $server_url"
    sed -n '1,160p' "$log_file"
    exit 1
  fi
}

BOMOELLERSHOW_YOUTUBE_RUNTIME_FILE="$runtime_file" \
BOMOELLERSHOW_YOUTUBE_FEED_URL='file:///dev/null' \
BOMOELLERSHOW_RSS_URL="file://$PWD/data/podcast-rss-fallback.xml" \
BOMOELLERSHOW_RSS_SNAPSHOT_FILE="$rss_snapshot_file" \
BOMOELLERSHOW_CACHE_NAMESPACE="release-test-$$" \
php -S 127.0.0.1:8877 -t public_html tests/router.php >"$server_log" 2>&1 &
server_pid=$!
server_pids+=("$server_pid")
wait_for_server 'http://127.0.0.1:8877/' "$server_log"

BOMOELLERSHOW_YOUTUBE_RUNTIME_FILE="$runtime_file" php tests/release.php

BOMOELLERSHOW_RSS_URL="file://$fixture_root/synthetic-rss.xml" \
BOMOELLERSHOW_RSS_SNAPSHOT_FILE="$synthetic_rss_snapshot_file" \
BOMOELLERSHOW_YOUTUBE_FEED_URL="file://$fixture_root/synthetic-youtube.xml" \
BOMOELLERSHOW_YOUTUBE_RUNTIME_FILE="$synthetic_runtime_file" \
BOMOELLERSHOW_CACHE_NAMESPACE="synthetic-test-$$" \
php -S 127.0.0.1:8878 -t public_html tests/router.php >"$synthetic_server_log" 2>&1 &
synthetic_server_pid=$!
server_pids+=("$synthetic_server_pid")
wait_for_server 'http://127.0.0.1:8878/' "$synthetic_server_log"

BOMOELLERSHOW_TEST_BASE_URL='http://127.0.0.1:8878' \
BOMOELLERSHOW_YOUTUBE_RUNTIME_FILE="$synthetic_runtime_file" \
php tests/synthetic-youtube.php
