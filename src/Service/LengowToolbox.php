<?php declare(strict_types=1);

namespace Lengow\Connector\Service;

use Exception;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Checkout\Order\OrderStates;
use Shopware\Core\Checkout\Order\Aggregate\OrderDelivery\OrderDeliveryStates;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionStates;
use Lengow\Connector\Entity\Lengow\Order\OrderCollection as LengowOrderCollection;
use Lengow\Connector\Entity\Lengow\Order\OrderEntity as LengowOrderEntity;
use Lengow\Connector\Util\EnvironmentInfoProvider;

/**
 * Class LengowToolbox
 * @package Lengow\Connector\Service
 */
class LengowToolbox
{
    /* Toolbox GET params */
    public const PARAM_CREATED_FROM = 'created_from';
    public const PARAM_CREATED_TO = 'created_to';
    public const PARAM_DATE = 'date';
    public const PARAM_DAYS = 'days';
    public const PARAM_FORCE = 'force';
    public const PARAM_MARKETPLACE_NAME = 'marketplace_name';
    public const PARAM_MARKETPLACE_SKU = 'marketplace_sku';
    public const PARAM_PROCESS = 'process';
    public const PARAM_SHOP_ID = 'shop_id';
    public const PARAM_TOKEN = 'token';
    public const PARAM_TOOLBOX_ACTION = 'toolbox_action';
    public const PARAM_TYPE = 'type';

    /* Toolbox Actions */
    public const ACTION_DATA = 'data';
    public const ACTION_LOG = 'log';
    public const ACTION_ORDER = 'order';

    /* Data type */
    public const DATA_TYPE_ACTION = 'action';
    public const DATA_TYPE_ALL = 'all';
    public const DATA_TYPE_CHECKLIST = 'checklist';
    public const DATA_TYPE_CHECKSUM = 'checksum';
    public const DATA_TYPE_CMS = 'cms';
    public const DATA_TYPE_ERROR = 'error';
    public const DATA_TYPE_EXTRA = 'extra';
    public const DATA_TYPE_LOG = 'log';
    public const DATA_TYPE_PLUGIN = 'plugin';
    public const DATA_TYPE_OPTION = 'option';
    public const DATA_TYPE_ORDER = 'order';
    public const DATA_TYPE_ORDER_STATUS = 'order_status';
    public const DATA_TYPE_SHOP = 'shop';
    public const DATA_TYPE_SYNCHRONIZATION = 'synchronization';

    /* Toolbox process type */
    public const PROCESS_TYPE_GET_DATA = 'get_data';
    public const PROCESS_TYPE_SYNC = 'sync';

    /* Toolbox Data  */
    public const CHECKLIST = 'checklist';
    public const CHECKLIST_CURL_ACTIVATED = 'curl_activated';
    public const CHECKLIST_SIMPLE_XML_ACTIVATED = 'simple_xml_activated';
    public const CHECKLIST_JSON_ACTIVATED = 'json_activated';
    public const CHECKLIST_MD5_SUCCESS = 'md5_success';
    public const PLUGIN = 'plugin';
    public const PLUGIN_CMS_VERSION = 'cms_version';
    public const PLUGIN_VERSION = 'plugin_version';
    public const PLUGIN_PHP_VERSION = 'php_version';
    public const PLUGIN_DEBUG_MODE_DISABLE = 'debug_mode_disable';
    public const PLUGIN_WRITE_PERMISSION = 'write_permission';
    public const PLUGIN_SERVER_IP = 'server_ip';
    public const PLUGIN_AUTHORIZED_IP_ENABLE = 'authorized_ip_enable';
    public const PLUGIN_AUTHORIZED_IPS = 'authorized_ips';
    public const PLUGIN_TOOLBOX_URL = 'toolbox_url';
    public const SYNCHRONIZATION = 'synchronization';
    public const SYNCHRONIZATION_CMS_TOKEN = 'cms_token';
    public const SYNCHRONIZATION_CRON_URL = 'cron_url';
    public const SYNCHRONIZATION_NUMBER_ORDERS_IMPORTED = 'number_orders_imported';
    public const SYNCHRONIZATION_NUMBER_ORDERS_WAITING_SHIPMENT = 'number_orders_waiting_shipment';
    public const SYNCHRONIZATION_NUMBER_ORDERS_IN_ERROR = 'number_orders_in_error';
    public const SYNCHRONIZATION_SYNCHRONIZATION_IN_PROGRESS = 'synchronization_in_progress';
    public const SYNCHRONIZATION_LAST_SYNCHRONIZATION = 'last_synchronization';
    public const SYNCHRONIZATION_LAST_SYNCHRONIZATION_TYPE = 'last_synchronization_type';
    public const CMS_OPTIONS = 'cms_options';
    public const SHOPS = 'shops';
    public const SHOP_ID = 'shop_id';
    public const SHOP_NAME = 'shop_name';
    public const SHOP_DOMAIN_URL = 'domain_url';
    public const SHOP_TOKEN = 'shop_token';
    public const SHOP_FEED_URL = 'feed_url';
    public const SHOP_ENABLED = 'enabled';
    public const SHOP_CATALOG_IDS = 'catalog_ids';
    public const SHOP_NUMBER_PRODUCTS_AVAILABLE = 'number_products_available';
    public const SHOP_NUMBER_PRODUCTS_EXPORTED = 'number_products_exported';
    public const SHOP_LAST_EXPORT = 'last_export';
    public const SHOP_OPTIONS = 'shop_options';
    public const CHECKSUM = 'checksum';
    public const CHECKSUM_AVAILABLE = 'available';
    public const CHECKSUM_SUCCESS = 'success';
    public const CHECKSUM_NUMBER_FILES_CHECKED = 'number_files_checked';
    public const CHECKSUM_NUMBER_FILES_MODIFIED = 'number_files_modified';
    public const CHECKSUM_NUMBER_FILES_DELETED = 'number_files_deleted';
    public const CHECKSUM_FILE_MODIFIED = 'file_modified';
    public const CHECKSUM_FILE_DELETED = 'file_deleted';
    public const LOGS = 'logs';

