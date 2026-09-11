<?php

declare(strict_types=1);
namespace StraschekIo\TorBlocker\Tests\Unit\Command;

use PHPUnit\Framework\TestCase;
use StraschekIo\TorBlocker\Command\UpdateCommand;
use StraschekIo\TorBlocker\Configuration\ExtensionSettings;
use StraschekIo\TorBlocker\Download\DownloadFailedException;
use StraschekIo\TorBlocker\Download\ExitNodeListDownloader;
use StraschekIo\TorBlocker\Network\IpAddressNormalizer;
use StraschekIo\TorBlocker\Parser\ExitNodeListParser;
use StraschekIo\TorBlocker\Repository\ExitNodeRepository;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * @covers \StraschekIo\TorBlocker\Command\UpdateCommand
 */
final class UpdateCommandTest extends TestCase
{
    private string $storageDirectory;

    protected function setUp(): void
    {
        $this->storageDirectory = sys_get_temp_dir() . '/tor_blocker_test_' . bin2hex(random_bytes(6));
    }

    protected function tearDown(): void
    {
        foreach (glob($this->storageDirectory . '/*') ?: [] as $file) {
            unlink($file);
        }
        if (is_dir($this->storageDirectory)) {
            rmdir($this->storageDirectory);
        } elseif (is_file($this->storageDirectory)) {
            unlink($this->storageDirectory);
        }
    }

    public function testStoresTheDownloadedList(): void
    {
        $commandTester = $this->createCommandTester($this->createDownloader("192.0.2.1\n198.51.100.23\n"));

        $exitCode = $commandTester->execute([]);

        $repository = new ExitNodeRepository($this->storageDirectory);
        self::assertSame(0, $exitCode);
        self::assertTrue($repository->contains('192.0.2.1'));
        self::assertTrue($repository->contains('198.51.100.23'));
        self::assertStringContainsString('Stored 2 Tor exit node addresses', $commandTester->getDisplay());
    }

    public function testDownloadsTheConfiguredUrl(): void
    {
        $downloader = $this->createMock(ExitNodeListDownloader::class);
        $downloader->expects(self::once())->method('download')->with('https://example.org/exit-nodes')->willReturn("192.0.2.1\n198.51.100.23\n");

        self::assertSame(0, $this->createCommandTester($downloader)->execute([]));
    }

    public function testKeepsTheCurrentListWhenTooFewAddressesArrive(): void
    {
        (new ExitNodeRepository($this->storageDirectory))->replace(['203.0.113.7']);
        $commandTester = $this->createCommandTester($this->createDownloader("192.0.2.1\n"));

        $exitCode = $commandTester->execute([]);

        $repository = new ExitNodeRepository($this->storageDirectory);
        self::assertSame(1, $exitCode);
        self::assertTrue($repository->contains('203.0.113.7'));
        self::assertFalse($repository->contains('192.0.2.1'));
    }

    public function testKeepsTheCurrentListWhenTheDownloadFails(): void
    {
        (new ExitNodeRepository($this->storageDirectory))->replace(['203.0.113.7']);
        $downloader = $this->createMock(ExitNodeListDownloader::class);
        $downloader->method('download')->willThrowException(new DownloadFailedException('Connection refused.', 1789113610));
        $commandTester = $this->createCommandTester($downloader);

        $exitCode = $commandTester->execute([]);

        self::assertSame(1, $exitCode);
        self::assertStringContainsString('Connection refused. Keeping the current list.', $commandTester->getDisplay());
        self::assertTrue((new ExitNodeRepository($this->storageDirectory))->contains('203.0.113.7'));
    }

    public function testReportsAFailureWhenTheListCannotBeStored(): void
    {
        // A file in the way of the storage directory
        file_put_contents($this->storageDirectory, '');
        $commandTester = $this->createCommandTester($this->createDownloader("192.0.2.1\n198.51.100.23\n"));

        $exitCode = $commandTester->execute([]);

        self::assertSame(1, $exitCode);
        self::assertStringContainsString('Could not store the list', $commandTester->getDisplay());
    }

    private function createCommandTester(ExitNodeListDownloader $downloader): CommandTester
    {
        $command = new UpdateCommand(
            $downloader,
            new ExitNodeListParser(new IpAddressNormalizer()),
            new ExitNodeRepository($this->storageDirectory),
            new ExtensionSettings(['listUrl' => 'https://example.org/exit-nodes', 'minimumEntries' => '2'])
        );

        return new CommandTester($command);
    }

    private function createDownloader(string $list): ExitNodeListDownloader
    {
        $downloader = $this->createMock(ExitNodeListDownloader::class);
        $downloader->method('download')->willReturn($list);

        return $downloader;
    }
}
