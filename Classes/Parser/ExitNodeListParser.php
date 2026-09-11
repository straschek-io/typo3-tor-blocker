<?php

declare(strict_types=1);
namespace StraschekIo\TorBlocker\Parser;

class ExitNodeListParser
{
    /**
     * Extracts the valid IP addresses from a plain text list with one address per line.
     * Empty lines, comments and anything that is not an IP address are skipped.
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
            if (filter_var($line, FILTER_VALIDATE_IP) === false) {
                continue;
            }
            $addresses[$line] = true;
        }

        return array_map('strval', array_keys($addresses));
    }
}
