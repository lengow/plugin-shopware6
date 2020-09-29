<?php declare(strict_types=1);

namespace Lengow\Connector\Service;

use \Exception;
use Lengow\Connector\Exception\LengowException;

/**
 * Class LengowConnector
 * @package Lengow\Connector\Service
 */
class LengowConnector
{
    /**
     * @var string url of Lengow solution
     */
    public const LENGOW_URL = 'lengow.io';
    // public const LENGOW_URL = 'lengow.net';

    /**
     * @var string url of the Lengow API
     */
    private const LENGOW_API_URL = 'https://api.lengow.io';
    // private const LENGOW_API_URL = 'https://api.lengow.net';

    /**
     * @var string url of access token API
     */
    private const API_ACCESS_TOKEN = '/access/get_token';

    /**
     * @var string url of order API
     */
    public const API_ORDER = '/v3.0/orders';

    /**
     * @var string url of order merchant order id API
     */
    public const API_ORDER_MOI = '/v3.0/orders/moi/';

    /**
     * @var string url of order action API
     */
    public const API_ORDER_ACTION = '/v3.0/orders/actions/';

    /**
     * @var string url of marketplace API
     */
    public const API_MARKETPLACE = '/v3.0/marketplaces';

    /**
     * @var string url of plan API
     */
    public const API_PLAN = '/v3.0/plans';

    /**
     * @var string url of cms API
     */
    public const API_CMS = '/v3.1/cms';

    /**
     * @var string url of plugin API
     */
    public const API_PLUGIN = '/v3.0/plugins';

    /**
     * @var string request GET
     */
    public const GET = 'GET';

    /**
     * @var string request POST
     */
    public const POST = 'POST';

    /**
     * @var string request PUT
     */
    public const PUT = 'PUT';

    /**
     * @var string request PATCH
     */
    public const PATCH = 'PATCH';

    /**
     * @var string json format return
     */
    public const FORMAT_JSON = 'json';

    /**
     * @var string stream format return
     */
    public const FORMAT_STREAM = 'stream';

    /**
     * @var string success code
     */
    private const CODE_200 = 200;

    /**
     * @var string success create code
     */
    private const CODE_201 = 201;

    /**
     * @var string unauthorized access code
     */
    private const CODE_401 = 401;

    /**
     * @var string forbidden access code
     */
    private const CODE_403 = 403;

    /**
     * @var string error server code
     */
    private const CODE_500 = 500;

    /**
     * @var string timeout server code
     */
    private const CODE_504 = 504;

    /**
     * @var int authorization token lifetime
     */
    private const TOKEN_LIFETIME = 3000;

    /**
     * @var LengowConfiguration Lengow configuration service
     */
    private $lengowConfiguration;

    /**
     * @var LengowLog Lengow log service
     */
    private $lengowLog;

    /**
     * @var array success HTTP codes for request
     */
    private $successCodes = [
        self::CODE_200,
        self::CODE_201,
    ];

    /**
     * @var array authorization HTTP codes for request
     */
    private $authorizationCodes = [
        self::CODE_401,
        self::CODE_403,
    ];

    /**
     * @var array default options for curl
     */
    private $curlOpts = [
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 10,
        CURLOPT_USERAGENT => 'lengow-cms-shopware6',
    ];

    /**
     * @var array Lengow url for curl timeout
     */
    private $lengowUrls = [
        self::API_ORDER => 20,
        self::API_ORDER_MOI => 10,
        self::API_ORDER_ACTION => 15,
        self::API_MARKETPLACE => 15,
        self::API_PLAN => 5,
        self::API_CMS => 5,
        self::API_PLUGIN => 5,
    ];

    /**
     * @var int account id to connect
     */
    private $accountId;

    /**
     * @var string the access token to connect
     */
    private $accessToken;

    /**
     * @var string the secret to connect
     */
    private $secret;

    /**
     * @var string temporary token for the authorization
     */
    private $token;

    /**
     * LengowConnector Construct
     *
     * @param LengowConfiguration $lengowConfiguration Lengow configuration service
     * @param LengowLog $lengowLog Lengow log service
     */
    public function __construct(LengowConfiguration $lengowConfiguration, LengowLog $lengowLog)
    {
        $this->lengowConfiguration = $lengowConfiguration;
        $this->lengowLog = $lengowLog;
        list($this->accountId, $this->accessToken, $this->secret) = $this->lengowConfiguration->getAccessIds();
    }

