<?php

declare(strict_types=1);
namespace StraschekIo\TorBlocker\Tests\Unit\Network;

use PHPUnit\Framework\TestCase;
use StraschekIo\TorBlocker\Network\IpAddressNormalizer;

/**
 * @covers \StraschekIo\TorBlocker\Network\IpAddressNormalizer
 */
final class IpAddressNormalizerTest extends TestCase
{
    /**
     * @dataProvider addressProvider
     */
    public function testNormalizesAddresses(string $address, string $expected): void
    {
        self::assertSame($expected, (new IpAddressNormalizer())->normalize($address));
    }

    /**
     * @return array<string, array{string, string}>
     */
    public function addressProvider(): array
    {
        return [
            'ipv4' => ['192.0.2.1', '192.0.2.1'],
            'ipv4 with surrounding whitespace' => ["  192.0.2.1\t", '192.0.2.1'],
            'ipv4 mapped ipv6' => ['::ffff:192.0.2.1', '192.0.2.1'],
            'ipv4 mapped ipv6 upper case' => ['::FFFF:192.0.2.1', '192.0.2.1'],
            'ipv4 mapped ipv6 in hex notation' => ['::ffff:c000:201', '192.0.2.1'],
            'ipv6 compressed' => ['2001:db8::1', '2001:db8::1'],
            'ipv6 upper case' => ['2001:DB8::1', '2001:db8::1'],
            'ipv6 expanded' => ['2001:0db8:0000:0000:0000:0000:0000:0001', '2001:db8::1'],
            'ipv6 partially compressed' => ['2001:db8:0:0:0:0:0:1', '2001:db8::1'],
            'ipv6 loopback' => ['::1', '::1'],
            'empty' => ['', ''],
            'not an address' => ['not-an-address', ''],
            'ipv4 out of range' => ['999.1.1.1', ''],
            'ipv6 with zone' => ['fe80::1%eth0', ''],
            'ipv4 with port' => ['192.0.2.1:80', ''],
            'html' => ['<html>', ''],
        ];
    }
}
