<?php declare(strict_types=1);

namespace Lengow\Connector\Service;

/**
 * Class LengowMessage
 * @package Lengow\Connector\Service
 */
class LengowMessage
{
    /**
     * @var LengowTranslation $lengowTranslation
     */
    private $lengowTranslation;

    /**
     * LengowMessage Construct
     *
     * @param LengowTranslation $lengowTranslation Lengow translation service
     */
    public function __construct(LengowTranslation $lengowTranslation)
    {
        $this->lengowTranslation = $lengowTranslation;
    }

    /**
     * Encode message with params for translation
     *
     * @param string $key message key
     * @param array $params message parameters
     *
     * @return string
     */
    public function encode(string $key, array $params = []): string
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
    public function decode(string $message, string $isoCode = null, array $params = []): string
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
}