    /**
     * Check if PHP Curl is activated
     *
     * @return bool
     */
    public function isCurlActivated(): bool
    {
        return function_exists('curl_version');
    }

    /**
     * Check API Authentication
     *
     * @param boolean $logOutput see log or not
     *
     * @return boolean
     */
    public function isValidAuth($logOutput = false): bool
    {
        try {
            if ($this->accountId === null) {
                return false;
            }
            if (!$this->isCurlActivated()) {
                throw new LengowException(
                    $this->lengowLog->encodeMessage('log.connector.curl_disabled'),
                    self::CODE_500
                );
            }
            $this->connect(false, $logOutput);
        } catch (Exception $e) {
            $message = $this->lengowLog->decodeMessage($e->getMessage(), LengowTranslation::DEFAULT_ISO_CODE);
            $error = $this->lengowLog->encodeMessage('log.connector.error_api', [
                'error_code' => $e->getCode(),
                'error_message' => $message,
            ]);
            $this->lengowLog->write(LengowLog::CODE_CONNECTOR, $error, $logOutput);
            return false;
        }
        return true;
    }

    /**
     * Get result for a query Api
     *
     * @param string $type request type (GET / POST / PUT / PATCH)
     * @param string $api request url
     * @param array $args request params
     * @param string $body body data for request
     * @param bool $logOutput see log or not
     *
     * @return mixed
     */
    public function queryApi(string $type, string $api, array $args = [], string $body = '', bool $logOutput = false)
    {
        try {
            if ($this->accountId === null) {
                return false;
            }
            if (!in_array($type, [self::GET, self::POST, self::PUT, self::PATCH])) {
                throw new LengowException(
                    $this->lengowLog->encodeMessage('log.connector.method_not_valid', [
                        'type' => $type
                    ]),
                    self::CODE_500
                );
            }
            $type = strtolower($type);
            $results = $this->$type(
                $api,
                array_merge(['account_id' => $this->accountId], $args),
                self::FORMAT_STREAM,
                $body,
                $logOutput
            );
        } catch (LengowException $e) {
            $message = $this->lengowLog->decodeMessage($e->getMessage(), LengowTranslation::DEFAULT_ISO_CODE);
            $error = $this->lengowLog->encodeMessage('log.connector.error_api', [
                'error_code' => $e->getCode(),
                'error_message' => $message,
            ]);
            $this->lengowLog->write(LengowLog::CODE_CONNECTOR, $error, $logOutput);
            return false;
        }
        return json_decode($results);
    }

    /**
     * Connection to the API
     *
     * @param bool $force Force cache Update
     * @param bool $logOutput see log or not
     *
     * @throws LengowException|Exception
     */
    public function connect(bool $force = false, bool $logOutput = false): void
    {
        $token = $this->lengowConfiguration->get('lengowAuthorizationToken');
        $updatedAt = $this->lengowConfiguration->get('lengowLastAuthorizationTokenUpdate');
        if (!$force
            && $token !== null
            && $updatedAt !== null
            && $token !== ''
            && (time() - $updatedAt) < self::TOKEN_LIFETIME
        ) {
            $authorizationToken = $token;
        } else {
            $authorizationToken = $this->getAuthorizationToken($logOutput);
            $this->lengowConfiguration->set('lengowAuthorizationToken', $authorizationToken);
            $this->lengowConfiguration->set('lengowLastAuthorizationTokenUpdate', (string)time());
        }
        $this->token = $authorizationToken;
    }

    /**
     * Get API call
     *
     * @param string $api Lengow method API call
     * @param array $args Lengow method API parameters
     * @param string $format return format of API
     * @param string $body body data for request
     * @param bool $logOutput see log or not
     *
     * @throws LengowException
     *
     * @return mixed
     */
    public function get(
        string $api,
        array $args = [],
        string $format = self::FORMAT_JSON,
        string $body = '',
        bool $logOutput = false
    )
    {
        return $this->call($api, $args, self::GET, $format, $body, $logOutput);
    }

    /**
     * Post API call
     *
     * @param string $api Lengow method API call
     * @param array $args Lengow method API parameters
     * @param string $format return format of API
     * @param string $body body data for request
     * @param bool $logOutput see log or not
     *
     * @throws LengowException
     *
     * @return mixed
     */
    public function post(
        string $api,
        array $args = [],
        string $format = self::FORMAT_JSON,
        string $body = '',
        bool $logOutput = false
    )
    {
        return $this->call($api, $args, self::POST, $format, $body, $logOutput);
    }

