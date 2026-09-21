<?php

declare(strict_types=1);
namespace StraschekIo\TorBlocker\Repository;

use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use TYPO3\CMS\Core\Core\Environment;

/**
 * Stores the exit node addresses as a PHP array file, so opcache keeps the list in memory
 * and a lookup is a single isset(). A missing or broken file blocks nobody, but is logged.
 *
 * The file is written by the CLI and read by the web server process, so the directory and
 * the file get the permissions configured in TYPO3 (folderCreateMask, fileCreateMask,
 * createGroup) regardless of the umask of the CLI user.
 */
class ExitNodeRepository implements LoggerAwareInterface
{
    use LoggerAwareTrait;

    private const FILE_NAME = 'exit-nodes.php';

    private const DEFAULT_FOLDER_CREATE_MASK = '2775';

    private const DEFAULT_FILE_CREATE_MASK = '0664';

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
     * Timestamp of the last successful update, null without a stored list.
     */
    public function getLastModified(): ?int
    {
        clearstatcache(true, $this->getFilePath());
        $lastModified = is_file($this->getFilePath()) ? filemtime($this->getFilePath()) : false;

        return $lastModified === false ? null : $lastModified;
    }

    /**
     * @param string[] $addresses
     */
    public function replace(array $addresses): void
    {
        $this->createStorageDirectory();

        $indexedAddresses = array_fill_keys($addresses, true);
        $content = '<?php' . "\n\n" . 'return ' . var_export($indexedAddresses, true) . ';' . "\n";

        // Write to a temporary file first, so readers never see a half written list
        $temporaryFile = $this->getFilePath() . '.' . getmypid() . '.tmp';
        if (file_put_contents($temporaryFile, $content) === false) {
            throw new \RuntimeException(sprintf('Could not write "%s".', $temporaryFile), 1789113601);
        }
        $this->applyPermissions($temporaryFile, self::DEFAULT_FILE_CREATE_MASK, 'fileCreateMask');
        if (!rename($temporaryFile, $this->getFilePath())) {
            @unlink($temporaryFile);
            throw new \RuntimeException(sprintf('Could not replace "%s".', $this->getFilePath()), 1789113602);
        }
        if (!is_readable($this->getFilePath())) {
            throw new \RuntimeException(
                sprintf('"%s" was written but is not readable, check its owner and permissions.', $this->getFilePath()),
                1789113603
            );
        }
        // Only affects the opcache of this process; the web server revalidates the file by its
        // modification time, unless opcache.validate_timestamps is disabled there
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
                $this->logLoadFailure($filePath, $exception->getMessage());
                $addresses = [];
            }
            if (!is_array($addresses)) {
                $this->logLoadFailure($filePath, 'The file does not return an array.');
                $addresses = [];
            }
        }
        $this->addresses = $addresses;

        return $this->addresses;
    }

    private function logLoadFailure(string $filePath, string $reason): void
    {
        if ($this->logger !== null) {
            $this->logger->error('The stored Tor exit node list could not be loaded, blocking nobody.', [
                'file' => $filePath,
                'reason' => $reason,
            ]);
        }
    }

    private function createStorageDirectory(): void
    {
        // mkdir() is subject to the umask, the chmod() in applyPermissions() is not
        if (!is_dir($this->storageDirectory) && !@mkdir($this->storageDirectory, 0777, true) && !is_dir($this->storageDirectory)) {
            throw new \RuntimeException(sprintf('Could not create directory "%s".', $this->storageDirectory), 1789113600);
        }
        $this->applyPermissions($this->storageDirectory, self::DEFAULT_FOLDER_CREATE_MASK, 'folderCreateMask');
    }

    /**
     * Applies the create mask and group configured in $GLOBALS['TYPO3_CONF_VARS']['SYS'],
     * like GeneralUtility::fixPermissions() does for paths inside the project.
     */
    private function applyPermissions(string $path, string $defaultMask, string $maskSettingName): void
    {
        $mask = (string)($GLOBALS['TYPO3_CONF_VARS']['SYS'][$maskSettingName] ?? '');
        if (!preg_match('/^[0-7]{3,4}$/', $mask)) {
            $mask = $defaultMask;
        }
        @chmod($path, (int)octdec($mask));

        $group = (string)($GLOBALS['TYPO3_CONF_VARS']['SYS']['createGroup'] ?? '');
        if ($group !== '') {
            @chgrp($path, $group);
        }
    }
}
