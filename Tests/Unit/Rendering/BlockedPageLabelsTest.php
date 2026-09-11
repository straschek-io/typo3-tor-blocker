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
    public function testReturnsTheTranslatedLabelsFromNestedData(): void
    {
        $parsedData = [
            'default' => $this->nestedLabels('Access not possible', 'No access via Tor.'),
            'de' => $this->nestedLabels('Zugriff nicht möglich', 'Kein Zugriff über Tor.'),
        ];

        self::assertSame(
            ['title' => 'Zugriff nicht möglich', 'message' => 'Kein Zugriff über Tor.'],
            $this->createLabels()->fromParsedData($parsedData, 'de')
        );
    }

    public function testReturnsTheDefaultLabelsFromNestedData(): void
    {
        $parsedData = ['default' => $this->nestedLabels('Access not possible', 'No access via Tor.')];

        self::assertSame(
            ['title' => 'Access not possible', 'message' => 'No access via Tor.'],
            $this->createLabels()->fromParsedData($parsedData, 'default')
        );
    }

    public function testFallsBackToTheDefaultLanguageForMissingTranslations(): void
    {
        $parsedData = [
            'default' => $this->nestedLabels('Access not possible', 'No access via Tor.'),
            'de' => ['title' => [['source' => 'Access not possible', 'target' => 'Zugriff nicht möglich']]],
        ];

        self::assertSame(
            ['title' => 'Zugriff nicht möglich', 'message' => 'No access via Tor.'],
            $this->createLabels()->fromParsedData($parsedData, 'de')
        );
    }

    public function testReturnsTheLabelsFromFlatDataOfTypo3Fourteen(): void
    {
        $parsedData = ['title' => 'Zugriff nicht möglich', 'message' => 'Kein Zugriff über Tor.'];

        self::assertSame(
            ['title' => 'Zugriff nicht möglich', 'message' => 'Kein Zugriff über Tor.'],
            $this->createLabels()->fromParsedData($parsedData, 'de')
        );
    }

    public function testFallsBackToEmptyStringsWithoutAnyLabels(): void
    {
        self::assertSame(['title' => '', 'message' => ''], $this->createLabels()->fromParsedData([], 'de'));
    }

    /**
     * @return array<string, array<int, array<string, string>>>
     */
    private function nestedLabels(string $title, string $message): array
    {
        return [
            'title' => [['source' => 'Access not possible', 'target' => $title]],
            'message' => [['source' => 'No access via Tor.', 'target' => $message]],
        ];
    }

    private function createLabels(): BlockedPageLabels
    {
        // LocalizationFactory is a read-only class from TYPO3 14 on and cannot be doubled,
        // the label lookup is tested on the parsed data instead
        $localizationFactory = (new \ReflectionClass(LocalizationFactory::class))->newInstanceWithoutConstructor();

        return new BlockedPageLabels($localizationFactory);
    }
}
