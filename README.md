TYPO3 Tor Blocker
======================================

This extension answers every frontend request coming from a Tor exit node with
`403 Forbidden` and a short notice page.

Form spam bots like to hide behind the Tor network: every submission arrives from
a different exit node, so blocking single IP addresses is pointless. The Tor
Project publishes the complete list of exit nodes, which makes blocking all of
them straightforward. Legitimate visitors via Tor are rare on most websites;
if they matter for yours, this extension is not for you.

## How to install

Composer mode:

```
composer require straschek-io/typo3-tor-blocker
vendor/bin/typo3 extension:setup
```

Classic mode: copy the extension to `typo3conf/ext/tor_blocker/`, activate it in
the extension manager and dump the autoload information.

## How to use

1. Download the list once, as the user the web server runs as (see
   [Permissions](#permissions)):

   ```
   vendor/bin/typo3 torblocker:update
   ```

2. Keep it up to date: add a scheduler task "Execute console commands" with
   `torblocker:update`, running hourly.

3. Check that it works: request the site from a listed address (Tor Browser, or
   `curl` from a listed exit node) and expect a `403` with `Cache-Control: no-store`.

That's it. The middleware runs first in the frontend stack, before static file
caches and before the page is resolved. The backend is never blocked.

## Configuration

Extension configuration, all optional:

| Setting | Default | |
|---|---|---|
| `listUrl` | `https://check.torproject.org/torbulkexitlist` | Plain text, one IP address per line |
| `minimumEntries` | `500` | A download with fewer valid addresses is rejected and the stored list is kept |
| `templatePath` | `EXT:tor_blocker/Resources/Private/Templates/Blocked.html` | Fluid template of the notice page, gets `{htmlLanguage}` and `{labels.title}`, `{labels.message}` |

The notice page is available in English and German, chosen by the browser's
`Accept-Language` header (best quality first), since the site and its languages
are not resolved yet at that point.

## Good to know

- The list is stored as a PHP array in `<var path>/tor_blocker/exit-nodes.php`
  (`typo3temp/var/` in classic mode, `var/` in composer mode). Opcache keeps it in
  memory, so a lookup costs a single `isset()`.
- The file is replaced atomically. A failed or implausible download never touches
  the stored list, and the command exits with `1`.
- **Fail-open:** without a stored list nobody is blocked. Deleting the file is the
  emergency switch; the next scheduler run brings it back. A list that exists but
  cannot be loaded blocks nobody either and is logged as an error
  (`TYPO3.CMS.Log` component `StraschekIo.TorBlocker.Repository.ExitNodeRepository`).
- Addresses are normalized on both sides before they are compared: IPv6 is
  compressed and lower cased, IPv4-mapped IPv6 addresses (`::ffff:192.0.2.1`, as
  reported by dual stack sockets with `ipv6only=off`) count as the IPv4 address.
- The client address comes from `GeneralUtility::getIndpEnv('REMOTE_ADDR')`, so
  `$GLOBALS['TYPO3_CONF_VARS']['SYS']['reverseProxyIP']` is respected. Behind a
  proxy or CDN, configure it, or the proxy's address is checked instead of the
  visitor's. Never set it to `*` without a proxy: clients could then fake their
  address via `X-Forwarded-For`.
- Static files are delivered by the web server and are not affected.

### Permissions

`torblocker:update` runs on the command line, the list is read by the web server
process. The directory and the file get the permissions configured in
`$GLOBALS['TYPO3_CONF_VARS']['SYS']` (`folderCreateMask`, `fileCreateMask`,
`createGroup`; defaults `2775`, `0664`, none), independent of the umask of the
CLI user. Run the command and the scheduler as the web server user anyway, or
make sure that user can read `<var path>/tor_blocker/exit-nodes.php`; the
command fails with exit code `1` if the file is not readable after writing.

### Opcache

The web server's opcache notices the new list by its modification time, within
`opcache.revalidate_freq` seconds (default 2). With
`opcache.validate_timestamps = 0` the web server keeps the old list, and also
keeps blocking after the file was deleted, until PHP-FPM is reloaded. Add the
reload to your update routine in that case.

## Development

```
./Build/dev-setup.sh
```

Bootstraps a full TYPO3 13.4 dev instance (DDEV required, PHP 8.3) with a seeded
page and a dev list that blocks the loopback addresses only, so requests from
inside the web container get the notice page while your browser gets the page.
Frontend: https://typo3-tor-blocker.ddev.site/ — Backend: `/typo3` (`admin` / `TorBlocker13!`)
Run tests with `ddev composer test`, code style with `ddev composer cs`.

The lowest supported combination, TYPO3 10.4 on PHP 7.4, runs in Docker:

```
composer config platform.php 7.4.33 && composer install && composer config --unset platform
docker run --rm -v "$PWD":/app -w /app php:7.4-cli vendor/bin/phpunit
docker run --rm -v "$PWD":/app -w /app php:7.4-cli vendor/bin/php-cs-fixer fix --dry-run --diff
```

Activate the pre-commit hook once per clone: `git config core.hooksPath .githooks`

## Compatibility

Compatible with TYPO3 10.4, 12.4 and 13.4, PHP 7.4 – 8.3. TYPO3 11.5 is not
tested. Covered by PHPUnit tests against TYPO3 10.4 (PHP 7.4), 12.4 (PHP 8.2)
and 13.4 (PHP 8.3). On TYPO3 13 the notice page is rendered through the
`ViewFactoryInterface`, on 10 and 12 through `StandaloneView`.

Works for me, may work for you.
