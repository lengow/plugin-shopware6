<?php declare(strict_types=1);

namespace Lengow\Connector\Components;

use Lengow\Connector\Util\EnvironmentInfoProvider;

/**
 * Class LengowFile
 * @package Lengow\Connector\Components
 */
class LengowFile
{
    /**
     * @var EnvironmentInfoProvider Environment info provider utility
     */
    private $environmentInfoProvider;

    /**
     * @var string folder name that contains the file
     */
    private $folderName;

    /**
     * @var string file name
     */
    private $fileName;

    /**
     * @var string type of access
     */
    private $mode;

    /**
     * @var string file path
     */
    private $path;

    /**
     * @var string file link
     */
    private $link;

    /**
     * @var resource a file pointer resource
     */
    private $fileInstance;

    /**
     * LengowFile Construct
     *
     * @param string $folderName Lengow folder name
     * @param string|null $fileName Lengow file name
     * @param string $mode type of access
     * @param EnvironmentInfoProvider $environmentInfoProvider Environment info provider utility
     */
    public function __construct(
        string $folderName,
        string $fileName,
        string $mode,
        EnvironmentInfoProvider $environmentInfoProvider
    )
    {
        $this->fileName = $fileName;
        $this->folderName = $folderName;
        $this->mode = $mode;
        $this->environmentInfoProvider = $environmentInfoProvider;
        $this->fileInstance = $this->getFileResource($this->getPath(), $this->mode);
    }

    /**
     * Write content in file
     *
     * @param string $txt text to be written
     */
    public function write(string $txt): void
    {
        if (!$this->fileInstance) {
            $this->fileInstance = fopen($this->getPath(), $this->mode);
        }
        fwrite($this->fileInstance, $txt);
    }

    /**
     * Delete file
     */
    public function delete(): void
    {
        if ($this->exists()) {
            if ($this->fileInstance) {
                $this->close();
            }
            unlink($this->getPath());
        }
    }

    /**
     * Get file name
     *
     * @return string
     */
    public function getFileName(): string
    {
        return $this->fileName;
    }

    /**
     * Get file link
     *
     * @return string
     */
    public function getLink(): string
    {
        if (empty($this->link)) {
            if (!$this->exists()) {
                $this->link = null;
            }
            $sep = DIRECTORY_SEPARATOR;
            $base = $this->environmentInfoProvider->getBaseUrl() ?? '';
            $lengowDir = $this->environmentInfoProvider->getPluginDir();
            $this->link = $base . $lengowDir . $sep  . $this->folderName . $sep . $this->fileName;
        }
        return $this->link;
    }

    /**
     * Get file path
     *
     * @return string
     */
    public function getPath(): string
    {
        if (empty($this->path)) {
            $sep = DIRECTORY_SEPARATOR;
            $this->path = $this->environmentInfoProvider->getPluginPath()
                . $sep . $this->folderName . $sep . $this->fileName;
        }
        return $this->path;
    }

    /**
     * Get folder path of current file
     *
     * @return string
     */
    public function getFolderPath(): string
    {
        $sep = DIRECTORY_SEPARATOR;
        return $this->environmentInfoProvider->getPluginPath() . $sep  . $this->folderName;
    }

    /**
     * Rename file
     *
     * @param string $newName new file name
     *
     * @return bool
     */
    public function rename(string $newName): bool
    {
        return rename($this->getPath(), $newName);
    }

    /**
     * Close file handle
     */
    public function close(): void
    {
        if (is_resource($this->fileInstance)) {
            fclose($this->fileInstance);
        }
    }

    /**
     * Check if current file exists
     *
     * @return bool
     */
    private function exists(): bool
    {
        return file_exists($this->getPath());
    }

    /**
     * Get file resource of a given stream
     *
     * @param string $path path to the file
     * @param string $mode type of access
     *
     * @return resource|false
     */
    private function getFileResource(string $path, string $mode)
    {
        return fopen($path, $mode);
    }
}
