<?php

declare(strict_types=1);
namespace StraschekIo\TorBlocker\Repository;

use TYPO3\CMS\Core\Core\Environment;

/**
 * Stores the exit node addresses as a PHP array file, so opcache keeps the list in memory
 * and a lookup is a single isset(). A missing or broken file blocks nobody.
 */
class ExitNodeRepository
{
    private const FILE_NAME = 'exit-nodes.php';

    private string $storageDirectory;

    /**
     * @var array<string, bool>|null
     */
    private ?array $addresses = null;

    public function __construct(?string $storageDirectory = null)
    {
        $this->storageDirectory = $storageDirectory ?? Environment::getVarPath() . '/tor_blocker';
    }

    public function contains(string $address): bool
    {
        return isset($this->load()[$address]);
    }

    public function count(): int
    {
        return count($this->load());
    }

    public function getFilePath(): string
    {
        return $this->storageDirectory . '/' . self::FILE_NAME;
    }

    /**
     * @param string[] $addresses
     */
    public function replace(array $addresses): void
    {
        if (!is_dir($this->storageDirectory) && !mkdir($this->storageDirectory, 0775, true) && !is_dir($this->storageDirectory)) {
            throw new \RuntimeException(sprintf('Could not create directory "%s".', $this->storageDirectory), 1789113600);
        }

        $indexedAddresses = array_fill_keys($addresses, true);
        $content = '<?php' . "\n\n" . 'return ' . var_export($indexedAddresses, true) . ';' . "\n";

        // Write to a temporary file first, so readers never see a half written list
        $temporaryFile = $this->getFilePath() . '.' . getmypid() . '.tmp';
        if (file_put_contents($temporaryFile, $content) === false) {
            throw new \RuntimeException(sprintf('Could not write "%s".', $temporaryFile), 1789113601);
        }
        @chmod($temporaryFile, 0664);
        if (!rename($temporaryFile, $this->getFilePath())) {
            @unlink($temporaryFile);
            throw new \RuntimeException(sprintf('Could not replace "%s".', $this->getFilePath()), 1789113602);
        }
        if (function_exists('opcache_invalidate')) {
            @opcache_invalidate($this->getFilePath(), true);
        }

        $this->addresses = $indexedAddresses;
    }

    /**
     * @return array<string, bool>
     */
    private function load(): array
    {
        if ($this->addresses !== null) {
            return $this->addresses;
        }

        $addresses = [];
        $filePath = $this->getFilePath();
        if (is_file($filePath)) {
            try {
                $addresses = include $filePath;
            } catch (\Throwable $exception) {
                $addresses = [];
            }
        }
        $this->addresses = is_array($addresses) ? $addresses : [];

        return $this->addresses;
    }
}
