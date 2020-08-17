<?php declare(strict_types=1);

namespace Lengow\Connector\Service;

use Lengow\Connector\Util\EnvironmentInfoProvider;

/**
 * Class LengowTranslation
 * @package Lengow\Connector\Service
 */
class LengowTranslation
{
    /**
     * @var string default iso code
     */
    public const DEFAULT_ISO_CODE = 'en-GB';

    /**
     * @var EnvironmentInfoProvider Environment info provider utility
     */
    private $environmentInfoProvider;

    /**
     * @var array|null all translations
     */
    private $translation = null;

    /**
     * LengowTranslation Construct
     *
     * @param EnvironmentInfoProvider $environmentInfoProvider Environment info provider utility
     */
    public function __construct(EnvironmentInfoProvider $environmentInfoProvider)
    {
        $this->environmentInfoProvider = $environmentInfoProvider;
    }

    /**
     * Translate message
     *
     * @param string $message localization key
     * @param array $args arguments to replace word in string
     * @param array|null $isoCode translation iso code
     *
     * @return string
     */
    public function t(string $message, array $args = [], $isoCode = null): string
    {
        if ($isoCode === null) {
            $isoCode = $this->environmentInfoProvider->getLocaleCode();
        }
        if (!isset($this->translation[$isoCode])) {
            $this->loadFile($isoCode);
        }
        if (isset($this->translation[$isoCode][$message])) {
            return $this->translateFinal($this->translation[$isoCode][$message], $args);
        }
        if (!isset($this->translation[self::DEFAULT_ISO_CODE])) {
            $this->loadFile(self::DEFAULT_ISO_CODE);
        }
        if (isset($this->translation[self::DEFAULT_ISO_CODE][$message])) {
            return $this->translateFinal($this->translation[self::DEFAULT_ISO_CODE][$message], $args);
        }
        return 'Missing Translation [' . $message . ']';
    }

    /**
     * Translate string
     *
     * @param string $text localization key
     * @param array $args arguments to replace word in string
     *
     * @return string
     */
    private function translateFinal(string $text, array $args): string
    {
        if (empty($args)) {
            return stripslashes($text);
        }
        $params = [];
        $values = [];
        foreach ($args as $key => $value) {
            $params[] = '%{' . $key . '}';
            $values[] = $value;
        }
        return stripslashes(str_replace($params, $values, $text));
    }

    /**
     * Load csv file
     *
     * @param string $isoCode translation iso code
     * @param string|null $filename file location
     *
     * @return bool
     */
    private function loadFile(string $isoCode, $filename = null): bool
    {
        if (!$filename) {
            $filename = $this->environmentInfoProvider->getPluginPath() . '/Translations/' . $isoCode . '.csv';
        }
        $translation = [];
        if (file_exists($filename) && ($handle = fopen($filename, 'r')) !== false) {
            while (($data = fgetcsv($handle, 1000, '|')) !== false) {
                if (isset($data[1])) {
                    $translation[$data[0]] = $data[1];
                }
            }
            fclose($handle);
        }
        $this->translation[$isoCode] = $translation;
        return !empty($translation);
    }
}
