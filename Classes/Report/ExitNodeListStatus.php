<?php

declare(strict_types=1);
namespace StraschekIo\TorBlocker\Report;

use StraschekIo\TorBlocker\Repository\ExitNodeRepository;
use TYPO3\CMS\Core\Type\ContextualFeedbackSeverity;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Reports\Status;
use TYPO3\CMS\Reports\StatusProviderInterface;

/**
 * Shows the state of the stored exit node list in the reports module. The extension is
 * fail-open, so a missing, broken or outdated list would go unnoticed otherwise.
 */
class ExitNodeListStatus implements StatusProviderInterface
{
    private const TITLE = 'Tor exit node list';

    private const MAXIMUM_AGE_IN_HOURS = 24;

    private ExitNodeRepository $exitNodeRepository;

    public function __construct(?ExitNodeRepository $exitNodeRepository = null)
    {
        // TYPO3 10 creates status providers without constructor arguments
        $this->exitNodeRepository = $exitNodeRepository ?? GeneralUtility::makeInstance(ExitNodeRepository::class);
    }

    public function getLabel(): string
    {
        return 'Tor Blocker';
    }

    /**
     * @return Status[]
     */
    public function getStatus(): array
    {
        return ['exitNodeList' => $this->getExitNodeListStatus()];
    }

    private function getExitNodeListStatus(): Status
    {
        $lastModified = $this->exitNodeRepository->getLastModified();
        if ($lastModified === null) {
            return $this->createStatus(
                'No list stored',
                'Nobody is blocked. Run "torblocker:update" once and set up a scheduler task or cron job for it.',
                'WARNING'
            );
        }

        $count = $this->exitNodeRepository->count();
        if ($count === 0) {
            return $this->createStatus(
                'The stored list is empty or cannot be loaded',
                sprintf(
                    'Nobody is blocked. Check that the web server user can read "%s" and run "torblocker:update".',
                    $this->exitNodeRepository->getFilePath()
                ),
                'ERROR'
            );
        }

        $ageInHours = (int)floor(max(0, time() - $lastModified) / 3600);
        $value = sprintf('%d addresses, updated %d hours ago', $count, $ageInHours);
        if ($ageInHours >= self::MAXIMUM_AGE_IN_HOURS) {
            return $this->createStatus(
                $value,
                'The list should be updated hourly. Check the scheduler task or cron job that runs "torblocker:update".',
                'WARNING'
            );
        }

        return $this->createStatus($value, '', 'OK');
    }

    /**
     * @param string $value
     * @param string $message
     * @param string $severity Name of the severity: OK, WARNING or ERROR
     */
    private function createStatus(string $value, string $message, string $severity): Status
    {
        // Integer constants of the status class up to TYPO3 12, the enum is mandatory from TYPO3 13 on
        $severityClass = class_exists(ContextualFeedbackSeverity::class) ? ContextualFeedbackSeverity::class : Status::class;

        return new Status(self::TITLE, $value, $message, constant($severityClass . '::' . $severity));
    }
}
