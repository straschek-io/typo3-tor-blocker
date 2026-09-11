<?php

declare(strict_types=1);
namespace StraschekIo\TorBlocker\Tests\Unit\Configuration;

use PHPUnit\Framework\TestCase;
use StraschekIo\TorBlocker\Configuration\ExtensionSettings;

/**
 * @covers \StraschekIo\TorBlocker\Configuration\ExtensionSettings
 */
final class ExtensionSettingsTest extends TestCase
{
    /**
     * @var mixed
     */
    private $typo3ConfVarsBackup;

    protected function setUp(): void
    {
        $this->typo3ConfVarsBackup = $GLOBALS['TYPO3_CONF_VARS'] ?? null;
        unset($GLOBALS['TYPO3_CONF_VARS']);
    }

    protected function tearDown(): void
    {
        $GLOBALS['TYPO3_CONF_VARS'] = $this->typo3ConfVarsBackup;
    }

    public function testUsesTheDefaultsWithoutConfiguration(): void
    {
        $settings = new ExtensionSettings([]);

        self::assertSame('https://check.torproject.org/torbulkexitlist', $settings->getListUrl());
        self::assertSame(500, $settings->getMinimumEntries());
        self::assertSame('EXT:tor_blocker/Resources/Private/Templates/Blocked.html', $settings->getTemplatePath());
    }

    public function testUsesTheDefaultsWhenTheExtensionIsNotConfiguredYet(): void
    {
        $settings = new ExtensionSettings();

        self::assertSame(500, $settings->getMinimumEntries());
        self::assertSame('https://check.torproject.org/torbulkexitlist', $settings->getListUrl());
    }

    public function testReadsTheExtensionConfiguration(): void
    {
        $GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS']['tor_blocker'] = ['minimumEntries' => '42'];

        self::assertSame(42, (new ExtensionSettings())->getMinimumEntries());
    }

    public function testReturnsTheConfiguredValuesTrimmed(): void
    {
        $settings = new ExtensionSettings([
            'listUrl' => ' https://example.org/exit-nodes ',
            'minimumEntries' => '25',
            'templatePath' => ' EXT:site/Resources/Private/Templates/Blocked.html ',
        ]);

        self::assertSame('https://example.org/exit-nodes', $settings->getListUrl());
        self::assertSame(25, $settings->getMinimumEntries());
        self::assertSame('EXT:site/Resources/Private/Templates/Blocked.html', $settings->getTemplatePath());
    }

    /**
     * @dataProvider invalidMinimumEntriesProvider
     * @param mixed $minimumEntries
     */
    public function testFallsBackToTheDefaultMinimumForInvalidValues($minimumEntries): void
    {
        self::assertSame(500, (new ExtensionSettings(['minimumEntries' => $minimumEntries]))->getMinimumEntries());
    }

    /**
     * @return array<string, array{mixed}>
     */
    public function invalidMinimumEntriesProvider(): array
    {
        return [
            'zero' => ['0'],
            'negative' => ['-5'],
            'text' => ['many'],
            'empty' => [''],
        ];
    }

    public function testFallsBackToTheDefaultsForEmptyStrings(): void
    {
        $settings = new ExtensionSettings(['listUrl' => '   ', 'templatePath' => '']);

        self::assertSame('https://check.torproject.org/torbulkexitlist', $settings->getListUrl());
        self::assertSame('EXT:tor_blocker/Resources/Private/Templates/Blocked.html', $settings->getTemplatePath());
    }
}
