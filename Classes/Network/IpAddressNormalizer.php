<?php

declare(strict_types=1);
namespace StraschekIo\TorBlocker\Network;

/**
 * Brings IP addresses into one canonical form, so the stored list and the client address
 * compare as plain strings: lower case, compressed IPv6, and IPv4-mapped IPv6 addresses
 * (::ffff:192.0.2.1, as dual stack sockets report them) reduced to the IPv4 address.
 * Anything that is not an IP address becomes an empty string.
 */
class IpAddressNormalizer
{
    private const IPV4_MAPPED_PREFIX = "\0\0\0\0\0\0\0\0\0\0\xff\xff";

    public function normalize(string $address): string
    {
        $address = trim($address);
        if ($address === '' || filter_var($address, FILTER_VALIDATE_IP) === false) {
            return '';
        }

        $binary = inet_pton($address);
        if ($binary === false) {
            return '';
        }
        if (strlen($binary) === 16 && strpos($binary, self::IPV4_MAPPED_PREFIX) === 0) {
            $binary = substr($binary, strlen(self::IPV4_MAPPED_PREFIX));
        }

        $normalized = inet_ntop($binary);

        return $normalized === false ? '' : strtolower($normalized);
    }
}
