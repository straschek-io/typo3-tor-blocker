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
     * Labels in the requested language, falling back to the default language and then to
     * an empty string, so the template never sees a missing variable.
     *
     * @param string $languageKey
     * @return array<string, string>
     */
    public function get(string $languageKey): array
    {
        $parsedData = $this->localizationFactory->getParsedData(self::LANGUAGE_FILE, $languageKey);

        $labels = [];
        foreach (self::LABEL_KEYS as $labelKey) {
            $labels[$labelKey] = (string)($parsedData[$languageKey][$labelKey][0]['target']
                ?? $parsedData['default'][$labelKey][0]['target']
                ?? '');
        }

        return $labels;
    }
}
