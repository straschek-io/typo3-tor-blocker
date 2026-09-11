<?php

declare(strict_types=1);
namespace StraschekIo\TorBlocker\Configuration;

/**
 * Extension configuration with defaults, read from $GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS']
 * as ExtensionConfiguration::get() does. Reading the array directly keeps the class free of
 * core services, which are read-only classes from TYPO3 14 on and cannot be replaced in tests.
 */
class ExtensionSettings
{
    public const EXTENSION_KEY = 'tor_blocker';

    private const DEFAULTS = [
        'listUrl' => 'https://check.torproject.org/torbulkexitlist',
        'minimumEntries' => 500,
        'templatePath' => 'EXT:tor_blocker/Resources/Private/Templates/Blocked.html',
    ];

    private array $settings;

    /**
     * @param array<string, mixed>|null $settings Defaults to the extension configuration; not configured yet
     *                                            (e.g. activated without running the extension setup) means defaults
     */
    public function __construct(?array $settings = null)
    {
        $settings = $settings ?? ($GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS'][self::EXTENSION_KEY] ?? []);
        $this->settings = is_array($settings) ? $settings : [];
    }

    public function getListUrl(): string
    {
        return $this->getString('listUrl');
    }

    public function getMinimumEntries(): int
    {
        $minimumEntries = (int)($this->settings['minimumEntries'] ?? 0);

        return $minimumEntries > 0 ? $minimumEntries : self::DEFAULTS['minimumEntries'];
    }

    public function getTemplatePath(): string
    {
        return $this->getString('templatePath');
    }

    private function getString(string $name): string
    {
        $value = trim((string)($this->settings[$name] ?? ''));

        return $value !== '' ? $value : self::DEFAULTS[$name];
    }
}
