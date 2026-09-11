<?php

declare(strict_types=1);
namespace StraschekIo\TorBlocker\Download;

use TYPO3\CMS\Core\Http\RequestFactory;

/**
 * Downloads the exit node list through the TYPO3 request factory, so the HTTP settings
 * of the installation (proxy, certificates) apply. Kept separate from the command,
 * because RequestFactory is a read-only class from TYPO3 14 on and cannot be replaced in tests.
 */
class ExitNodeListDownloader
{
    private const REQUEST_TIMEOUT = 30;

    private RequestFactory $requestFactory;

    public function __construct(RequestFactory $requestFactory)
    {
        $this->requestFactory = $requestFactory;
    }

    /**
     * @throws DownloadFailedException
     */
    public function download(string $url): string
    {
        try {
            $response = $this->requestFactory->request($url, 'GET', ['timeout' => self::REQUEST_TIMEOUT]);
        } catch (\Throwable $exception) {
            throw new DownloadFailedException(
                sprintf('Could not download %s: %s.', $url, $exception->getMessage()),
                1789113610,
                $exception
            );
        }

        if ($response->getStatusCode() !== 200) {
            throw new DownloadFailedException(
                sprintf('%s answered with status %d.', $url, $response->getStatusCode()),
                1789113611
            );
        }

        return (string)$response->getBody();
    }
}
