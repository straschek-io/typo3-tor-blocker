<?php

declare(strict_types=1);
namespace StraschekIo\TorBlocker\Configuration;

use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;
use TYPO3\CMS\Core\Exception;

class ExtensionSettings
{
    public const EXTENSION_KEY = 'tor_blocker';

    private const DEFAULTS = [
        'listUrl' => 'https://check.torproject.org/torbulkexitlist',
        'minimumEntries' => 500,
        'templatePath' => 'EXT:tor_blocker/Resources/Private/Templates/Blocked.html',
    ];

    private array $settings;

    public function __construct(ExtensionConfiguration $extensionConfiguration)
    {
        try {
            $settings = $extensionConfiguration->get(self::EXTENSION_KEY);
        } catch (Exception $exception) {
            // Not configured yet, e.g. activated without running the extension setup
            $settings = [];
        }
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
