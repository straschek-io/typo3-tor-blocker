<?php

declare(strict_types=1);
namespace StraschekIo\TorBlocker\Command;

use StraschekIo\TorBlocker\Configuration\ExtensionSettings;
use StraschekIo\TorBlocker\Download\DownloadFailedException;
use StraschekIo\TorBlocker\Download\ExitNodeListDownloader;
use StraschekIo\TorBlocker\Parser\ExitNodeListParser;
use StraschekIo\TorBlocker\Repository\ExitNodeRepository;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class UpdateCommand extends Command
{
    private ExitNodeListDownloader $exitNodeListDownloader;

    private ExitNodeListParser $exitNodeListParser;

    private ExitNodeRepository $exitNodeRepository;

    private ExtensionSettings $extensionSettings;

    public function __construct(
        ExitNodeListDownloader $exitNodeListDownloader,
        ExitNodeListParser $exitNodeListParser,
        ExitNodeRepository $exitNodeRepository,
        ExtensionSettings $extensionSettings
    ) {
        $this->exitNodeListDownloader = $exitNodeListDownloader;
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

        try {
            $list = $this->exitNodeListDownloader->download($this->extensionSettings->getListUrl());
        } catch (DownloadFailedException $exception) {
            $io->error($exception->getMessage() . ' Keeping the current list.');

            return 1;
        }

        $addresses = $this->exitNodeListParser->parse($list);
        $minimumEntries = $this->extensionSettings->getMinimumEntries();
        if (count($addresses) < $minimumEntries) {
            $io->error(sprintf(
                'Received %d addresses, expected at least %d. Keeping the current list.',
                count($addresses),
                $minimumEntries
            ));

            return 1;
        }

        try {
            $this->exitNodeRepository->replace($addresses);
        } catch (\RuntimeException $exception) {
            $io->error(sprintf('Could not store the list: %s Keeping the current list.', $exception->getMessage()));

            return 1;
        }
        $io->success(sprintf('Stored %d Tor exit node addresses in %s.', count($addresses), $this->exitNodeRepository->getFilePath()));

        return 0;
    }
}