    /**
     * Put API call
     *
     * @param string $api Lengow method API call
     * @param array $args Lengow method API parameters
     * @param string $format return format of API
     * @param string $body body data for request
     * @param bool $logOutput see log or not
     *
     * @throws LengowException
     *
     * @return mixed
     */
    public function put(
        string $api,
        array $args = [],
        string $format = self::FORMAT_JSON,
        string $body = '',
        bool $logOutput = false
    )
    {
        return $this->call($api, $args, self::PUT, $format, $body, $logOutput);
    }

    /**
     * Patch API call
     *
     * @param string $api Lengow method API call
     * @param array $args Lengow method API parameters
     * @param string $format return format of API
     * @param string $body body data for request
     * @param bool $logOutput see log or not
     *
     * @throws LengowException
     *
     * @return mixed
     */
    public function patch(
        string $api,
        array $args = [],
        string $format = self::FORMAT_JSON,
        string $body = '',
        bool $logOutput = false
    )
    {
        return $this->call($api, $args, self::PATCH, $format, $body, $logOutput);
    }

    /**
     * The API method
     *
     * @param string $api Lengow method API call
     * @param array $args Lengow method API parameters
     * @param string $type type of request GET|POST|PUT|HEAD|DELETE|PATCH
     * @param string $format return format of API
     * @param string $body body data for request
     * @param boolean $logOutput see log or not
     *
     * @throws LengowException
     *
     * @return mixed
     */
    private function call(string $api, array $args, string $type, string $format, string $body, bool $logOutput)
    {
        try {
            $this->connect(false, $logOutput);
            $data = $this->callAction($api, $args, $type, $format, $body, $logOutput);
        } catch (Exception $e) {
            if (in_array($e->getCode(), $this->authorizationCodes, true)) {
                $this->lengowLog->write(
                    LengowLog::CODE_CONNECTOR,
                    $this->lengowLog->encodeMessage('log.connector.retry_get_token'),
                    $logOutput
                );
                $this->connect(true, $logOutput);
                $data = $this->callAction($api, $args, $type, $format, $body, $logOutput);
            } else {
                throw new LengowException($e->getMessage(), $e->getCode());
            }
        }
        return $data;
    }

    /**
     * Call API action
     *
     * @param string $api Lengow method API call
     * @param array $args Lengow method API parameters
     * @param string $type type of request GET|POST|PUT|PATCH
     * @param string $format return format of API
     * @param string $body body data for request
     * @param bool $logOutput see log or not
     *
     * @throws LengowException
     *
     * @return mixed
     */
    private function callAction(string $api, array $args, string $type, string $format, string $body, bool $logOutput)
    {
        $result = $this->makeRequest($type, $api, $args, $this->token ?? '', $body, $logOutput);
        return $this->format($result, $format);
    }

    /**
     * Get authorization token from Middleware
     *
     * @param bool $logOutput see log or not
     *
     * @throws LengowException
     *
     * @return string
     */
    private function getAuthorizationToken(bool $logOutput): string
    {
        // reset temporary token for the new authorization
        $this->token = null;
        $data = $this->callAction(
            self::API_ACCESS_TOKEN,
            [
                'access_token' => $this->accessToken,
                'secret' => $this->secret,
            ],
            self::POST,
            self::FORMAT_JSON,
            '',
            $logOutput
        );
        // return a specific error for get_token
        if (!isset($data['token'])) {
            throw new LengowException(
                $this->lengowLog->encodeMessage('log.connector.token_not_return'),
                self::CODE_500
            );
        }
        if ($data['token'] === '') {
            throw new LengowException(
                $this->lengowLog->encodeMessage('log.connector.token_is_empty'),
                self::CODE_500
            );
        }
        return $data['token'];
    }

