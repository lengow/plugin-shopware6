<?php declare(strict_types=1);

namespace Lengow\Connector\Service;

use Lengow\Connector\Components\LengowFile;
use Lengow\Connector\Factory\LengowFileFactory;
use Lengow\Connector\Util\EnvironmentInfoProvider;

/**
 * Class LengowLog
 * @package Lengow\Connector\Service
 */
class LengowLog
{
    /* Log category codes */
    public const CODE_INSTALL = 'Install';
    public const CODE_CONNECTION = 'Connection';
    public const CODE_SETTING = 'Setting';
    public const CODE_CONNECTOR = 'Connector';
    public const CODE_EXPORT = 'Export';
    public const CODE_IMPORT = 'Import';
    public const CODE_ACTION = 'Action';
    public const CODE_MAIL_REPORT = 'Mail Report';
    public const CODE_ORM = 'Orm';

    /* Log params for export */
    public const LOG_DATE = 'date';
    public const LOG_LINK = 'link';

    /**
     * @var int life of log files in days
     */
    private const LOG_LIFE = 20;

    /**
     * @var LengowTranslation Lengow translation service
     */
    private $lengowTranslation;

    /**
     * @var LengowConfiguration Lengow configuration service
     */
    private $lengowConfiguration;

    /**
     * @var EnvironmentInfoProvider Environment info provider utility
     */
    private $environmentInfoProvider;

    /**
     * @var LengowFileFactory Lengow file factory
     */
    private $lengowFileFactory;

    /**
     * @var LengowFile Lengow file instance
     */
    private $lengowFile;

    /**
     * LengowLog Construct
     *
     * @param LengowTranslation $lengowTranslation Lengow translation service
     * @param LengowConfiguration $lengowConfiguration Lengow configuration service
     * @param EnvironmentInfoProvider $environmentInfoProvider Environment info provider utility
     * @param LengowFileFactory $lengowFileFactory Lengow file factory
     */
    public function __construct(
        LengowTranslation $lengowTranslation,
        LengowConfiguration $lengowConfiguration,
        EnvironmentInfoProvider $environmentInfoProvider,
        LengowFileFactory $lengowFileFactory
    )
    {
        $this->lengowTranslation = $lengowTranslation;
        $this->lengowConfiguration = $lengowConfiguration;
        $this->environmentInfoProvider = $environmentInfoProvider;
        $this->lengowFileFactory = $lengowFileFactory;
    }

    /**
     * Encode message with params for translation
     *
     * @param string $key message key
     * @param array $params message parameters
     *
     * @return string
     */
    public function encodeMessage(string $key, array $params = []): string
    {
        if (empty($params)) {
            return $key;
        }
        $allParams = [];
        foreach ($params as $param => $value) {
            $value = str_replace(['|', '=='], ['', ''], $value);
            $allParams[] = $param . '==' . $value;
        }
        return $key . '[' . join('|', $allParams) . ']';
    }

    /**
     * Decode message with params for translation
     *
     * @param string $message key to translate
     * @param string|null $isoCode language translation iso code
     * @param array $params array parameters to display in the translation message
     *
     * @return string
     */
    public function decodeMessage(string $message, string $isoCode = null, array $params = []): string
    {
        if (preg_match('/^(([a-z\_]*\.){1,3}[a-z\_]*)(\[(.*)\]|)$/', $message, $result)) {
            if ($result[1] ?? false) {
                $key = $result[1];
                if (isset($result[4]) && empty($params)) {
                    $strParam = $result[4];
                    $allParams = explode('|', $strParam);
                    foreach ($allParams as $param) {
                        $result = explode('==', $param);
                        $params[$result[0]] = $result[1];
                    }
                }
                $message = $this->lengowTranslation->t($key, $params, $isoCode);
            }
        }
        return $message;
    }

