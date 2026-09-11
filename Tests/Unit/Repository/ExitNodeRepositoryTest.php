<?php

declare(strict_types=1);
namespace StraschekIo\TorBlocker\Tests\Unit\Repository;

use PHPUnit\Framework\TestCase;
use StraschekIo\TorBlocker\Repository\ExitNodeRepository;

/**
 * @covers \StraschekIo\TorBlocker\Repository\ExitNodeRepository
 */
final class ExitNodeRepositoryTest extends TestCase
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

    public function testBlocksNobodyWhenTheStoredListIsBroken(): void
    {
        $repository = new ExitNodeRepository($this->storageDirectory);
        $repository->replace(['192.0.2.1']);
        file_put_contents($repository->getFilePath(), '<?php return [broken');

        $brokenRepository = new ExitNodeRepository($this->storageDirectory);

        self::assertFalse($brokenRepository->contains('192.0.2.1'));
        self::assertSame(0, $brokenRepository->count());
    }

    public function testBlocksNobodyWhenTheStoredListIsNoArray(): void
    {
        mkdir($this->storageDirectory);
        $repository = new ExitNodeRepository($this->storageDirectory);
        file_put_contents($repository->getFilePath(), '<?php return "192.0.2.1";');

        self::assertFalse($repository->contains('192.0.2.1'));
    }
}
