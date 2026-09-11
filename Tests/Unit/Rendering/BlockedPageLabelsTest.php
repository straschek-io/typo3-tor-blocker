<?php

declare(strict_types=1);
namespace StraschekIo\TorBlocker\Tests\Unit\Rendering;

use PHPUnit\Framework\TestCase;
use StraschekIo\TorBlocker\Rendering\BlockedPageLabels;
use TYPO3\CMS\Core\Localization\LocalizationFactory;

/**
 * @covers \StraschekIo\TorBlocker\Rendering\BlockedPageLabels
 */
final class BlockedPageLabelsTest extends TestCase
{
    private const LANGUAGE_FILE = 'EXT:tor_blocker/Resources/Private/Language/locallang.xlf';

    public function testReturnsTheTranslatedLabels(): void
    {
        $labels = new BlockedPageLabels($this->createLocalizationFactory('de', [
            'default' => $this->parsedLabels('Access not possible', 'No access via Tor.'),
            'de' => $this->parsedLabels('Zugriff nicht möglich', 'Kein Zugriff über Tor.'),
        ]));

        self::assertSame(
            ['title' => 'Zugriff nicht möglich', 'message' => 'Kein Zugriff über Tor.'],
            $labels->get('de')
        );
    }

    public function testReturnsTheDefaultLabels(): void
    {
        $labels = new BlockedPageLabels($this->createLocalizationFactory('default', [
            'default' => $this->parsedLabels('Access not possible', 'No access via Tor.'),
        ]));

        self::assertSame(['title' => 'Access not possible', 'message' => 'No access via Tor.'], $labels->get('default'));
    }

    public function testFallsBackToTheDefaultLanguageForMissingTranslations(): void
    {
        $labels = new BlockedPageLabels($this->createLocalizationFactory('de', [
            'default' => $this->parsedLabels('Access not possible', 'No access via Tor.'),
            'de' => ['title' => [['source' => 'Access not possible', 'target' => 'Zugriff nicht möglich']]],
        ]));

        self::assertSame(['title' => 'Zugriff nicht möglich', 'message' => 'No access via Tor.'], $labels->get('de'));
    }

    public function testFallsBackToEmptyStringsWithoutAnyLabels(): void
    {
        $labels = new BlockedPageLabels($this->createLocalizationFactory('de', []));

        self::assertSame(['title' => '', 'message' => ''], $labels->get('de'));
    }

    /**
     * @return array<string, array<int, array<string, string>>>
     */
    private function parsedLabels(string $title, string $message): array
    {
        return [
            'title' => [['source' => 'Access not possible', 'target' => $title]],
            'message' => [['source' => 'No access via Tor.', 'target' => $message]],
        ];
    }

    private function createLocalizationFactory(string $expectedLanguageKey, array $parsedData): LocalizationFactory
    {
        $localizationFactory = $this->createMock(LocalizationFactory::class);
        $localizationFactory
            ->expects(self::once())
            ->method('getParsedData')
            ->with(self::LANGUAGE_FILE, $expectedLanguageKey)
            ->willReturn($parsedData);

        return $localizationFactory;
    }
}
