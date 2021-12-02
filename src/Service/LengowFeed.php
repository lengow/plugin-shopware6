<?php declare(strict_types=1);

namespace Lengow\Connector\Service;

use Lengow\Connector\Components\LengowFile;
use Lengow\Connector\Factory\LengowFileFactory;
use Lengow\Connector\Util\EnvironmentInfoProvider;
use Lengow\Connector\Util\StringCleaner;
use Lengow\Connector\Exception\LengowException;

/**
 * Class LengowFeed
 * @package Lengow\Connector\Service
 */
class LengowFeed
{
    /* Feed formats */
    public const FORMAT_CSV = 'csv';
    public const FORMAT_YAML = 'yaml';
    public const FORMAT_XML = 'xml';
    public const FORMAT_JSON = 'json';

    /* Content types */
    public const HEADER = 'header';
    public const BODY = 'body';
    public const FOOTER = 'footer';

    /**
     * @var string CSV Protection
     */
    public const PROTECTION = '"';

    /**
     * @var string CSV separator
     */
    public const CSV_SEPARATOR = '|';

    /**
     * @var string end of line
     */
    public const EOL = "\r\n";

    /**
     * @var bool export is stream or file
     */
    private $stream;

    /**
     * @var string export format
     */
    private $format;

    /**
     * @var string salesChannel Id to export
     */
    private $salesChannelId;

    /**
     * @var LengowFileFactory Lengow file factory
     */
    private $lengowFileFactory;

    /**
     * @var LengowFile Lengow file instance
     */
    private $lengowFile;

    /**
     * @var EnvironmentInfoProvider Lengow environment info provider
     */
    private $environmentInfoProvider;

    /**
     * @var LengowLog Lengow log instance
     */
    private $lengowLog;

    /**
     * @var array formats available for export
     */
    public static $availableFormats = [
        self::FORMAT_CSV,
        self::FORMAT_YAML,
        self::FORMAT_XML,
        self::FORMAT_JSON,
    ];

    /**
     * LengowFeed constructor
     *
     * @param LengowFileFactory $lengowFileFactory
     * @param EnvironmentInfoProvider $environmentInfoProvider
     * @param LengowLog $lengowLog
     */
    public function __construct(
        LengowFileFactory $lengowFileFactory,
        EnvironmentInfoProvider $environmentInfoProvider,
        LengowLog $lengowLog
    )
    {
        $this->lengowFileFactory = $lengowFileFactory;
        $this->environmentInfoProvider = $environmentInfoProvider;
        $this->lengowLog = $lengowLog;
    }

    /**
     * Init LengowFeed service
     *
     * @param string $salesChannelId salesChannelId of the sales channel to export
     * @param bool $stream false = fileMode | true = streamMode
     * @param string $format export format
     * @throws LengowException
     */
    public function init(string $salesChannelId, bool $stream = false, string $format = self::FORMAT_CSV) : void
    {
        $this->stream = $stream;
        $this->format = $format;
        $this->salesChannelId = $salesChannelId;
        if (!$stream) {
            $this->initExportFile();
        }
    }

    /**
     * Create export file
     *
     * @throws LengowException
     */
    public function initExportFile() : void
    {
        $sep = DIRECTORY_SEPARATOR;
        $folderPath  = $this->environmentInfoProvider->getPluginPath() . $sep . EnvironmentInfoProvider::FOLDER_EXPORT;
        if (!file_exists($folderPath) && !mkdir($folderPath) && !is_dir($folderPath)) {
            throw new LengowException(
                $this->lengowLog->encodeMessage('log.export.error_unable_to_create_folder', [
                    'folder_path' => $folderPath,
                ])
            );
        }
        $fileName = $this->salesChannelId . '-flux-' . time() . '.' . $this->format;
        $this->lengowFile = $this->lengowFileFactory->create(EnvironmentInfoProvider::FOLDER_EXPORT, $fileName);
    }

    /**
     * Write data in file
     *
     * @param string $type data type (header, body or footer)
     * @param array $data export data
     * @param bool $isFirst is first product
     */
    public function write(string $type, array $data = [], bool $isFirst = false): void
    {
        switch ($type) {
            case self::HEADER:
                if ($this->stream) {
                    header($this->getHtmlHeader());
                    if ($this->format === self::FORMAT_CSV) {
                        header('Content-Disposition: attachment; filename=feed.csv');
                    }
                }
                $header = $this->getHeader($data);
                $this->flush($header);
                break;
            case self::BODY:
                $body = $this->getBody($data, $isFirst);
                $this->flush($body);
                break;
            case self::FOOTER:
                $footer = $this->getFooter();
                $this->flush($footer);
                break;
        }
    }

    /**
     * Finalize export generation
     *
     * @return bool
     */
    public function end() : bool
    {
        $this->write(self::FOOTER);
        if (!$this->stream) {
            $oldFileName = $this->salesChannelId . '-flux.' . $this->format;
            $oldFile = $this->lengowFileFactory->create(EnvironmentInfoProvider::FOLDER_EXPORT, $oldFileName);
            if ($oldFile->exists()) {
                $oldFilePath = $oldFile->getPath();
                $oldFile->delete();
            }
            if (isset($oldFilePath)) {
                $rename = $this->lengowFile->rename($oldFilePath);
                $this->lengowFile->setFileName($oldFileName);
            } else {
                $sep = DIRECTORY_SEPARATOR;
                $rename = $this->lengowFile->rename($this->lengowFile->getFolderPath() . $sep . $oldFileName);
                $this->lengowFile->setFileName($oldFileName);
            }
            return $rename;
        }
        return true;
    }

