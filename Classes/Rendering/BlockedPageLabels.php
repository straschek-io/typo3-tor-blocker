<?php

declare(strict_types=1);
namespace StraschekIo\TorBlocker\Rendering;

use TYPO3\CMS\Core\Localization\LocalizationFactory;

/**
 * Resolves the labels of the blocked page. The page is rendered before the site and the
 * frontend are initialized, so the labels are read from the language file directly and
 * handed to the template as variables instead of using the translate view helper.
 */
class BlockedPageLabels
{
    private const LANGUAGE_FILE = 'EXT:tor_blocker/Resources/Private/Language/locallang.xlf';

    private const LABEL_KEYS = ['title', 'message'];

    private LocalizationFactory $localizationFactory;

    public function __construct(LocalizationFactory $localizationFactory)
    {
        $this->localizationFactory = $localizationFactory;
    }

    /**
     * @param string $languageKey
     * @return array<string, string>
     */
    public function get(string $languageKey): array
    {
        return $this->fromParsedData($this->localizationFactory->getParsedData(self::LANGUAGE_FILE, $languageKey), $languageKey);
    }

    /**
     * Labels from the parsed language data, falling back to the default language and then to
     * an empty string, so the template never sees a missing variable. Handles both formats of
     * LocalizationFactory::getParsedData(): nested per language with source and target up to
     * TYPO3 13, flat with the fallbacks already resolved from TYPO3 14 on.
     *
     * @param array<string, mixed> $parsedData
     * @param string $languageKey
     * @return array<string, string>
     */
    public function fromParsedData(array $parsedData, string $languageKey): array
    {
        $labels = [];
        foreach (self::LABEL_KEYS as $labelKey) {
            $value = $parsedData[$labelKey] ?? null;
            if (!is_string($value)) {
                $value = $parsedData[$languageKey][$labelKey][0]['target']
                    ?? $parsedData['default'][$labelKey][0]['target']
                    ?? '';
            }
            $labels[$labelKey] = (string)$value;
        }

        return $labels;
    }
}
