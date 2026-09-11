<?php

declare(strict_types=1);
namespace StraschekIo\TorBlocker\Command;

use StraschekIo\TorBlocker\Configuration\ExtensionSettings;
use StraschekIo\TorBlocker\Parser\ExitNodeListParser;
use StraschekIo\TorBlocker\Repository\ExitNodeRepository;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use TYPO3\CMS\Core\Http\RequestFactory;

class UpdateCommand extends Command
{
    private const REQUEST_TIMEOUT = 30;

    private ExitNodeListParser $exitNodeListParser;

    private ExitNodeRepository $exitNodeRepository;

    private ExtensionSettings $extensionSettings;

    private RequestFactory $requestFactory;

    public function __construct(
        RequestFactory $requestFactory,
        ExitNodeListParser $exitNodeListParser,
        ExitNodeRepository $exitNodeRepository,
        ExtensionSettings $extensionSettings
    ) {
        $this->requestFactory = $requestFactory;
        $this->exitNodeListParser = $exitNodeListParser;
        $this->exitNodeRepository = $exitNodeRepository;
        $this->extensionSettings = $extensionSettings;
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setDescription('Downloads the Tor exit node list used to block frontend requests');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $listUrl = $this->extensionSettings->getListUrl();

        try {
            $response = $this->requestFactory->request($listUrl, 'GET', ['timeout' => self::REQUEST_TIMEOUT]);
        } catch (\Throwable $exception) {
            $io->error(sprintf('Could not download %s: %s. Keeping the current list.', $listUrl, $exception->getMessage()));

            return 1;
        }

        if ($response->getStatusCode() !== 200) {
            $io->error(sprintf('%s answered with status %d. Keeping the current list.', $listUrl, $response->getStatusCode()));

            return 1;
        }

        $addresses = $this->exitNodeListParser->parse((string)$response->getBody());
        $minimumEntries = $this->extensionSettings->getMinimumEntries();
        if (count($addresses) < $minimumEntries) {
            $io->error(sprintf(
                'Received %d addresses, expected at least %d. Keeping the current list.',
                count($addresses),
                $minimumEntries
            ));

            return 1;
        }

        $this->exitNodeRepository->replace($addresses);
        $io->success(sprintf('Stored %d Tor exit node addresses in %s.', count($addresses), $this->exitNodeRepository->getFilePath()));

        return 0;
    }
}