    /**
     * Get export generated file path
     *
     * @return string
     */
    public function getExportFilePath() : string
    {
        $sep = DIRECTORY_SEPARATOR;
        return $this->lengowFile->getFolderPath() . $sep . $this->lengowFile->getFileName();
    }

    /**
     * Flush feed content
     *
     * @param string $content feed content to be flushed
     */
    public function flush(string $content) : void
    {
        if ($this->stream) {
            echo $content;
            flush();
        } else {
            $this->lengowFile->write($content);
        }
    }


    /**
     * Return HTML header according to the given format
     *
     * @return string
     */
    protected function getHtmlHeader() : string
    {
        switch ($this->format) {
            case self::FORMAT_CSV:
            default:
                return 'Content-Type: text/csv; charset=UTF-8';
            case self::FORMAT_XML:
                return 'Content-Type: application/xml; charset=UTF-8';
            case self::FORMAT_JSON:
                return 'Content-Type: application/json; charset=UTF-8';
            case self::FORMAT_YAML:
                return 'Content-Type: text/x-yaml; charset=UTF-8';
        }
    }

    /**
     * Return feed header
     *
     * @param array $data export data
     *
     * @return string
     */
    protected function getHeader(array $data) : string
    {
        switch ($this->format) {
            case self::FORMAT_CSV:
            default:
                $header = '';
                foreach ($data as $field) {
                    $header .= self::PROTECTION . self::formatFields($field, self::FORMAT_CSV)
                        . self::PROTECTION . self::CSV_SEPARATOR;
                }
                return rtrim($header, self::CSV_SEPARATOR) . self::EOL;
            case self::FORMAT_XML:
                return '<?xml version="1.0" encoding="UTF-8"?>' . self::EOL
                    . '<catalog>' . self::EOL;
            case self::FORMAT_JSON:
                return '{"catalog":[';
            case self::FORMAT_YAML:
                return '"catalog":' . self::EOL;
        }
    }

    /**
     * GetBody
     *
     * @param array $data data to export as body
     * @param bool $isFirst is first product
     *
     * @return string
     */
    protected function getBody(array $data, bool $isFirst) : string
    {
        switch ($this->format) {
            case self::FORMAT_CSV:
            default:
                $content = '';
                foreach ($data as $value) {
                    $content .= self::PROTECTION . $value . self::PROTECTION . self::CSV_SEPARATOR;
                }
                return rtrim($content, self::CSV_SEPARATOR) . self::EOL;
            case self::FORMAT_XML:
                $content = '<product>';
                foreach ($data as $field => $value) {
                    $field = self::formatFields($field, self::FORMAT_XML);
                    $content .= '<' . $field . '><![CDATA[' . $value . ']]></' . $field . '>' . self::EOL;
                }
                $content .= '</product>' . self::EOL;
                return $content;
            case self::FORMAT_JSON:
                $content = $isFirst ? '' : ',';
                $jsonArray = array();
                foreach ($data as $field => $value) {
                    $field = self::formatFields($field, self::FORMAT_JSON);
                    $jsonArray[$field] = $value;
                }
                $content .= json_encode($jsonArray);
                return $content;
            case self::FORMAT_YAML:
                $content = '  ' . self::PROTECTION . 'product' . self::PROTECTION . ':' . self::EOL;
                $fieldMaxSize = $this->getFieldMaxSize($data);
                foreach ($data as $field => $value) {
                    $field = self::formatFields($field, self::FORMAT_YAML);
                    $content .= '    ' . self::PROTECTION . $field . self::PROTECTION . ':';
                    $content .= $this->indentYaml($field, $fieldMaxSize) . $value . self::EOL;
                }
                return $content;
        }
    }

    /**
     * Return feed footer
     *
     * @return string
     */
    protected function getFooter() : string
    {
        switch ($this->format) {
            case self::FORMAT_XML:
                return '</catalog>';
            case self::FORMAT_JSON:
                return ']}';
            default:
                return '';
        }
    }

    /**
     * Format field names according to the given format
     *
     * @param string $str field name
     * @param string $format export format
     *
     * @return string
     */
    public static function formatFields(string $str, string $format) : string
    {
        switch ($format) {
            case self::FORMAT_CSV:
                return substr(
                    preg_replace(
                        '/[^a-zA-Z0-9_]+/',
                        '',
                        str_replace([' ', '\''], '_', StringCleaner::replaceAccentedChars($str))
                    ),
                    0,
                    58
                );
            default:
                return strtolower(
                    preg_replace(
                        '/[^a-zA-Z0-9_]+/',
                        '',
                        str_replace([' ', '\''], '_', StringCleaner::replaceAccentedChars($str))
                    )
                );
        }
    }

    /**
     * For YAML, add spaces to have corresponding indentation
     *
     * @param string $name the field name
     * @param int $maxSize space limit
     * @return string
     */
    protected function indentYaml(string $name, int $maxSize) : string
    {
        $strlen = strlen($name);
        $spaces = '';
        for ($i = $strlen; $i <= $maxSize; $i++) {
            $spaces .= ' ';
        }
        return $spaces;
    }

    /**
     * Get the maximum length of the fields
     * Used for indentYaml function
     *
     * @param array $fields list of fields to export
     * @return int
     */
    protected function getFieldMaxSize(array $fields) : int
    {
        $maxSize = 0;
        foreach ($fields as $key => $field) {
            $field = self::formatFields($key, self::FORMAT_YAML);
            if (strlen($field) > $maxSize) {
                $maxSize = strlen($field);
            }
        }
        return $maxSize;
    }

}
