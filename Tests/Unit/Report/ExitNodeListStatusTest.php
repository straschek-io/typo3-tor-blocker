<?php

declare(strict_types=1);
namespace StraschekIo\TorBlocker\Tests\Unit\Report;

use PHPUnit\Framework\TestCase;
use StraschekIo\TorBlocker\Report\ExitNodeListStatus;
use StraschekIo\TorBlocker\Repository\ExitNodeRepository;
use TYPO3\CMS\Reports\Status;

/**
 * @covers \StraschekIo\TorBlocker\Report\ExitNodeListStatus
 */
final class ExitNodeListStatusTest extends TestCase
{
    private const SEVERITY_NAMES = [-2 => 'NOTICE', -1 => 'INFO', 0 => 'OK', 1 => 'WARNING', 2 => 'ERROR'];

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
        }
    }

    public function testWarnsWithoutAStoredList(): void
    {
        $status = $this->getListStatus(new ExitNodeRepository($this->storageDirectory));

        self::assertSame('WARNING', $this->getSeverityName($status));
        self::assertSame('No list stored', $status->getValue());
        self::assertStringContainsString('torblocker:update', $status->getMessage());
    }

    public function testIsOkayWithAFreshList(): void
    {
        $repository = new ExitNodeRepository($this->storageDirectory);
        $repository->replace(['192.0.2.1', '198.51.100.23']);

        $status = $this->getListStatus(new ExitNodeRepository($this->storageDirectory));

        self::assertSame('OK', $this->getSeverityName($status));
        self::assertSame('2 addresses, updated 0 hours ago', $status->getValue());
        self::assertSame('', $status->getMessage());
    }

    public function testWarnsWhenTheListIsOutdated(): void
    {
        $repository = new ExitNodeRepository($this->storageDirectory);
        $repository->replace(['192.0.2.1']);
        touch($repository->getFilePath(), time() - 30 * 3600);

        $status = $this->getListStatus(new ExitNodeRepository($this->storageDirectory));

        self::assertSame('WARNING', $this->getSeverityName($status));
        self::assertSame('1 addresses, updated 30 hours ago', $status->getValue());
        self::assertStringContainsString('scheduler task or cron job', $status->getMessage());
    }

    public function testReportsAnErrorWhenTheListCannotBeLoaded(): void
    {
        mkdir($this->storageDirectory);
        $repository = new ExitNodeRepository($this->storageDirectory);
        file_put_contents($repository->getFilePath(), '<?php return [broken');

        $status = $this->getListStatus($repository);

        self::assertSame('ERROR', $this->getSeverityName($status));
        self::assertStringContainsString($repository->getFilePath(), $status->getMessage());
    }

    public function testHasALabelForTheReportsModule(): void
    {
        self::assertSame('Tor Blocker', (new ExitNodeListStatus(new ExitNodeRepository($this->storageDirectory)))->getLabel());
    }

    private function getListStatus(ExitNodeRepository $repository): Status
    {
        $statuses = (new ExitNodeListStatus($repository))->getStatus();

        self::assertArrayHasKey('exitNodeList', $statuses);
        self::assertSame('Tor exit node list', $statuses['exitNodeList']->getTitle());

        return $statuses['exitNodeList'];
    }

    /**
     * The severity is an integer up to TYPO3 12 and an enum from TYPO3 12 on.
     */
    private function getSeverityName(Status $status): string
    {
        $severity = $status->getSeverity();

        return is_int($severity) ? self::SEVERITY_NAMES[$severity] : $severity->name;
    }
}
