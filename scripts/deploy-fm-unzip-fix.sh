#!/usr/bin/env bash
set -euo pipefail

host="${1:-root@86.48.2.171}"
ssh_key="${SSH_KEY:-$HOME/.ssh/zodpanel_deploy}"
ssh_opts=(-i "$ssh_key" -o BatchMode=yes -o StrictHostKeyChecking=accept-new)

if [[ ! -f "$ssh_key" ]]; then
	echo "SSH key not found: $ssh_key"
	echo "Set SSH_KEY=/path/to/key or install the zodpanel deploy key first."
	exit 66
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

ssh "${ssh_opts[@]}" "$host" "cat > '$remote_archive'" < "$archive"
ssh "${ssh_opts[@]}" "$host" "set -e; stamp=\$(date +%Y%m%d%H%M%S); cp -a /usr/local/hestia/web/fm/configuration.php /usr/local/hestia/web/fm/configuration.php.bak-zod-unzip-\$stamp; cp -a /usr/local/hestia/web/fm/dist/js/app.js /usr/local/hestia/web/fm/dist/js/app.js.bak-zod-unzip-\$stamp; tar -C / -xzf '$remote_archive'; rm -f '$remote_archive'; php -l /usr/local/hestia/web/fm/configuration.php; systemctl reload hestia || systemctl restart hestia"
curl -ks "https://zodpanel.zodhost.com:8083/fm/js/app.js" | php -r '
$js = stream_get_contents(STDIN);
$checks = [
	"domain-preserving API URL" => strpos($js, "new URLSearchParams(window.location.search)") !== false,
	"unzip folder picker" => strpos($js, "unzip(e,a){this.\$modal.open") !== false,
];
foreach ($checks as $name => $ok) {
	echo ($ok ? "OK " : "FAIL ") . $name . PHP_EOL;
}
exit(in_array(false, $checks, true) ? 1 : 0);
'