    /**
     * Write log
     *
     * @param string $category log category
     * @param string $message log message
     * @param boolean $display display on screen
     * @param string|null $marketplaceSku Lengow order id
     */
    public function write(string $category, string $message = '', bool $display = false, $marketplaceSku = null): void
    {
        $decodedMessage = $this->decodeMessage($message, LengowTranslation::DEFAULT_ISO_CODE);
        $log = $this->lengowConfiguration->date();
        $log .= ' - ' . (empty($category) ? '' : '[' . $category . '] ');
        $log .= '' . (empty($marketplaceSku) ? '' : 'order ' . $marketplaceSku . ': ');
        $log .= $decodedMessage . "\r\n";
        if ($display) {
            echo $log . '<br />';
            flush();
        }
        // init new LengowFile for logging
        if ($this->lengowFile === null) {
            $this->lengowFile = $this->lengowFileFactory->create(
                EnvironmentInfoProvider::FOLDER_LOG,
                'logs-' . date('Y-m-d') . '.txt'
            );
        }
        $this->lengowFile->write($log);
    }

    /**
     * Delete log files when too old
     */
    public function cleanLog(): void
    {
        $days = ['logs-' . date('Y-m-d') . '.txt'];
        for ($i = 1; $i < self::LOG_LIFE; $i++) {
            $days[] = 'logs-' . date('Y-m-d', strtotime('-' . $i . 'day')) . '.txt';
        }
        $logFiles = $this->getFilesFromFolder();
        if (empty($logFiles)) {
            return;
        }
        foreach ($logFiles as $logFile) {
            if (!in_array($logFile->getFileName(), $days, true)) {
                $logFile->delete();
            }
        }
    }

    /**
     * Get all log file list for log folder
     *
     * @return LengowFile[]
     */
    public function getFilesFromFolder(): array
    {
        $files = [];
        $sep = DIRECTORY_SEPARATOR;
        $folderPath = $this->environmentInfoProvider->getPluginPath() . $sep . EnvironmentInfoProvider::FOLDER_LOG;
        if (file_exists($folderPath)) {
            $folderContent = scandir($folderPath);
            foreach ($folderContent as $fileName) {
                if (!preg_match('/^\.[a-zA-Z\.]+$|^\.$|index\.php/', $fileName)) {
                    $files[] = $this->lengowFileFactory->create(EnvironmentInfoProvider::FOLDER_LOG, $fileName);
                }
            }
        }
        return $files;
    }

    /**
     * Get log files path
     *
     * @return array
     */
    public function getPaths(): array
    {
        $logs = [];
        $files = $this->getFilesFromFolder();
        if (empty($files)) {
            return $logs;
        }
        foreach ($files as $file) {
            preg_match('/^logs-(\d{4}-\d{2}-\d{2})\.txt$/', $file->getFileName(), $match);
            $date = $match[1];
            if ($date) {
                $logs[] = [
                    self::LOG_DATE => $date,
                    self::LOG_LINK => $this->lengowConfiguration->getToolboxUrl()
                        . '&' . LengowToolbox::PARAM_TOOLBOX_ACTION . '=' . LengowToolbox::ACTION_LOG
                        . '&' . LengowToolbox::PARAM_DATE . '=' . urlencode($date),
                ];
            }
        }
        return array_reverse($logs);
    }

    /**
     * Download log file individually or globally
     *
     * @param string|null $date date for a specific log file
     */
    public function download($date = null): void
    {
        /** @var LengowFile[] $logFiles */
        if ($date && preg_match('/^(\d{4}-\d{2}-\d{2})$/', $date)) {
            $logFiles = [];
            $file = 'logs-' . $date . '.txt';
            $fileName = $date . '.txt';
            $sep = DIRECTORY_SEPARATOR;
            $filePath = $this->environmentInfoProvider->getPluginPath()
                . $sep . EnvironmentInfoProvider::FOLDER_LOG . $sep . $file;
            if (file_exists($filePath)) {
                $logFiles = [$this->lengowFileFactory->create(EnvironmentInfoProvider::FOLDER_LOG, $file)];
            }
        } else {
            $fileName = 'logs.txt';
            $logFiles = $this->getFilesFromFolder();
        }
        $contents = '';
        foreach ($logFiles as $logFile) {
            $filePath = $logFile->getPath();
            $handle = fopen($filePath, 'r');
            $fileSize = filesize($filePath);
            if ($fileSize > 0) {
                $contents .= fread($handle, $fileSize);
            }
        }
        header('Content-type: text/plain');
        header('Content-Disposition: attachment; filename="' . $fileName . '"');
        echo $contents;
    }
}
