<?php

declare(strict_types=1);
namespace StraschekIo\TorBlocker\Tests\Unit\Command;

use PHPUnit\Framework\TestCase;
use StraschekIo\TorBlocker\Command\UpdateCommand;
use StraschekIo\TorBlocker\Configuration\ExtensionSettings;
use StraschekIo\TorBlocker\Network\IpAddressNormalizer;
use StraschekIo\TorBlocker\Parser\ExitNodeListParser;
use StraschekIo\TorBlocker\Repository\ExitNodeRepository;
use Symfony\Component\Console\Tester\CommandTester;
use TYPO3\CMS\Core\Http\RequestFactory;
use TYPO3\CMS\Core\Http\Response;

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
        $commandTester = $this->createCommandTester($this->createRequestFactory("192.0.2.1\n198.51.100.23\n"));

        $exitCode = $commandTester->execute([]);

        $repository = new ExitNodeRepository($this->storageDirectory);
        self::assertSame(0, $exitCode);
        self::assertTrue($repository->contains('192.0.2.1'));
        self::assertTrue($repository->contains('198.51.100.23'));
        self::assertStringContainsString('Stored 2 Tor exit node addresses', $commandTester->getDisplay());
    }

    public function testKeepsTheCurrentListWhenTooFewAddressesArrive(): void
    {
        (new ExitNodeRepository($this->storageDirectory))->replace(['203.0.113.7']);
        $commandTester = $this->createCommandTester($this->createRequestFactory("192.0.2.1\n"));

        $exitCode = $commandTester->execute([]);

        $repository = new ExitNodeRepository($this->storageDirectory);
        self::assertSame(1, $exitCode);
        self::assertTrue($repository->contains('203.0.113.7'));
        self::assertFalse($repository->contains('192.0.2.1'));
    }

    public function testKeepsTheCurrentListWhenTheDownloadFails(): void
    {
        (new ExitNodeRepository($this->storageDirectory))->replace(['203.0.113.7']);
        $requestFactory = $this->createMock(RequestFactory::class);
        $requestFactory->method('request')->willThrowException(new \RuntimeException('Connection refused'));
        $commandTester = $this->createCommandTester($requestFactory);

        $exitCode = $commandTester->execute([]);

        self::assertSame(1, $exitCode);
        self::assertTrue((new ExitNodeRepository($this->storageDirectory))->contains('203.0.113.7'));
    }

    public function testKeepsTheCurrentListOnAnErrorStatus(): void
    {
        (new ExitNodeRepository($this->storageDirectory))->replace(['203.0.113.7']);
        $commandTester = $this->createCommandTester($this->createRequestFactory("192.0.2.1\n198.51.100.23\n", 503));

        $exitCode = $commandTester->execute([]);

        self::assertSame(1, $exitCode);
        self::assertTrue((new ExitNodeRepository($this->storageDirectory))->contains('203.0.113.7'));
    }

    public function testReportsAFailureWhenTheListCannotBeStored(): void
    {
        // A file in the way of the storage directory
        file_put_contents($this->storageDirectory, '');
        $commandTester = $this->createCommandTester($this->createRequestFactory("192.0.2.1\n198.51.100.23\n"));

        $exitCode = $commandTester->execute([]);

        self::assertSame(1, $exitCode);
        self::assertStringContainsString('Could not store the list', $commandTester->getDisplay());
    }

    private function createCommandTester(RequestFactory $requestFactory): CommandTester
    {
        $extensionSettings = $this->createMock(ExtensionSettings::class);
        $extensionSettings->method('getListUrl')->willReturn('https://example.org/exit-nodes');
        $extensionSettings->method('getMinimumEntries')->willReturn(2);

        $command = new UpdateCommand(
            $requestFactory,
            new ExitNodeListParser(new IpAddressNormalizer()),
            new ExitNodeRepository($this->storageDirectory),
            $extensionSettings
        );

        return new CommandTester($command);
    }

    private function createRequestFactory(string $body, int $statusCode = 200): RequestFactory
    {
        $response = (new Response())->withStatus($statusCode);
        $response->getBody()->write($body);

        $requestFactory = $this->createMock(RequestFactory::class);
        $requestFactory->method('request')->willReturn($response);

        return $requestFactory;
    }
}
