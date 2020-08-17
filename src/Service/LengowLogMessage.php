<?php declare(strict_types=1);

namespace Lengow\Connector\Service;

/**
 * Class LengowLogMessage
 * @package Lengow\Connector\Service
 */
class LengowLogMessage
{
    /**
     * @var LengowTranslation $lengowTranslation
     */
    private $lengowTranslation;

    /**
     * LengowLogMessage Construct
     *
     * @param LengowTranslation $lengowTranslation Lengow translation service
     */
    public function __construct(LengowTranslation $lengowTranslation)
    {
        $this->lengowTranslation = $lengowTranslation;
    }

    /**
     * Encode log message with params for translation
     *
     * @param string $key log message key
     * @param array $params log message parameters
     *
     * @return string
     */
    public function encodeLogMessage(string $key, array $params = []): string
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
     * Decode log message with params for translation
     *
     * @param string $message key to translate
     * @param string|null $isoCode language translation iso code
     * @param array $params array parameters to display in the translation message
     *
     * @return string
     */
    public function decodeLogMessage(string $message, string $isoCode = null, array $params = []): string
    {
        if (preg_match('/^(([a-z\_]*\.){1,3}[a-z\_]*)(\[(.*)\]|)$/', $message, $result)) {
            if ($result[1] ?? false) {
                $key = $result[1];
                if (isset($result[4]) && $params === null) {
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
}
