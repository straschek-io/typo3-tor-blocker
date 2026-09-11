<?php

declare(strict_types=1);
namespace StraschekIo\TorBlocker\Tests\Unit\Repository;

use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use StraschekIo\TorBlocker\Repository\ExitNodeRepository;

/**
 * @covers \StraschekIo\TorBlocker\Repository\ExitNodeRepository
 */
final class ExitNodeRepositoryTest extends TestCase
{
    private string $storageDirectory;

    private int $umaskBackup;

    /**
     * @var mixed
     */
    private $typo3ConfVarsBackup;

    protected function setUp(): void
    {
        $this->storageDirectory = sys_get_temp_dir() . '/tor_blocker_test_' . bin2hex(random_bytes(6));
        $this->umaskBackup = umask();
        $this->typo3ConfVarsBackup = $GLOBALS['TYPO3_CONF_VARS'] ?? null;
        unset($GLOBALS['TYPO3_CONF_VARS']);
    }

    protected function tearDown(): void
    {
        umask($this->umaskBackup);
        $GLOBALS['TYPO3_CONF_VARS'] = $this->typo3ConfVarsBackup;
        foreach (glob($this->storageDirectory . '/*') ?: [] as $file) {
            unlink($file);
        }
        if (is_dir($this->storageDirectory)) {
            rmdir($this->storageDirectory);
        }
    }

    public function testContainsNothingWithoutStoredList(): void
    {
        $repository = new ExitNodeRepository($this->storageDirectory);

        self::assertFalse($repository->contains('192.0.2.1'));
        self::assertSame(0, $repository->count());
    }

    public function testStoredListIsReadByAFreshInstance(): void
    {
        (new ExitNodeRepository($this->storageDirectory))->replace(['192.0.2.1', '2001:db8::1']);

        $repository = new ExitNodeRepository($this->storageDirectory);

        self::assertTrue($repository->contains('192.0.2.1'));
        self::assertTrue($repository->contains('2001:db8::1'));
        self::assertFalse($repository->contains('198.51.100.23'));
        self::assertSame(2, $repository->count());
    }

    public function testReplaceOverwritesThePreviousList(): void
    {
        $repository = new ExitNodeRepository($this->storageDirectory);
        $repository->replace(['192.0.2.1']);
        $repository->replace(['198.51.100.23']);

        self::assertFalse($repository->contains('192.0.2.1'));
        self::assertTrue($repository->contains('198.51.100.23'));
        self::assertFalse((new ExitNodeRepository($this->storageDirectory))->contains('192.0.2.1'));
    }

    public function testReplaceLeavesNoTemporaryFileBehind(): void
    {
        $repository = new ExitNodeRepository($this->storageDirectory);
        $repository->replace(['192.0.2.1']);

        self::assertSame([$repository->getFilePath()], glob($this->storageDirectory . '/*'));
    }

    public function testReplaceAppliesTheDefaultPermissionsRegardlessOfTheUmask(): void
    {
        umask(077);
        $repository = new ExitNodeRepository($this->storageDirectory);

        $repository->replace(['192.0.2.1']);

        self::assertSame('2775', decoct(fileperms($this->storageDirectory) & 07777));
        self::assertSame('664', decoct(fileperms($repository->getFilePath()) & 0777));
    }

    public function testReplaceAppliesTheConfiguredCreateMasks(): void
    {
        umask(077);
        $GLOBALS['TYPO3_CONF_VARS']['SYS']['folderCreateMask'] = '0750';
        $GLOBALS['TYPO3_CONF_VARS']['SYS']['fileCreateMask'] = '0640';
        $repository = new ExitNodeRepository($this->storageDirectory);

        $repository->replace(['192.0.2.1']);

        self::assertSame('750', decoct(fileperms($this->storageDirectory) & 07777));
        self::assertSame('640', decoct(fileperms($repository->getFilePath()) & 0777));
    }

    public function testReplaceIgnoresInvalidCreateMasks(): void
    {
        umask(077);
        $GLOBALS['TYPO3_CONF_VARS']['SYS']['folderCreateMask'] = 'rwx';
        $GLOBALS['TYPO3_CONF_VARS']['SYS']['fileCreateMask'] = '';
        $repository = new ExitNodeRepository($this->storageDirectory);

        $repository->replace(['192.0.2.1']);

        self::assertSame('2775', decoct(fileperms($this->storageDirectory) & 07777));
        self::assertSame('664', decoct(fileperms($repository->getFilePath()) & 0777));
    }

    public function testReplaceFailsWhenTheDirectoryCannotBeCreated(): void
    {
        // A file in the way of the directory
        file_put_contents($this->storageDirectory, '');
        $repository = new ExitNodeRepository($this->storageDirectory);

        try {
            $this->expectException(\RuntimeException::class);
            $this->expectExceptionCode(1789113600);
            $repository->replace(['192.0.2.1']);
        } finally {
            unlink($this->storageDirectory);
        }
    }

    public function testBlocksNobodyWhenTheStoredListIsBroken(): void
    {
        $repository = new ExitNodeRepository($this->storageDirectory);
        $repository->replace(['192.0.2.1']);
        file_put_contents($repository->getFilePath(), '<?php return [broken');

        $brokenRepository = new ExitNodeRepository($this->storageDirectory);

        self::assertFalse($brokenRepository->contains('192.0.2.1'));
        self::assertSame(0, $brokenRepository->count());
    }

    public function testLogsAnErrorWhenTheStoredListIsBroken(): void
    {
        mkdir($this->storageDirectory);
        $repository = new ExitNodeRepository($this->storageDirectory);
        file_put_contents($repository->getFilePath(), '<?php return [broken');
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())->method('error');
        $repository->setLogger($logger);

        self::assertFalse($repository->contains('192.0.2.1'));
    }

    public function testBlocksNobodyWhenTheStoredListIsNoArray(): void
    {
        mkdir($this->storageDirectory);
        $repository = new ExitNodeRepository($this->storageDirectory);
        file_put_contents($repository->getFilePath(), '<?php return "192.0.2.1";');
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())->method('error');
        $repository->setLogger($logger);

        self::assertFalse($repository->contains('192.0.2.1'));
    }
}