    /**
     * Make Curl request
     *
     * @param string $type type of request GET|POST|PUT|PATCH
     * @param string $api Lengow method API call
     * @param array $args Lengow method API parameters
     * @param string $token temporary authorization token
     * @param string $body body data for request
     * @param bool $logOutput see log or not
     *
     * @throws LengowException
     *
     * @return mixed
     */
    private function makeRequest(string $type, string $api, array $args, string $token, string $body, bool $logOutput)
    {
        // Define CURLE_OPERATION_TIMEDOUT for old php versions
        defined('CURLE_OPERATION_TIMEDOUT') || define('CURLE_OPERATION_TIMEDOUT', CURLE_OPERATION_TIMEOUTED);
        $ch = curl_init();
        // Default curl Options
        $opts = $this->curlOpts;
        // get special timeout for specific Lengow API
        if (array_key_exists($api, $this->lengowUrls)) {
            $opts[CURLOPT_TIMEOUT] = $this->lengowUrls[$api];
        }
        // get url for a specific environment
        $url = self::LENGOW_API_URL . $api;
        $opts[CURLOPT_CUSTOMREQUEST] = strtoupper($type);
        $url = parse_url($url);
        if (isset($url['port'])) {
            $opts[CURLOPT_PORT] = $url['port'];
        }
        $opts[CURLOPT_HEADER] = false;
        $opts[CURLOPT_VERBOSE] = false;
        if (isset($token)) {
            $opts[CURLOPT_HTTPHEADER] = ['Authorization: ' . $token];
        }
        $url = $url['scheme'] . '://' . $url['host'] . $url['path'];
        switch ($type) {
            case self::GET:
                $opts[CURLOPT_URL] = $url . (!empty($args) ? '?' . http_build_query($args) : '');
                break;
            case self::PUT:
                if (isset($token)) {
                    $opts[CURLOPT_HTTPHEADER] = array_merge(
                        $opts[CURLOPT_HTTPHEADER],
                        [
                            'Content-Type: application/json',
                            'Content-Length: ' . strlen($body),
                        ]
                    );
                }
                $opts[CURLOPT_URL] = $url . '?' . http_build_query($args);
                $opts[CURLOPT_POSTFIELDS] = $body;
                break;
            case self::PATCH:
                if (isset($token)) {
                    $opts[CURLOPT_HTTPHEADER] = array_merge(
                        $opts[CURLOPT_HTTPHEADER],
                        ['Content-Type: application/json']
                    );
                }
                $opts[CURLOPT_URL] = $url;
                $opts[CURLOPT_POST] = count($args);
                $opts[CURLOPT_POSTFIELDS] = json_encode($args);
                break;
            default:
                $opts[CURLOPT_URL] = $url;
                $opts[CURLOPT_POST] = count($args);
                $opts[CURLOPT_POSTFIELDS] = http_build_query($args);
                break;
        }
        $this->lengowLog->write(
            LengowLog::CODE_CONNECTOR,
            $this->lengowLog->encodeMessage('log.connector.call_api', [
                'call_type' => $type,
                'curl_url' => $opts[CURLOPT_URL],
            ]),
            $logOutput
        );
        curl_setopt_array($ch, $opts);
        $result = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        $curlErrorNumber = curl_errno($ch);
        curl_close($ch);
        $this->checkReturnRequest($result, $httpCode, $curlError, $curlErrorNumber);
        return $result;
    }

    /**
     * Check return request and generate exception if needed
     *
     * @param bool|string $result Curl return call
     * @param int $httpCode request http code
     * @param string $curlError Curl error
     * @param int $curlErrorNumber Curl error number
     *
     * @throws LengowException
     *
     */
    private function checkReturnRequest($result, int $httpCode, string $curlError, int $curlErrorNumber): void
    {
        if ($result === false) {
            // recovery of Curl errors
            if (in_array($curlErrorNumber, [CURLE_OPERATION_TIMEDOUT, CURLE_OPERATION_TIMEOUTED], true)) {
                throw new LengowException($this->lengowLog->encodeMessage('log.connector.timeout_api'), self::CODE_504);
            }
            throw new LengowException(
                $this->lengowLog->encodeMessage('log.connector.error_curl', [
                    'error_code' => $curlErrorNumber,
                    'error_message' => $curlError,
                ]),
                self::CODE_500
            );
        }
        if (!in_array($httpCode, $this->successCodes, true)) {
            $result = $this->format($result);
            // recovery of Lengow Api errors
            if (isset($result['error'])) {
                throw new LengowException($result['error']['message'], $httpCode);
            }
            throw new LengowException($this->lengowLog->encodeMessage('log.connector.api_not_available'), $httpCode);
        }
    }

    /**
     * Get data in specific format
     *
     * @param mixed $data Curl response data
     * @param string $format return format of API
     *
     * @return mixed
     */
    private function format($data, string $format = self::FORMAT_JSON)
    {
        switch ($format) {
            case self::FORMAT_STREAM:
                return $data;
            case self::FORMAT_JSON:
            default:
                return json_decode($data, true);
        }
    }
}
