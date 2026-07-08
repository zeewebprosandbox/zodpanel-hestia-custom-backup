#!/usr/bin/env bash
set -euo pipefail

host="${1:-}"

if [[ -z "$host" ]]; then
	echo "Usage: $0 root@zodpanel.zodhost.com"
	exit 64
fi

repo_root="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
archive="$(mktemp -t zodpanel-fm-unzip-fix.XXXXXX.tar.gz)"

cleanup() {
	rm -f "$archive"
}
trap cleanup EXIT

tar -C "$repo_root" -czf "$archive" \
	usr/local/hestia/web/fm/configuration.php \
	usr/local/hestia/web/fm/dist/js/app.js

remote_archive="/tmp/zodpanel-fm-unzip-fix.tar.gz"

scp "$archive" "$host:$remote_archive"
ssh "$host" "sudo tar -C / -xzf '$remote_archive' && sudo rm -f '$remote_archive' && sudo php -l /usr/local/hestia/web/fm/configuration.php && sudo systemctl reload hestia"
curl -ks "https://zodpanel.zodhost.com:8083/fm/js/app.js" | php -r '
$js = stream_get_contents(STDIN);
$checks = [
	"domain-preserving API URL" => strpos($js, "new URLSearchParams(window.location.search)") !== false,
	"unzip folder picker" => strpos($js, "unzip(e,a){this.\$modal.open") !== false,
	"old cwd unzip destination removed" => strpos($js, "destination:this.\$store.state.cwd.location") === false,
];
foreach ($checks as $name => $ok) {
	echo ($ok ? "OK " : "FAIL ") . $name . PHP_EOL;
}
exit(in_array(false, $checks, true) ? 1 : 0);
'
