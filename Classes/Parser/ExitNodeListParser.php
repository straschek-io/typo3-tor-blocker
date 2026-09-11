<?php

declare(strict_types=1);
namespace StraschekIo\TorBlocker\Parser;

use StraschekIo\TorBlocker\Network\IpAddressNormalizer;

class ExitNodeListParser
{
    private IpAddressNormalizer $ipAddressNormalizer;

    public function __construct(IpAddressNormalizer $ipAddressNormalizer)
    {
        $this->ipAddressNormalizer = $ipAddressNormalizer;
    }

    /**
     * Extracts the valid IP addresses from a plain text list with one address per line.
     * Empty lines, comments and anything that is not an IP address are skipped, the
     * addresses are normalized, so different spellings of one address count once.
     *
     * @param string $list
     * @return string[]
     */
    public function parse(string $list): array
    {
        $addresses = [];
        foreach (preg_split('/\R/', $list) ?: [] as $line) {
            $line = trim($line);
            if ($line === '' || strpos($line, '#') === 0) {
                continue;
            }
            $address = $this->ipAddressNormalizer->normalize($line);
            if ($address === '') {
                continue;
            }
            $addresses[$address] = true;
        }

        return array_map('strval', array_keys($addresses));
    }
}
