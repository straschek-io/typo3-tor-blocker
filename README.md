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

1. Download the list once:

   ```
   vendor/bin/typo3 torblocker:update
   ```

2. Keep it up to date: add a scheduler task "Execute console commands" with
   `torblocker:update`, running hourly.

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
`Accept-Language` header, since the site and its languages are not resolved yet
at that point.

## Good to know

- The list is stored as a PHP array in `<var path>/tor_blocker/exit-nodes.php`
  (`typo3temp/var/` in classic mode, `var/` in composer mode). Opcache keeps it in
  memory, so a lookup costs a single `isset()`.
- The file is replaced atomically. A failed or implausible download never touches
  the stored list, and the command exits with `1`.
- **Fail-open:** without a stored list nobody is blocked. Deleting the file is the
  emergency switch; the next scheduler run brings it back.
- The client address comes from `GeneralUtility::getIndpEnv('REMOTE_ADDR')`, so
  `$GLOBALS['TYPO3_CONF_VARS']['SYS']['reverseProxyIP']` is respected. Behind a
  proxy or CDN, configure it, or the proxy's address is checked instead of the
  visitor's. Never set it to `*` without a proxy: clients could then fake their
  address via `X-Forwarded-For`.
- Static files are delivered by the web server and are not affected.

## Development

```
composer install
composer test
composer cs
```

Activate the pre-commit hook once per clone: `git config core.hooksPath .githooks`

## Compatibility

Compatible with TYPO3 10.4, 12.4 and 13.4, PHP 7.4 – 8.3.
Covered by PHPUnit tests, running in CI against TYPO3 10.4 (PHP 7.4),
12.4 (PHP 8.2) and 13.4 (PHP 8.3).

Works for me, may work for you.