    /* Toolbox order data  */
    public const ID = 'id';
    public const ORDERS = 'orders';
    public const ORDER_MARKETPLACE_SKU = 'marketplace_sku';
    public const ORDER_MARKETPLACE_NAME = 'marketplace_name';
    public const ORDER_MARKETPLACE_LABEL = 'marketplace_label';
    public const ORDER_MERCHANT_ORDER_ID = 'merchant_order_id';
    public const ORDER_MERCHANT_ORDER_REFERENCE = 'merchant_order_reference';
    public const ORDER_DELIVERY_ADDRESS_ID = 'delivery_address_id';
    public const ORDER_DELIVERY_COUNTRY_ISO = 'delivery_country_iso';
    public const ORDER_PROCESS_STATE = 'order_process_state';
    public const ORDER_STATUSES = 'order_statuses';
    public const ORDER_STATUS = 'order_status';
    public const ORDER_MERCHANT_ORDER_STATUS = 'merchant_order_status';
    public const ORDER_TOTAL_PAID = 'total_paid';
    public const ORDER_MERCHANT_TOTAL_PAID = 'merchant_total_paid';
    public const ORDER_COMMISSION= 'commission';
    public const ORDER_CURRENCY = 'currency';
    public const ORDER_DATE = 'order_date';
    public const ORDER_ITEMS = 'order_items';
    public const ORDER_IS_REIMPORTED = 'is_reimported';
    public const ORDER_IS_IN_ERROR = 'is_in_error';
    public const ORDER_ACTION_IN_PROGRESS = 'action_in_progress';
    public const CUSTOMER = 'customer';
    public const CUSTOMER_NAME = 'name';
    public const CUSTOMER_EMAIL = 'email';
    public const CUSTOMER_VAT_NUMBER = 'vat_number';
    public const ORDER_TYPES = 'order_types';
    public const ORDER_TYPE_EXPRESS = 'is_express';
    public const ORDER_TYPE_PRIME = 'is_prime';
    public const ORDER_TYPE_BUSINESS = 'is_business';
    public const ORDER_TYPE_DELIVERED_BY_MARKETPLACE = 'is_delivered_by_marketplace';
    public const TRACKING = 'tracking';
    public const TRACKING_CARRIER = 'carrier';
    public const TRACKING_METHOD = 'method';
    public const TRACKING_NUMBER = 'tracking_number';
    public const TRACKING_RELAY_ID = 'relay_id';
    public const TRACKING_MERCHANT_CARRIER = 'merchant_carrier';
    public const TRACKING_MERCHANT_TRACKING_NUMBER = 'merchant_tracking_number';
    public const TRACKING_MERCHANT_TRACKING_URL = 'merchant_tracking_url';
    public const CREATED_AT = 'created_at';
    public const UPDATED_AT = 'updated_at';
    public const IMPORTED_AT = 'imported_at';
    public const ERRORS = 'errors';
    public const ERROR_TYPE = 'type';
    public const ERROR_MESSAGE = 'message';
    public const ERROR_CODE = 'code';
    public const ERROR_FINISHED = 'is_finished';
    public const ERROR_REPORTED = 'is_reported';
    public const ACTIONS = 'actions';
    public const ACTION_ID = 'action_id';
    public const ACTION_PARAMETERS = 'parameters';
    public const ACTION_RETRY = 'retry';
    public const ACTION_FINISH = 'is_finished';
    public const EXTRA_UPDATED_AT = 'extra_updated_at';

    /* Process state labels */
    private const PROCESS_STATE_NEW = 'new';
    private const PROCESS_STATE_IMPORT = 'import';
    private const PROCESS_STATE_FINISH = 'finish';

    /* Error type labels */
    private const TYPE_ERROR_IMPORT = 'import';
    private const TYPE_ERROR_SEND = 'send';

    /* PHP extensions */
    private const PHP_EXTENSION_CURL = 'curl_version';
    private const PHP_EXTENSION_SIMPLEXML = 'simplexml_load_file';
    private const PHP_EXTENSION_JSON = 'json_decode';

    /* Toolbox files */
    private const FILE_CHECKMD5 = 'checkmd5.csv';
    private const FILE_TEST = 'test.txt';

    /**
     * @var array valid toolbox actions
     */
    private $toolboxActions = [
        self::ACTION_DATA,
        self::ACTION_LOG,
        self::ACTION_ORDER,
    ];

    /**
     * @var LengowAction $lengowAction Lengow action service
     */
    private $lengowAction;

    /**
     * @var LengowConfiguration $lengowConfiguration Lengow configuration service
     */
    private $lengowConfiguration;

    /**
     * @var LengowExport $lengowExport Lengow export service
     */
    private $lengowExport;

