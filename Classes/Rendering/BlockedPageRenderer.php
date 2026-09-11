<?php

declare(strict_types=1);
namespace StraschekIo\TorBlocker\Rendering;

use StraschekIo\TorBlocker\Configuration\ExtensionSettings;
use TYPO3\CMS\Core\Localization\LocalizationFactory;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Fluid\View\StandaloneView;

/**
 * Renders the page for blocked visitors. It runs before the site and the frontend are
 * initialized, so labels are resolved here and handed to the template as variables.
 */
class BlockedPageRenderer
{
    private const LANGUAGE_FILE = 'EXT:tor_blocker/Resources/Private/Language/locallang.xlf';

    private const LABEL_KEYS = ['title', 'message'];

    private ExtensionSettings $extensionSettings;

    private LocalizationFactory $localizationFactory;

    public function __construct(ExtensionSettings $extensionSettings, LocalizationFactory $localizationFactory)
    {
        $this->extensionSettings = $extensionSettings;
        $this->localizationFactory = $localizationFactory;
    }

    public function render(string $languageKey): string
    {
        $view = GeneralUtility::makeInstance(StandaloneView::class);
        $view->setTemplatePathAndFilename(GeneralUtility::getFileAbsFileName($this->extensionSettings->getTemplatePath()));
        $view->assignMultiple([
            'htmlLanguage' => $languageKey === 'default' ? 'en' : $languageKey,
            'labels' => $this->getLabels($languageKey),
        ]);

        return (string)$view->render();
    }

    /**
     * @return array<string, string>
     */
    private function getLabels(string $languageKey): array
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
