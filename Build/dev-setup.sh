#!/usr/bin/env bash
# Bootstrap a full TYPO3 dev instance around this extension for manual testing.
# Usage from a fresh clone: ./Build/dev-setup.sh
# Re-running is safe: existing installation, site and content are left untouched.
#
# The instance runs TYPO3 13.4 on PHP 8.3, the newest supported combination, because
# TYPO3 10 has no "typo3 setup" command. The lowest supported combination (TYPO3 10.4,
# PHP 7.4) is covered by the unit tests in CI and by the Docker one-liners in the README.
set -euo pipefail
cd "$(dirname "$0")/.."

BACKEND_USER='admin'
BACKEND_PASSWORD='TorBlocker13!'
SITE_URL='https://typo3-tor-blocker.ddev.site'

if [ ! -f .ddev/config.yaml ]; then
    ddev config --project-type=typo3 --docroot=public --create-docroot --php-version=8.3
fi
ddev start

ddev composer install --no-progress --no-interaction

if ! ddev exec test -f config/system/settings.php; then
    ddev exec vendor/bin/typo3 setup \
        --driver=mysqli --host=db --port=3306 --dbname=db --username=db --password=db \
        --admin-username="${BACKEND_USER}" --admin-user-password="${BACKEND_PASSWORD}" \
        --admin-email=hallo@straschek.io --project-name='Tor Blocker Dev' \
        --server-type=other --force --no-interaction
    # enable deprecation logging (limited to the LOG deprecations block)
    ddev exec sed -i "/'deprecations' =>/,/^                ],$/ s/'disabled' => true/'disabled' => false/" \
        config/system/settings.php
fi

if [ ! -f config/sites/main/config.yaml ]; then
    mkdir -p config/sites/main
    cat > config/sites/main/config.yaml <<YAML
rootPageId: 1
base: '${SITE_URL}/'
websiteTitle: 'Tor Blocker Dev'
languages:
  - title: Deutsch
    enabled: true
    languageId: 0
    base: /
    locale: de_DE.UTF-8
    navigationTitle: Deutsch
    flag: de
YAML

    ddev mutagen sync >/dev/null 2>&1 || true
    ddev mysql <<'SQL'
INSERT IGNORE INTO pages (uid, pid, title, doktype, is_siteroot, slug, tstamp, crdate, sorting)
VALUES (1, 0, 'Home', 1, 1, '/', UNIX_TIMESTAMP(), UNIX_TIMESTAMP(), 256);

INSERT IGNORE INTO sys_template (uid, pid, title, root, clear, include_static_file, config, tstamp, crdate, sorting)
VALUES (1, 1, 'Main', 1, 3, 'EXT:fluid_styled_content/Configuration/TypoScript/',
        'page = PAGE\npage.10 < styles.content.get', UNIX_TIMESTAMP(), UNIX_TIMESTAMP(), 256);

INSERT IGNORE INTO tt_content (uid, pid, CType, header, bodytext, colPos, tstamp, crdate, sorting)
VALUES (1, 1, 'text', 'Not blocked', '<p>This request did not come from a Tor exit node.</p>',
        0, UNIX_TIMESTAMP(), UNIX_TIMESTAMP(), 256);
SQL
fi

# A dev list with the loopback addresses only: requests from inside the web container
# are blocked, requests through the ddev router (your browser) are not. Running
# "torblocker:update" replaces it with the real list.
if [ ! -f var/tor_blocker/exit-nodes.php ]; then
    mkdir -p var/tor_blocker
    cat > var/tor_blocker/exit-nodes.php <<'PHP'
<?php

return array (
  '127.0.0.1' => true,
  '::1' => true,
);
PHP
fi

ddev exec vendor/bin/typo3 cache:flush

echo ""
echo "Frontend: ${SITE_URL}/"
echo "Backend:  ${SITE_URL}/typo3 (${BACKEND_USER} / ${BACKEND_PASSWORD})"
echo "Blocked:  ddev exec curl -si http://localhost/ | head -5   (expects 403, Cache-Control: no-store)"
echo "Update:   ddev exec vendor/bin/typo3 torblocker:update      (downloads the real list)"
echo "Tests:    ddev composer test | Code style: ddev composer cs"