    /**
     * @var LengowImport $lengowImport Lengow import service
     */
    private $lengowImport;

    /**
     * @var LengowLog $lengowLog Lengow log service
     */
    private $lengowLog;

    /**
     * @var LengowOrder $lengowOrder Lengow order service
     */
    private $lengowOrder;

    /**
     * @var LengowOrderError $lengowOrderError Lengow order error service
     */
    private $lengowOrderError;

    /**
     * @var EnvironmentInfoProvider $environmentInfoProvider Environment info provider utility
     */
    private $environmentInfoProvider;

    /**
     * LengowToolbox constructor
     *
     * @param LengowAction $lengowAction Lengow action service
     * @param LengowConfiguration $lengowConfiguration Lengow configuration service
     * @param LengowExport $lengowExport Lengow export service
     * @param LengowImport $lengowImport Lengow import service
     * @param LengowLog $lengowLog Lengow log service
     * @param LengowOrder $lengowOrder Lengow order service
     * @param EnvironmentInfoProvider $environmentInfoProvider Lengow environment info provider utility
     *
     */
    public function __construct(
        LengowAction  $lengowAction,
        LengowConfiguration $lengowConfiguration,
        LengowExport $lengowExport,
        LengowImport $lengowImport,
        LengowLog $lengowLog,
        LengowOrder $lengowOrder,
        LengowOrderError $lengowOrderError,
        EnvironmentInfoProvider $environmentInfoProvider
    )
    {
        $this->lengowAction = $lengowAction;
        $this->lengowConfiguration = $lengowConfiguration;
        $this->lengowExport = $lengowExport;
        $this->lengowImport = $lengowImport;
        $this->lengowLog = $lengowLog;
        $this->lengowOrder = $lengowOrder;
        $this->lengowOrderError = $lengowOrderError;
        $this->environmentInfoProvider = $environmentInfoProvider;
    }

    /**
     * Get all toolbox data
     *
     * @param string $type Toolbox data type
     *
     * @return array
     */
    public function getData(string $type = self::DATA_TYPE_CMS): array
    {
        switch ($type) {
            case self::DATA_TYPE_ALL:
                return $this->getAllData();
            case self::DATA_TYPE_CHECKLIST:
                return $this->getChecklistData();
            case self::DATA_TYPE_CHECKSUM:
                return $this->getChecksumData();
            case self::DATA_TYPE_LOG:
                return $this->getLogData();
            case self::DATA_TYPE_OPTION:
                return $this->getOptionData();
            case self::DATA_TYPE_PLUGIN:
                return $this->getPluginData();
            case self::DATA_TYPE_SHOP:
                return $this->getShopData();
            case self::DATA_TYPE_SYNCHRONIZATION:
                return $this->getSynchronizationData();
            default:
            case self::DATA_TYPE_CMS:
                return $this->getCmsData();
        }
    }

    /**
     * Download log file individually or globally
     *
     * @param string|null $fileName name of file to download
     */
    public function downloadLog(string $fileName = null): void
    {
        $this->lengowLog->download($fileName);
    }

    /**
     * Start order synchronization based on specific parameters
     *
     * @param array $params synchronization parameters
     *
     * @return array
     */
    public function syncOrders(array $params = []): array
    {
        // get all params for order synchronization
        $params = $this->filterParamsForSync($params);
        $this->lengowImport->init($params);
        $result = $this->lengowImport->exec();
        // if global error return error message and request http code
        if (isset($result[LengowImport::ERRORS][0])) {
            return $this->generateErrorReturn(LengowConnector::CODE_403, $result[LengowImport::ERRORS][0]);
        }
        unset($result[LengowImport::ERRORS]);
        return $result;
    }

    /**
     * Get all order data from a marketplace reference
     *
     * @param string|null $marketplaceSku marketplace order reference
     * @param string|null $marketplaceName marketplace code
     * @param string $type Toolbox order data type
     *
     * @return array
     */
    public function getOrderData(
        string $marketplaceSku = null,
        string $marketplaceName = null,
        string $type = self::DATA_TYPE_ORDER
    ): array
    {
        /** @var LengowOrderCollection $lengowOrderCollection */
        $lengowOrderCollection = $marketplaceSku && $marketplaceName
            ? $this->lengowOrder->getAllLengowOrders($marketplaceSku, $marketplaceName)
            : null;
        // if no reference is found, process is blocked
        if ($lengowOrderCollection === null) {
            return $this->generateErrorReturn(
                LengowConnector::CODE_404,
                $this->lengowLog->encodeMessage('log.import.unable_find_order')
            );
        }
        $orders = [];
        foreach ($lengowOrderCollection as $lengowOrder) {
            if ($type === self::DATA_TYPE_EXTRA) {
                return $this->getOrderExtraData($lengowOrder);
            }
            $marketplaceLabel = $lengowOrder->getMarketplaceLabel();
            $orders[] = $this->getOrderDataByType($lengowOrder, $type);
        }
        return [
            self::ORDER_MARKETPLACE_SKU => $marketplaceSku,
            self::ORDER_MARKETPLACE_NAME => $marketplaceName,
            self::ORDER_MARKETPLACE_LABEL => $marketplaceLabel ?? null,
            self::ORDERS => $orders,
        ];
    }

    /**
     * Is toolbox action
     *
     * @param string $action toolbox action
     *
     * @return bool
     */
    public function isToolboxAction(string $action): bool
    {
        return in_array($action, $this->toolboxActions, true);
    }

