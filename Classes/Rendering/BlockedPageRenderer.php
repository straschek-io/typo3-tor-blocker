<?php

declare(strict_types=1);
namespace StraschekIo\TorBlocker\Rendering;

use StraschekIo\TorBlocker\Configuration\ExtensionSettings;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\View\ViewFactoryData;
use TYPO3\CMS\Core\View\ViewFactoryInterface;
use TYPO3\CMS\Fluid\View\StandaloneView;

/**
 * Renders the page for blocked visitors. It runs before the site and the frontend are
 * initialized, so labels are resolved here and handed to the template as variables.
 */
class BlockedPageRenderer
{
    private BlockedPageLabels $blockedPageLabels;

    private ExtensionSettings $extensionSettings;

    /**
     * Only available from TYPO3 13 on, where StandaloneView is deprecated. The container
     * leaves the argument at null in TYPO3 10 and 12, where the interface does not exist.
     *
     * @var ViewFactoryInterface|null
     */
    private $viewFactory;

    public function __construct(
        ExtensionSettings $extensionSettings,
        BlockedPageLabels $blockedPageLabels,
        ?ViewFactoryInterface $viewFactory = null
    ) {
        $this->extensionSettings = $extensionSettings;
        $this->blockedPageLabels = $blockedPageLabels;
        $this->viewFactory = $viewFactory;
    }

    public function render(string $languageKey): string
    {
        $templatePathAndFilename = GeneralUtility::getFileAbsFileName($this->extensionSettings->getTemplatePath());
        $variables = [
            'htmlLanguage' => $languageKey === 'default' ? 'en' : $languageKey,
            'labels' => $this->blockedPageLabels->get($languageKey),
        ];

        if ($this->viewFactory !== null) {
            $view = $this->viewFactory->create(new ViewFactoryData(null, null, null, $templatePathAndFilename));
            $view->assignMultiple($variables);

            return $view->render();
        }

        // TYPO3 10 and 12
        $view = GeneralUtility::makeInstance(StandaloneView::class);
        $view->setTemplatePathAndFilename($templatePathAndFilename);
        $view->assignMultiple($variables);

        return (string)$view->render();
    }
}
