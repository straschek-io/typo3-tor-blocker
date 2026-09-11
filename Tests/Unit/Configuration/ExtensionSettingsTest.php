<?php

declare(strict_types=1);
namespace StraschekIo\TorBlocker\Tests\Unit\Configuration;

use PHPUnit\Framework\TestCase;
use StraschekIo\TorBlocker\Configuration\ExtensionSettings;
use TYPO3\CMS\Core\Configuration\Exception\ExtensionConfigurationExtensionNotConfiguredException;
use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;

/**
 * @covers \StraschekIo\TorBlocker\Configuration\ExtensionSettings
 */
final class ExtensionSettingsTest extends TestCase
{
    public function testUsesTheDefaultsWithoutConfiguration(): void
    {
        $settings = $this->createSettings([]);

        self::assertSame('https://check.torproject.org/torbulkexitlist', $settings->getListUrl());
        self::assertSame(500, $settings->getMinimumEntries());
        self::assertSame('EXT:tor_blocker/Resources/Private/Templates/Blocked.html', $settings->getTemplatePath());
    }

    public function testUsesTheDefaultsWhenTheExtensionIsNotConfiguredYet(): void
    {
        $extensionConfiguration = $this->createMock(ExtensionConfiguration::class);
        $extensionConfiguration->method('get')->willThrowException(
            new ExtensionConfigurationExtensionNotConfiguredException('not configured', 1789113700)
        );

        $settings = new ExtensionSettings($extensionConfiguration);

        self::assertSame(500, $settings->getMinimumEntries());
        self::assertSame('https://check.torproject.org/torbulkexitlist', $settings->getListUrl());
    }

    public function testReturnsTheConfiguredValuesTrimmed(): void
    {
        $settings = $this->createSettings([
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
        self::assertSame(500, $this->createSettings(['minimumEntries' => $minimumEntries])->getMinimumEntries());
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
        $settings = $this->createSettings(['listUrl' => '   ', 'templatePath' => '']);

        self::assertSame('https://check.torproject.org/torbulkexitlist', $settings->getListUrl());
        self::assertSame('EXT:tor_blocker/Resources/Private/Templates/Blocked.html', $settings->getTemplatePath());
    }

    /**
     * @param mixed $configuration
     */
    private function createSettings($configuration): ExtensionSettings
    {
        $extensionConfiguration = $this->createMock(ExtensionConfiguration::class);
        $extensionConfiguration->method('get')->with('tor_blocker')->willReturn($configuration);

        return new ExtensionSettings($extensionConfiguration);
    }
}
