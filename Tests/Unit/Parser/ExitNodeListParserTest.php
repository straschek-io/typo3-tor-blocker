<?php

declare(strict_types=1);
namespace StraschekIo\TorBlocker\Tests\Unit\Parser;

use PHPUnit\Framework\TestCase;
use StraschekIo\TorBlocker\Parser\ExitNodeListParser;

/**
 * @covers \StraschekIo\TorBlocker\Parser\ExitNodeListParser
 */
final class ExitNodeListParserTest extends TestCase
{
    private ExitNodeListParser $parser;

    protected function setUp(): void
    {
        $this->parser = new ExitNodeListParser();
    }

    public function testReturnsAddressesInOrder(): void
    {
        self::assertSame(
            ['192.0.2.1', '198.51.100.23', '203.0.113.42'],
            $this->parser->parse("192.0.2.1\n198.51.100.23\n203.0.113.42\n")
        );
    }

    public function testSkipsEmptyLinesCommentsAndInvalidEntries(): void
    {
        $list = "# exit nodes\n\n192.0.2.1\nnot-an-address\n999.1.1.1\n  198.51.100.23  \n";

        self::assertSame(['192.0.2.1', '198.51.100.23'], $this->parser->parse($list));
    }

    public function testRemovesDuplicates(): void
    {
        self::assertSame(['192.0.2.1'], $this->parser->parse("192.0.2.1\n192.0.2.1\n"));
    }

    public function testAcceptsIpv6AndWindowsLineEndings(): void
    {
        self::assertSame(['192.0.2.1', '2001:db8::1'], $this->parser->parse("192.0.2.1\r\n2001:db8::1\r\n"));
    }

    public function testReturnsNothingForAnHtmlErrorPage(): void
    {
        self::assertSame([], $this->parser->parse("<!DOCTYPE html>\n<html><body>Service unavailable</body></html>\n"));
    }
}