    /**
     * Check if PHP Curl is activated
     *
     * @return bool
     */
    public static function isCurlActivated(): bool
    {
        return function_exists(self::PHP_EXTENSION_CURL);
    }

    /**
     * Get array of requirements
     *
     * @return array
     */
    private function getAllData(): array
    {
        return [
            self::CHECKLIST => $this->getChecklistData(),
            self::PLUGIN => $this->getPluginData(),
            self::SYNCHRONIZATION => $this->getSynchronizationData(),
            self::CMS_OPTIONS => $this->lengowConfiguration->getAllValues(null, true),
            self::SHOPS => $this->getShopData(),
            self::CHECKSUM => $this->getChecksumData(),
            self::LOGS => $this->getLogData(),
        ];
    }

    /**
     * Get cms data
     *
     * @return array
     */
    private function getCmsData(): array
    {
        return [
            self::CHECKLIST => $this->getChecklistData(),
            self::PLUGIN => $this->getPluginData(),
            self::SYNCHRONIZATION => $this->getSynchronizationData(),
            self::CMS_OPTIONS => $this->lengowConfiguration->getAllValues(null, true),
        ];
    }

    /**
     * Get array of requirements
     *
     * @return array
     */
    private function getChecklistData(): array
    {
        $checksumData = $this->getChecksumData();
        return [
            self::CHECKLIST_CURL_ACTIVATED => self::isCurlActivated(),
            self::CHECKLIST_SIMPLE_XML_ACTIVATED => $this->isSimpleXMLActivated(),
            self::CHECKLIST_JSON_ACTIVATED  => $this->isJsonActivated(),
            self::CHECKLIST_MD5_SUCCESS => $checksumData[self::CHECKSUM_SUCCESS],
        ];
    }

    /**
     * Get array of plugin data
     *
     * @return array
     */
    private function getPluginData(): array
    {
        return [
            self::PLUGIN_CMS_VERSION => $this->environmentInfoProvider->getVersion(),
            self::PLUGIN_VERSION => $this->environmentInfoProvider->getPluginVersion(),
            self::PLUGIN_PHP_VERSION => PHP_VERSION,
            self::PLUGIN_DEBUG_MODE_DISABLE => !$this->lengowConfiguration->debugModeIsActive(),
            self::PLUGIN_WRITE_PERMISSION => $this->testWritePermission(),
            self::PLUGIN_SERVER_IP => $_SERVER['SERVER_ADDR'],
            self::PLUGIN_AUTHORIZED_IP_ENABLE => $this->lengowConfiguration->get(
                LengowConfiguration::AUTHORIZED_IP_ENABLED
            ),
            self::PLUGIN_AUTHORIZED_IPS => $this->lengowConfiguration->get(LengowConfiguration::AUTHORIZED_IPS),
            self::PLUGIN_TOOLBOX_URL => $this->lengowConfiguration->getToolboxUrl(),
        ];
    }

    /**
     * Get array of import data
     *
     * @return array
     */
    private function getSynchronizationData(): array
    {
        $lastImport = $this->lengowImport->getLastImport();
        return [
            self::SYNCHRONIZATION_CMS_TOKEN => $this->lengowConfiguration->getToken(),
            self::SYNCHRONIZATION_CRON_URL => $this->lengowConfiguration->getCronUrl(),
            self::SYNCHRONIZATION_NUMBER_ORDERS_IMPORTED => $this->lengowOrder->countOrderImportedByLengow(),
            self::SYNCHRONIZATION_NUMBER_ORDERS_WAITING_SHIPMENT => $this->lengowOrder->countOrderToBeSent(),
            self::SYNCHRONIZATION_NUMBER_ORDERS_IN_ERROR => $this->lengowOrder->countOrderWithError(),
            self::SYNCHRONIZATION_SYNCHRONIZATION_IN_PROGRESS => $this->lengowImport->isInProcess(),
            self::SYNCHRONIZATION_LAST_SYNCHRONIZATION => $lastImport['type'] === 'none' ? 0 : $lastImport['timestamp'],
            self::SYNCHRONIZATION_LAST_SYNCHRONIZATION_TYPE => $lastImport['type'],
        ];
    }

    /**
     * Get array of export data
     *
     * @return array
     */
    private function getShopData(): array
    {
        $exportData = [];
        $salesChannels = $this->environmentInfoProvider->getActiveSalesChannels();
        if ($salesChannels->count() === 0) {
            return $exportData;
        }
        foreach ($salesChannels as $salesChannel) {
            $salesChannelId = $salesChannel->getId();
            $this->lengowExport->init([
                LengowExport::PARAM_SALES_CHANNEL_ID => $salesChannelId,
            ]);
            $lastExport = $this->lengowConfiguration->get(LengowConfiguration::LAST_UPDATE_EXPORT, $salesChannelId);
            $exportData[] = [
                self::SHOP_ID => $salesChannelId,
                self::SHOP_NAME => $salesChannel->getName(),
                self::SHOP_DOMAIN_URL => $this->environmentInfoProvider->getBaseUrl($salesChannelId),
                self::SHOP_TOKEN => $this->lengowConfiguration->getToken($salesChannel->getId()),
                self::SHOP_FEED_URL => $this->lengowConfiguration->getFeedUrl($salesChannel->getId()),
                self::SHOP_ENABLED => $this->lengowConfiguration->salesChannelIsActive($salesChannelId),
                self::SHOP_CATALOG_IDS => $this->lengowConfiguration->getCatalogIds($salesChannelId),
                self::SHOP_NUMBER_PRODUCTS_AVAILABLE => $this->lengowExport->getTotalProduct(),
                self::SHOP_NUMBER_PRODUCTS_EXPORTED => $this->lengowExport->getTotalExportProduct(),
                self::SHOP_LAST_EXPORT => empty($lastExport) ? 0 : (int) $lastExport,
                self::SHOP_OPTIONS => $this->lengowConfiguration->getAllValues($salesChannelId, true),
            ];
        }
        return $exportData;
    }

