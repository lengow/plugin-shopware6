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
    /**
     * @var string install log code
     */
    public const CODE_INSTALL = 'Install';

    /**
     * @var string setting log code
     */
    public const CODE_SETTING = 'Setting';

    /**
     * @var string connector log code
     */
    public const CODE_CONNECTOR = 'Connector';

    /**
     * @var string export log code
     */
    public const CODE_EXPORT = 'Export';

    /**
     * @var string import log code
     */
    public const CODE_IMPORT = 'Import';

    /**
     * @var string action log code
     */
    public const CODE_ACTION = 'Action';

    /**
     * @var string mail report code
     */
    public const CODE_MAIL_REPORT = 'Mail Report';

    /**
     * @var string orm code
     */
    public const CODE_ORM = 'Orm';

    /**
     * @var string name of logs folder
     */
    private const LOG_FOLDER_NAME = 'Logs';

    /**
     * @var int life of log files in days
     */
    private const LOG_LIFE = 20;

    /**
     * @var LengowTranslation Lengow translation service
     */
    private $lengowTranslation;

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
     * @param EnvironmentInfoProvider $environmentInfoProvider Environment info provider utility
     * @param LengowFileFactory $lengowFileFactory Lengow file factory
     */
    public function __construct(
        LengowTranslation $lengowTranslation,
        EnvironmentInfoProvider $environmentInfoProvider,
        LengowFileFactory $lengowFileFactory
    )
    {
        $this->lengowTranslation = $lengowTranslation;
        $this->environmentInfoProvider = $environmentInfoProvider;
        $this->lengowFileFactory = $lengowFileFactory;
        // init new LengowFile for logging
        $this->lengowFile = $this->lengowFileFactory->create(self::LOG_FOLDER_NAME, 'logs-' . date('Y-m-d') . '.txt');
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
        $log = date('Y-m-d H:i:s');
        $log .= ' - ' . (empty($category) ? '' : '[' . $category . '] ');
        $log .= '' . (empty($marketplaceSku) ? '' : 'order ' . $marketplaceSku . ': ');
        $log .= $decodedMessage . "\r\n";
        if ($display) {
            echo $log . '<br />';
            flush();
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
        /** @var LengowFile[] $logFiles */
        $logFiles = $this->getFilesFromFolder();
        if (empty($logFiles)) {
            return;
        }
        foreach ($logFiles as $logFile) {
            if (!in_array($logFile->getFileName(), $days)) {
                $logFile->delete();
            }
        }
    }

    /**
     * Get all log file list for log folder
     *
     * @return array
     */
    public function getFilesFromFolder(): array
    {
        $files = [];
        $sep = DIRECTORY_SEPARATOR;
        $folderPath = $this->environmentInfoProvider->getPluginPath() . $sep . self::LOG_FOLDER_NAME;
        if (file_exists($folderPath)) {
            $folderContent = scandir($folderPath);
            foreach ($folderContent as $fileName) {
                if (!preg_match('/^\.[a-zA-Z\.]+$|^\.$|index\.php/', $fileName)) {
                    $files[] = $this->lengowFileFactory->create(self::LOG_FOLDER_NAME, $fileName);
                }
            }
        }
        return $files;
    }

    /**
     * Download log file individually or globally
     *
     * @param string|null $file name of file to download
     */
    public function download($file = null): void
    {
        if ($file && preg_match('/^logs-([0-9]{4}-[0-9]{2}-[0-9]{2})\.txt$/', $file, $match)) {
            $sep = DIRECTORY_SEPARATOR;
            $filename = $this->environmentInfoProvider->getPluginPath() . $sep . self::LOG_FOLDER_NAME . '/' . $file;
            $handle = fopen($filename, 'r');
            $contents = fread($handle, filesize($filename));
            header('Content-type: text/plain');
            header('Content-Disposition: attachment; filename="' . $match[1] . '.txt"');
            echo $contents;
            exit();
        } else {
            /** @var LengowFile[] $logFiles */
            $logFiles = $this->getFilesFromFolder();
            header('Content-type: text/plain');
            header('Content-Disposition: attachment; filename="logs.txt"');
            foreach ($logFiles as $logFile) {
                $filePath = $logFile->getPath();
                $handle = fopen($filePath, 'r');
                $contents = fread($handle, filesize($filePath));
                echo $contents;
            }
            exit();
        }
    }
}