    /**
     * Get array of export data
     *
     * @return array
     */
    private function getOptionData(): array
    {
        $optionData = [
            self::CMS_OPTIONS => $this->lengowConfiguration->getAllValues(),
            self::SHOP_OPTIONS => [],
        ];
        $salesChannels = $this->environmentInfoProvider->getActiveSalesChannels();
        foreach ($salesChannels as $salesChannel) {
            $optionData[self::SHOP_OPTIONS][] = $this->lengowConfiguration->getAllValues($salesChannel->getId());
        }
        return $optionData;
    }

    /**
     * Get files checksum
     *
     * @return array
     */
    private function getChecksumData(): array
    {
        $fileCounter = 0;
        $fileModified = [];
        $fileDeleted = [];
        $sep = DIRECTORY_SEPARATOR;
        $pluginPath = $this->environmentInfoProvider->getPluginPath();
        $fileName = $pluginPath . $sep . EnvironmentInfoProvider::FOLDER_CONFIG . $sep . self::FILE_CHECKMD5;
        if (file_exists($fileName)) {
            $md5Available = true;
            if (($file = fopen($fileName, 'rb')) !== false) {
                while (($data = fgetcsv($file, 1000, '|')) !== false) {
                    $fileCounter++;
                    $shortPath = $data[0];
                    $filePath = $this->environmentInfoProvider->getPluginBasePath() . $data[0];
                    if (file_exists($filePath)) {
                        $fileMd = md5_file($filePath);
                        if ($fileMd !== $data[1]) {
                            $fileModified[] = $shortPath;
                        }
                    } else {
                        $fileDeleted[] = $shortPath;
                    }
                }
                fclose($file);
            }
        } else {
           $md5Available = false;
        }
        $fileModifiedCounter = count($fileModified);
        $fileDeletedCounter = count($fileDeleted);
        $md5Success = $md5Available && !($fileModifiedCounter > 0) && !($fileDeletedCounter > 0);
        return [
            self::CHECKSUM_AVAILABLE => $md5Available,
            self::CHECKSUM_SUCCESS => $md5Success,
            self::CHECKSUM_NUMBER_FILES_CHECKED => $fileCounter,
            self::CHECKSUM_NUMBER_FILES_MODIFIED => $fileModifiedCounter,
            self::CHECKSUM_NUMBER_FILES_DELETED => $fileDeletedCounter,
            self::CHECKSUM_FILE_MODIFIED => $fileModified,
            self::CHECKSUM_FILE_DELETED => $fileDeleted,
        ];
    }

    /**
     * Get all log files available
     *
     * @return array
     */
    private function getLogData(): array
    {
        $logs = $this->lengowLog->getPaths();
        if (!empty($logs)) {
            $logs[] = [
                LengowLog::LOG_DATE => null,
                LengowLog::LOG_LINK => $this->lengowConfiguration->getToolboxUrl()
                    . '&' . self::PARAM_TOOLBOX_ACTION . '=' . self::ACTION_LOG,
            ];
        }
        return $logs;
    }

    /**
     * Check if SimpleXML Extension is activated
     *
     * @return bool
     */
    private function isSimpleXMLActivated(): bool
    {
        return function_exists(self::PHP_EXTENSION_SIMPLEXML);
    }

    /**
     * Check if SimpleXML Extension is activated
     *
     * @return bool
     */
    private function isJsonActivated(): bool
    {
        return function_exists(self::PHP_EXTENSION_JSON);
    }

    /**
     * Test write permission for log and export in file
     *
     * @return bool
     */
    private function testWritePermission(): bool
    {
        $sep = DIRECTORY_SEPARATOR;
        $pluginPath = $this->environmentInfoProvider->getPluginPath();
        $filePath = $pluginPath . $sep . EnvironmentInfoProvider::FOLDER_CONFIG . $sep . self::FILE_TEST;
        try {
            $file = fopen($filePath, 'wb+');
            if (!$file) {
                return false;
            }
            unlink($filePath);
            return true;
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * Filter parameters for order synchronization
     *
     * @param array $params synchronization params
     *
     * @return array
     */
    private function filterParamsForSync(array $params = []): array
    {
        $paramsFiltered = [LengowImport::PARAM_TYPE => LengowImport::TYPE_TOOLBOX];
        if (isset(
            $params[self::PARAM_MARKETPLACE_SKU],
            $params[self::PARAM_MARKETPLACE_NAME],
            $params[self::PARAM_SHOP_ID]
        )) {
            // get all parameters to synchronize a specific order
            $paramsFiltered[LengowImport::PARAM_MARKETPLACE_SKU] = $params[self::PARAM_MARKETPLACE_SKU];
            $paramsFiltered[LengowImport::PARAM_MARKETPLACE_NAME] = $params[self::PARAM_MARKETPLACE_NAME];
            $paramsFiltered[LengowImport::PARAM_SALES_CHANNEL_ID] = $params[self::PARAM_SHOP_ID];
        } elseif (isset($params[self::PARAM_CREATED_FROM], $params[self::PARAM_CREATED_TO])) {
            // get all parameters to synchronize over a fixed period
            $paramsFiltered[LengowImport::PARAM_CREATED_FROM] = $params[self::PARAM_CREATED_FROM];
            $paramsFiltered[LengowImport::PARAM_CREATED_TO] = $params[self::PARAM_CREATED_TO];
        } elseif (isset($params[self::PARAM_DAYS])) {
            // get all parameters to synchronize over a time interval
            $paramsFiltered[LengowImport::PARAM_DAYS] = (int) $params[self::PARAM_DAYS];
        }
        // force order synchronization by removing pending errors
        if (isset($params[self::PARAM_FORCE])) {
            $paramsFiltered[LengowImport::PARAM_FORCE_SYNC] = (bool) $params[self::PARAM_FORCE];
        }
        return $paramsFiltered;
    }

    /**
     * Get array of all the data of the order
     *
     * @param LengowOrderEntity $lengowOrder Lengow order instance
     * @param string $type Toolbox order data type
     *
     * @return array
     */
    private function getOrderDataByType(LengowOrderEntity $lengowOrder, string $type): array
    {
        $order = $lengowOrder->getOrder();
        $orderReferences = [
            self::ID => $lengowOrder->getId(),
            self::ORDER_MERCHANT_ORDER_ID  => $order ? $order->getId() : null,
            self::ORDER_MERCHANT_ORDER_REFERENCE  => $order ? $order->getOrderNumber() : null,
            self::ORDER_DELIVERY_ADDRESS_ID => $lengowOrder->getDeliveryAddressId(),
        ];
        switch ($type) {
            case self::DATA_TYPE_ACTION:
                $orderData = [
                    self::ACTIONS => $order ? $this->getOrderActionData($order) : [],
                ];
                break;
            case self::DATA_TYPE_ERROR:
                $orderData = [
                    self::ERRORS => $this->getOrderErrorsData($lengowOrder),
                ];
                break;
            case self::DATA_TYPE_ORDER_STATUS:
                $orderData = [
                    self::ORDER_STATUSES => $order ? $this->getOrderStatusesData($order) : [],
                ];
                break;
            case self::DATA_TYPE_ORDER:
            default:
                $orderData = $this->getAllOrderData($lengowOrder, $order);
        }
        return array_merge($orderReferences, $orderData);
    }

    /**
     * Get array of all the data of the order
     *
     * @param LengowOrderEntity $lengowOrder Lengow order instance
     * @param OrderEntity|null $order Shopware order instance
     *
     * @return array
     */
    private function getAllOrderData(LengowOrderEntity $lengowOrder, OrderEntity $order = null): array
    {
        $orderTypes = $lengowOrder->getOrderTypes();
        // get merchant order id
        $merchantOrderId = null;
        if ($order && $order->getStateMachineState()) {
            $currentDeliveryState = null;
            $currentOrderState = $order->getStateMachineState()->getTechnicalName();
            $orderDelivery = $order->getDeliveries() && $order->getDeliveries()->first()
                ? $order->getDeliveries()->first()
                : null;
            if ($orderDelivery && $orderDelivery->getStateMachineState()) {
                $currentDeliveryState = $orderDelivery->getStateMachineState()->getTechnicalName();
            }
            if ($currentOrderState === OrderStates::STATE_IN_PROGRESS
                && $currentDeliveryState === OrderDeliveryStates::STATE_SHIPPED
            ) {
                $merchantOrderId = OrderDeliveryStates::STATE_SHIPPED;
            } else {
                $merchantOrderId = $currentOrderState;
            }
        }
        // get merchant tracking data
        $merchantCarrier = null;
        $merchantTrackingNumber = null;
        $merchantTrackingUrl = null;
        $orderDelivery = $order && $order->getDeliveries() && $order->getDeliveries()->first()
            ? $order->getDeliveries()->first()
            : null;
        if ($orderDelivery) {
            $trackingCodes = $orderDelivery->getTrackingCodes();
            $merchantTrackingNumber = !empty($trackingCodes) ? end($trackingCodes) : null;
            $shippingMethod = $orderDelivery->getShippingMethod();
            $merchantCarrier = $shippingMethod ? $shippingMethod->getName() : null;
            $merchantTrackingUrl = $shippingMethod ? $shippingMethod->getTrackingUrl() : null;
        }
        return [
            self::ORDER_DELIVERY_COUNTRY_ISO => $lengowOrder->getDeliveryCountryIso(),
            self::ORDER_PROCESS_STATE => self::getOrderProcessLabel($lengowOrder->getOrderProcessState()),
            self::ORDER_STATUS => $lengowOrder->getOrderLengowState(),
            self::ORDER_MERCHANT_ORDER_STATUS => $merchantOrderId,
            self::ORDER_STATUSES => $order ? $this->getOrderStatusesData($order) : [],
            self::ORDER_TOTAL_PAID => $lengowOrder->getTotalPaid(),
            self::ORDER_MERCHANT_TOTAL_PAID => $order ? round($order->getAmountTotal(), 2) : null,
            self::ORDER_COMMISSION => $lengowOrder->getCommission(),
            self::ORDER_CURRENCY => $lengowOrder->getCurrency(),
            self::CUSTOMER => [
                self::CUSTOMER_NAME => !empty($lengowOrder->getCustomerName()) ? $lengowOrder->getCustomerName() : null,
                self::CUSTOMER_EMAIL => !empty($lengowOrder->getCustomerEmail())
                    ? $lengowOrder->getCustomerEmail()
                    : null,
                self::CUSTOMER_VAT_NUMBER => !empty($lengowOrder->getCustomerVatNumber())
                    ? $lengowOrder->getCustomerVatNumber()
                    : null,
            ],
            self::ORDER_DATE => $lengowOrder->getOrderDate()->getTimestamp(),
            self::ORDER_TYPES => [
                self::ORDER_TYPE_EXPRESS => isset($orderTypes[LengowOrder::TYPE_EXPRESS]),
                self::ORDER_TYPE_PRIME => isset($orderTypes[LengowOrder::TYPE_PRIME]),
                self::ORDER_TYPE_BUSINESS => isset($orderTypes[LengowOrder::TYPE_BUSINESS]),
                self::ORDER_TYPE_DELIVERED_BY_MARKETPLACE => isset(
                    $orderTypes[LengowOrder::TYPE_DELIVERED_BY_MARKETPLACE]
                ),
            ],
            self::ORDER_ITEMS => $lengowOrder->getOrderItem(),
            self::TRACKING => [
                self::TRACKING_CARRIER => !empty($lengowOrder->getCarrier())
                    ? $lengowOrder->getCarrier()
                    : null,
                self::TRACKING_METHOD => !empty($lengowOrder->getCarrierMethod())
                    ? $lengowOrder->getCarrierMethod()
                    : null,
                self::TRACKING_NUMBER => !empty($lengowOrder->getCarrierTracking())
                    ? $lengowOrder->getCarrierTracking()
                    : null,
                self::TRACKING_RELAY_ID => !empty($lengowOrder->getCarrierIdRelay())
                    ? $lengowOrder->getCarrierIdRelay()
                    : null,
                self::TRACKING_MERCHANT_CARRIER => $merchantCarrier,
                self::TRACKING_MERCHANT_TRACKING_NUMBER => $merchantTrackingNumber,
                self::TRACKING_MERCHANT_TRACKING_URL => $merchantTrackingUrl,
            ],
            self::ORDER_IS_REIMPORTED => $lengowOrder->isReimported(),
            self::ORDER_IS_IN_ERROR => $lengowOrder->isInError(),
            self::ERRORS => $this->getOrderErrorsData($lengowOrder),
            self::ORDER_ACTION_IN_PROGRESS => $order && $this->lengowAction->getActionsByOrderId($order->getId()),
            self::ACTIONS => $order ? $this->getOrderActionData($order) : [],
            self::CREATED_AT => $lengowOrder->getCreatedAt() ? $lengowOrder->getCreatedAt()->getTimestamp() : 0,
            self::UPDATED_AT => $lengowOrder->getUpdatedAt() ? $lengowOrder->getUpdatedAt()->getTimestamp() : 0,
            self::IMPORTED_AT => $lengowOrder->getImportedAt() ? $lengowOrder->getImportedAt()->getTimestamp() : 0,
        ];
    }

    /**
     * Get array of all the errors of a Lengow order
     *
     * @param LengowOrderEntity $lengowOrder Lengow order instance
     *
     * @return array
     */
    private function getOrderErrorsData(LengowOrderEntity $lengowOrder): array
    {
        $orderErrors = [];
        $errors = $this->lengowOrderError->getOrderErrors($lengowOrder->getId());
        if ($errors) {
            foreach ($errors as $error) {
                $orderErrors[] = [
                    self::ID => $error->getId(),
                    self::ERROR_TYPE => $error->getType() === LengowOrderError::TYPE_ERROR_IMPORT
                        ? self::TYPE_ERROR_IMPORT
                        : self::TYPE_ERROR_SEND,
                    self::ERROR_MESSAGE => $this->lengowLog->decodeMessage(
                        $error->getMessage(),
                        LengowTranslation::DEFAULT_ISO_CODE
                    ),
                    self::ERROR_FINISHED => $error->isFinished(),
                    self::ERROR_REPORTED => $error->isMail(),
                    self::CREATED_AT => $error->getCreatedAt()->getTimestamp(),
                    self::UPDATED_AT => $error->getUpdatedAt() ? $error->getUpdatedAt()->getTimestamp() : 0,
                ];
            }
        }
        return $orderErrors;
    }

    /**
     * Get array of all the actions of a Lengow order
     *
     * @param OrderEntity $order Shopware order instance
     *
     * @return array
     */
    private function getOrderActionData(OrderEntity $order): array
    {
        $orderActions = [];
        $actions = $this->lengowAction->getActionsByOrderId($order->getId());
        if ($actions) {
            foreach ($actions as $action) {
                $orderActions[] = [
                    self::ID => $action->getId(),
                    self::ACTION_ID => $action->getActionId(),
                    self::ACTION_PARAMETERS => $action->getParameters(),
                    self::ACTION_RETRY => $action->getRetry(),
                    self::ACTION_FINISH => $action->getState() === LengowAction::STATE_FINISH,
                    self::CREATED_AT => $action->getCreatedAt()->getTimestamp(),
                    self::UPDATED_AT => $action->getUpdatedAt() ? $action->getUpdatedAt()->getTimestamp() : 0,
                ];
            }
        }
        return $orderActions;
    }

    /**
     * Get array of all the statuses of an order
     *
     * @param OrderEntity $order Shopware order instance
     *
     * @return array
     */
    private function getOrderStatusesData(OrderEntity $order): array
    {
        $orderStatuses = [];
        // get current order state
        $currentOrderState = null;
        $currentUpdate = null;
        if ($order->getStateMachineState()) {
            $currentOrderState = $order->getStateMachineState()->getTechnicalName();
            $currentUpdate = $order->getStateMachineState()->getCreatedAt();
        }
        // get current order delivery state
        $orderDeliveryState = null;
        $orderDeliveryUpdate = null;
        $orderDelivery = $order->getDeliveries() && $order->getDeliveries()->first()
            ? $order->getDeliveries()->first()
            : null;
        if ($orderDelivery && $orderDelivery->getStateMachineState()) {
            $orderDeliveryState = $orderDelivery->getStateMachineState()->getTechnicalName();
            $orderDeliveryUpdate = $orderDelivery->getStateMachineState()->getCreatedAt();
        }
        // get current order transaction state
        $orderTransactionState = null;
        $orderTransactionUpdate = null;
        $orderTransaction = $order->getTransactions() && $order->getTransactions()->first()
            ? $order->getTransactions()->first()
            : null;
        if ($orderTransaction && $orderTransaction->getStateMachineState()) {
            $orderTransactionState = $orderTransaction->getStateMachineState()->getTechnicalName();
            $orderTransactionUpdate = $orderTransaction->getStateMachineState()->getCreatedAt();
        }
        if ($orderTransactionState) {
            $orderStatuses[] = [
                self::ORDER_MERCHANT_ORDER_STATUS => OrderTransactionStates::STATE_PAID,
                self::ORDER_STATUS => null,
                self::CREATED_AT => $orderTransactionUpdate ? $orderTransactionUpdate->getTimestamp() : 0,
            ];
            $orderStatuses[] = [
                self::ORDER_MERCHANT_ORDER_STATUS => OrderStates::STATE_IN_PROGRESS,
                self::ORDER_STATUS => LengowOrder::STATE_WAITING_SHIPMENT,
                self::CREATED_AT => $orderTransactionUpdate ? $orderTransactionUpdate->getTimestamp() : 0,
            ];
        }
        if ($orderDeliveryState && $orderDeliveryState === OrderDeliveryStates::STATE_SHIPPED) {
            $orderStatuses[] = [
                self::ORDER_MERCHANT_ORDER_STATUS => OrderDeliveryStates::STATE_SHIPPED,
                self::ORDER_STATUS => LengowOrder::STATE_SHIPPED,
                self::CREATED_AT => $orderDeliveryUpdate ? $orderDeliveryUpdate->getTimestamp() : 0,
            ];
        }
        if ($currentOrderState) {
            if ($currentOrderState === OrderStates::STATE_COMPLETED) {
                $orderStatuses[] = [
                    self::ORDER_MERCHANT_ORDER_STATUS => OrderStates::STATE_COMPLETED,
                    self::ORDER_STATUS => null,
                    self::CREATED_AT => $currentUpdate ? $currentUpdate->getTimestamp() : 0,
                ];
            } elseif ($currentOrderState === OrderStates::STATE_CANCELLED) {
                $orderStatuses[] = [
                    self::ORDER_MERCHANT_ORDER_STATUS => OrderStates::STATE_CANCELLED,
                    self::ORDER_STATUS => LengowOrder::STATE_CANCELED,
                    self::CREATED_AT => $currentUpdate ? $currentUpdate->getTimestamp() : 0,
                ];
            }
        }
        return $orderStatuses;
    }

    /**
     * Get all the data of the order at the time of import
     *
     * @param LengowOrderEntity $lengowOrder Lengow order instance
     *
     * @return array
     */
    private function getOrderExtraData(LengowOrderEntity $lengowOrder): array
    {
        $orderData = $lengowOrder->getExtra() ?: [];
        $updatedAt = $lengowOrder->getUpdatedAt() ? $lengowOrder->getUpdatedAt()->getTimestamp() : 0;
        $orderData[self::EXTRA_UPDATED_AT] = $lengowOrder->getImportedAt()
            ? $lengowOrder->getImportedAt()->getTimestamp()
            : $updatedAt;
        return $orderData;
    }

    /**
     * Get order process label
     *
     * @param int $orderProcess Lengow order process (new, import or finish)
     *
     * @return string
     */
    private static function getOrderProcessLabel(int $orderProcess): string
    {
        switch ($orderProcess) {
            case LengowOrder::PROCESS_STATE_NEW:
                return self::PROCESS_STATE_NEW;
            case LengowOrder::PROCESS_STATE_IMPORT:
                return self::PROCESS_STATE_IMPORT;
            case LengowOrder::PROCESS_STATE_FINISH:
            default:
                return self::PROCESS_STATE_FINISH;
        }
    }

    /**
     * Generates an error return for the Toolbox webservice
     *
     * @param int $httpCode request http code
     * @param string $error error message
     *
     * @return array
     */
    private function generateErrorReturn(int $httpCode, string $error): array
    {
        return [
            self::ERRORS => [
                self::ERROR_MESSAGE => $this->lengowLog->decodeMessage($error, LengowTranslation::DEFAULT_ISO_CODE),
                self::ERROR_CODE => $httpCode,
            ],
        ];
    }
}
