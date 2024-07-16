<?php declare(strict_types=1);

namespace Lengow\Connector\Service;

use Exception;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;
use Lengow\Connector\Entity\Lengow\OrderError\OrderErrorDefinition as LengowOrderErrorDefinition;
use Lengow\Connector\Entity\Lengow\OrderError\OrderErrorEntity as LengowOrderErrorEntity;
use Lengow\Connector\Exception\LengowException;
use Lengow\Connector\Util\EnvironmentInfoProvider;

/**
 * Class LengowConnector
 * @package Lengow\Connector\Service
 */
class LengowImport
{
    /* Import GET params */
    public const PARAM_TOKEN = 'token';
    public const PARAM_TYPE = 'type';
    public const PARAM_SALES_CHANNEL_ID = 'sales_channel_id';
    public const PARAM_MARKETPLACE_SKU = 'marketplace_sku';
    public const PARAM_MARKETPLACE_NAME = 'marketplace_name';
    public const PARAM_DELIVERY_ADDRESS_ID = 'delivery_address_id';
    public const PARAM_DAYS = 'days';
    public const PARAM_CREATED_FROM = 'created_from';
    public const PARAM_CREATED_TO = 'created_to';
    public const PARAM_LENGOW_ORDER_ID = 'lengow_order_id';
    public const PARAM_LIMIT = 'limit';
    public const PARAM_LOG_OUTPUT = 'log_output';
    public const PARAM_DEBUG_MODE = 'debug_mode';
    public const PARAM_FORCE = 'force';
    public const PARAM_FORCE_SYNC = 'force_sync';
    public const PARAM_SYNC = 'sync';
    public const PARAM_GET_SYNC = 'get_sync';

    /* Import API arguments */
    public const ARG_ACCOUNT_ID = 'account_id';
    public const ARG_CATALOG_IDS = 'catalog_ids';
    public const ARG_MARKETPLACE = 'marketplace';
    public const ARG_MARKETPLACE_ORDER_DATE_FROM = 'marketplace_order_date_from';
    public const ARG_MARKETPLACE_ORDER_DATE_TO = 'marketplace_order_date_to';
    public const ARG_MARKETPLACE_ORDER_ID = 'marketplace_order_id';
    public const ARG_MERCHANT_ORDER_ID = 'merchant_order_id';
    public const ARG_NO_CURRENCY_CONVERSION = 'no_currency_conversion';
    public const ARG_PAGE = 'page';
    public const ARG_UPDATED_FROM = 'updated_from';
    public const ARG_UPDATED_TO = 'updated_to';

    /* Import types */
    public const TYPE_MANUAL = 'manual';
    public const TYPE_CRON = 'cron';
    public const TYPE_TOOLBOX = 'toolbox';

    /* Import Data */
    public const NUMBER_ORDERS_PROCESSED = 'number_orders_processed';
    public const NUMBER_ORDERS_CREATED = 'number_orders_created';
    public const NUMBER_ORDERS_UPDATED = 'number_orders_updated';
    public const NUMBER_ORDERS_FAILED = 'number_orders_failed';
    public const NUMBER_ORDERS_IGNORED = 'number_orders_ignored';
    public const NUMBER_ORDERS_NOT_FORMATTED = 'number_orders_not_formatted';
    public const ORDERS_CREATED = 'orders_created';
    public const ORDERS_UPDATED = 'orders_updated';
    public const ORDERS_FAILED = 'orders_failed';
    public const ORDERS_IGNORED = 'orders_ignored';
    public const ORDERS_NOT_FORMATTED = 'orders_not_formatted';
    public const ERRORS = 'errors';

    /**
     * @var int max interval time for order synchronization old versions (1 day)
     */
    private const MIN_INTERVAL_TIME = 86400;

    /**
     * @var int max import days for old versions (10 days)
     */
    private const MAX_INTERVAL_TIME = 864000;

    /**
     * @var int security interval time for cron synchronization (2 hours)
     */
    private const SECURITY_INTERVAL_TIME = 7200;

    /**
     * @var integer interval of minutes for cron synchronization
     */
    private const MINUTE_INTERVAL_TIME = 1;

    /**
     * @var LengowConnector Lengow connector service
     */
    private $lengowConnector;

    /**
     * @var LengowConfiguration Lengow configuration service
     */
    private $lengowConfiguration;

    /**
     * @var LengowLog Lengow log service
     */
    private $lengowLog;

    /**
     * @var LengowImportOrder Lengow import order service
     */
    private $lengowImportOrder;

    /**
     * @var LengowOrder Lengow order service
     */
    private $lengowOrder;

    /**
     * @var LengowOrderError Lengow order error service
     */
    private $lengowOrderError;

    /**
     * @var LengowSync Lengow sync service
     */
    private $lengowSync;

    /**
     * @var LengowActionSync Lengow action sync service
     */
    private $lengowActionSync;

    /**
     * @var string order id being imported
     */
    public static $currentOrder;

    /**
     * @var int account ID
     */
    private $accountId;

    /**
     * @var string Shopware sales channel id
     */
    private $salesChannelId;

    /**
     * @var int amount of products to export
     */
    private $limit;

    /**
     * @var boolean force import order even if there are errors
     */
    private $forceSync;

    /**
     * @var boolean see log or not
     */
    private $logOutput;

    /**
     * @var string import type (manual or cron)
     */
    private $importType;

    /**
     * @var bool import one order
     */
    private $importOneOrder = false;

    /**
     * @var bool use debug mode
     */
    private $debugMode = false;

    /**
     * @var string|null marketplace order sku
     */
    private $marketplaceSku;

    /**
     * @var string|null marketplace name
     */
    private $marketplaceName;

    /**
     * @var string|null Lengow order id
     */
    private $lengowOrderId;

    /**
     * @var int|null delivery address id
     */
    private $deliveryAddressId;

    /**
     * @var int|null imports orders updated since (timestamp)
     */
    private $updatedFrom;

    /**
     * @var int|null imports orders updated until (timestamp)
     */
    private $updatedTo;

    /**
     * @var int|null imports orders created since (timestamp)
     */
    private $createdFrom;

    /**
     * @var int|null imports orders created until (timestamp)
     */
    private $createdTo;

    /**
     * @var array sales channel catalog ids for import
     */
    private $salesChannelCatalogIds = [];

    /**
     * @var array catalog ids already imported
     */
    private $catalogIds = [];

    /**
     * @var array all orders created during the process
     */
    private $ordersCreated = [];

    /**
     * @var array all orders updated during the process
     */
    private $ordersUpdated = [];

    /**
     * @var array all orders failed during the process
     */
    private $ordersFailed = [];

    /**
     * @var array all orders ignored during the process
     */
    private $ordersIgnored = [];

    /**
     * @var array all incorrectly formatted orders that cannot be processed
     */
    private $ordersNotFormatted = [];

    /**
     * @var array all synchronization error (global or by shop)
     */
    private $errors = [];

    /**
     * LengowImport Construct
     *
     * @param LengowConnector $lengowConnector Lengow connector service
     * @param LengowConfiguration $lengowConfiguration Lengow configuration service
     * @param LengowLog $lengowLog Lengow log service
     * @param LengowImportOrder $lengowImportOrder Lengow import order service
     * @param LengowOrder $lengowOrder Lengow order service
     * @param LengowOrderError $lengowOrderError Lengow order error service
     * @param LengowSync $lengowSync Lengow sync service
     * @param LengowActionSync $lengowActionSync Lengow action sync service
     */
    public function __construct(
        LengowConnector $lengowConnector,
        LengowConfiguration $lengowConfiguration,
        LengowLog $lengowLog,
        LengowImportOrder $lengowImportOrder,
        LengowOrder $lengowOrder,
        LengowOrderError $lengowOrderError,
        LengowSync $lengowSync,
        LengowActionSync $lengowActionSync
    )
    {
        $this->lengowConnector = $lengowConnector;
        $this->lengowConfiguration = $lengowConfiguration;
        $this->lengowLog = $lengowLog;
        $this->lengowImportOrder = $lengowImportOrder;
        $this->lengowOrder = $lengowOrder;
        $this->lengowOrderError = $lengowOrderError;
        $this->lengowSync = $lengowSync;
        $this->lengowActionSync = $lengowActionSync;
    }

    /**
     * Init a new import
     *
     * @param array $params optional options
     * string marketplace_sku     Lengow marketplace order id to synchronize
     * string marketplace_name    Lengow marketplace name to synchronize
     * string type                Type of current import
     * string created_from        Synchronization of orders since
     * string created_to          Synchronization of orders until
     * int    delivery_address_id Lengow delivery address id to synchronize
     * int    order_lengow_id     Lengow order id in Magento
     * int    sales_channel_id    Sales channel id for current import
     * int    days                Synchronization interval time
     * int    limit               Maximum number of new orders created
     * bool   log_output          Display log messages
     * bool   debug_mode          Activate debug mode
     * bool   force_sync          Force synchronization order even if there are errors
     */
    public function init(array $params = []): void
    {
        // get generic params for synchronization
        $this->accountId = (int) $this->lengowConfiguration->get(LengowConfiguration::ACCOUNT_ID);
        $this->debugMode = $params[self::PARAM_DEBUG_MODE] ?? $this->lengowConfiguration->debugModeIsActive();
        $this->importType = $params[self::PARAM_TYPE] ?? self::TYPE_MANUAL;
        $this->forceSync = isset($params[self::PARAM_FORCE_SYNC]) && $params[self::PARAM_FORCE_SYNC];
        $this->logOutput = $params[self::PARAM_LOG_OUTPUT] ?? false;
        $this->salesChannelId = $params[self::PARAM_SALES_CHANNEL_ID] ?? null;
        // get params for synchronize one or all orders
        if (isset($params[self::PARAM_MARKETPLACE_SKU], $params[self::PARAM_MARKETPLACE_NAME])
            && $this->salesChannelId
        ) {
            $this->limit = 1;
            $this->importOneOrder = true;
            $this->marketplaceSku = $params[self::PARAM_MARKETPLACE_SKU];
            $this->marketplaceName = $params[self::PARAM_MARKETPLACE_NAME];
            if (isset($params[self::PARAM_DELIVERY_ADDRESS_ID]) && $params[self::PARAM_DELIVERY_ADDRESS_ID] !== 0) {
                $this->deliveryAddressId = $params[self::PARAM_DELIVERY_ADDRESS_ID];
            }
            if (isset($params[self::PARAM_LENGOW_ORDER_ID])) {
                $this->lengowOrderId = $params[self::PARAM_LENGOW_ORDER_ID];
                $this->forceSync = true;
            }
        } else {
            // set the time interval
            $this->setIntervalTime(
                $params[self::PARAM_DAYS] ?? null,
                $params[self::PARAM_CREATED_FROM] ?? null,
                $params[self::PARAM_CREATED_TO] ?? null
            );
            $this->limit = $params[self::PARAM_LIMIT] ?? 0;
        }
    }

    /**
     * Execute import : fetch orders and import them
     *
     * @return array
     */
    public function exec(): array
    {
        $syncOk = true;
        // checks if a synchronization is not already in progress
        if (!$this->canExecuteSynchronization()) {
            return $this->getResult();
        }
        // starts some processes necessary for synchronization
        $this->setupSynchronization();
        // get all sales channel enabled for synchronization
        /** @var SalesChannelEntity[] $salesChannels */
        $salesChannels = $this->lengowConfiguration->getLengowActiveSalesChannels($this->salesChannelId);
        foreach ($salesChannels as $salesChannel) {
            // synchronize all orders for a specific store
            if (!$this->synchronizeOrdersBySalesChannel($salesChannel)) {
                $syncOk = false;
            }
        }
        // get order synchronization result
        $result = $this->getResult();
        $this->lengowLog->write(
            LengowLog::CODE_IMPORT,
            $this->lengowLog->encodeMessage('log.import.sync_result', [
                'number_orders_processed' => $result[self::NUMBER_ORDERS_PROCESSED],
                'number_orders_created' => $result[self::NUMBER_ORDERS_CREATED],
                'number_orders_updated' => $result[self::NUMBER_ORDERS_UPDATED],
                'number_orders_failed' => $result[self::NUMBER_ORDERS_FAILED],
                'number_orders_ignored' => $result[self::NUMBER_ORDERS_IGNORED],
                'number_orders_not_formatted' => $result[self::NUMBER_ORDERS_NOT_FORMATTED],
            ]),
            $this->logOutput
        );
        // update last import date
        if (!$this->importOneOrder && $syncOk) {
            $this->setLastImport($this->importType);
        }
        // complete synchronization and start all necessary processes
        $this->finishSynchronization();
        return $result;
    }

    /**
     * Get last import (type and timestamp)
     *
     * @return array
     */
    public function getLastImport(): array
    {
        $timestampCron = $this->lengowConfiguration->get(LengowConfiguration::LAST_UPDATE_CRON_SYNCHRONIZATION);
        $timestampManual = $this->lengowConfiguration->get(LengowConfiguration::LAST_UPDATE_MANUAL_SYNCHRONIZATION);
        if ($timestampCron && $timestampManual) {
            if ((int) $timestampCron > (int) $timestampManual) {
                return ['type' => self::TYPE_CRON, 'timestamp' => (int) $timestampCron];
            }
            return ['type' => self::TYPE_MANUAL, 'timestamp' => (int) $timestampManual];
        }
        if ($timestampCron && !$timestampManual) {
            return ['type' => self::TYPE_CRON, 'timestamp' => (int) $timestampCron];
        }
        if ($timestampManual && !$timestampCron) {
            return ['type' => self::TYPE_MANUAL, 'timestamp' => (int) $timestampManual];
        }
        return ['type' => 'none', 'timestamp' => 'none'];
    }

    /**
     * Check if import is already in process
     *
     * @return bool
     */
    public function isInProcess(): bool
    {
        $timestamp = (int) $this->lengowConfiguration->get(LengowConfiguration::SYNCHRONIZATION_IN_PROGRESS);
        // security check : if last import is more than 60 seconds old => authorize new import to be launched
        return ($timestamp > 0 && ($timestamp + (60 * self::MINUTE_INTERVAL_TIME)) > time());
    }

    /**
     * Get Rest time to make re-import order
     *
     * @return int
     */
    public function restTimeToImport(): int
    {
        $timestamp = (int) $this->lengowConfiguration->get(LengowConfiguration::SYNCHRONIZATION_IN_PROGRESS);
        if ($timestamp > 0) {
            return $timestamp + (60 * self::MINUTE_INTERVAL_TIME) - time();
        }
        return 0;
    }

    /**
     * Set interval time for order synchronization
     *
     * @param int|null $days Import period
     * @param string|null $createdFrom Import of orders since
     * @param string|null $createdTo Import of orders until
     */
    private function setIntervalTime(float $days = null, string $createdFrom = null, string $createdTo = null): void
    {
        if ($createdFrom && $createdTo) {
            // retrieval of orders created from ... until ...
            $createdFromTimestamp = strtotime($createdFrom);
            if ($createdFrom === $createdTo) {
                $createdToTimestamp = strtotime($createdTo) + self::MIN_INTERVAL_TIME -1;
            } else {
                $createdToTimestamp = strtotime($createdTo);
            }

            $intervalTime = $createdToTimestamp - $createdFromTimestamp;
            $this->createdFrom = $createdFromTimestamp;
            $this->createdTo = $intervalTime > self::MAX_INTERVAL_TIME
                ? $createdFromTimestamp + self::MAX_INTERVAL_TIME
                : $createdToTimestamp;
            return;
        }
        if ($days) {
            $intervalTime = floor($days * self::MIN_INTERVAL_TIME);
            $intervalTime = $intervalTime > self::MAX_INTERVAL_TIME ? self::MAX_INTERVAL_TIME : $intervalTime;
        } else {
            // order recovery updated since ... days
            $importDays = (float) $this->lengowConfiguration->get(LengowConfiguration::SYNCHRONIZATION_DAY_INTERVAL);
            $intervalTime = floor($importDays * self::MIN_INTERVAL_TIME);
            // add security for older versions of the plugin
            $intervalTime = $intervalTime < self::MIN_INTERVAL_TIME ? self::MIN_INTERVAL_TIME : $intervalTime;
            $intervalTime = $intervalTime > self::MAX_INTERVAL_TIME ? self::MAX_INTERVAL_TIME : $intervalTime;
            // get dynamic interval time for cron synchronization
            $lastImport = $this->getLastImport();
            $lastSettingUpdate = (int) $this->lengowConfiguration->get(LengowConfiguration::LAST_UPDATE_SETTING);
            if ($this->importType !== self::TYPE_MANUAL
                && $lastImport['timestamp'] !== 'none'
                && $lastImport['timestamp'] > $lastSettingUpdate
            ) {
                $lastIntervalTime = (time() - $lastImport['timestamp']) + self::SECURITY_INTERVAL_TIME;
                $intervalTime = $lastIntervalTime > $intervalTime ? $intervalTime : $lastIntervalTime;
            }
        }
        $this->updatedFrom = time() - $intervalTime;
        $this->updatedTo = time();
    }

    /**
     * Checks if a synchronization is not already in progress
     *
     * @return bool
     */
    private function canExecuteSynchronization(): bool
    {
        $globalError = null;
        // checks if the process can start
        if (!$this->debugMode && !$this->importOneOrder && $this->isInProcess()) {
            $globalError = $this->lengowLog->encodeMessage('lengow_log.error.rest_time_to_import', [
                'rest_time' => $this->restTimeToImport()
            ]);
            $this->lengowLog->write(LengowLog::CODE_IMPORT, $globalError, $this->logOutput);
        } elseif (!$this->lengowConnector->isValidAuth($this->logOutput)) {
            $globalError = $this->lengowLog->encodeMessage('lengow_log.error.credentials_not_valid');
            $this->lengowLog->write(LengowLog::CODE_IMPORT, $globalError, $this->logOutput);
        }
        // if we have a global error, we stop the process directly
        if ($globalError) {
            $this->errors[0] = $globalError;
            if (isset($this->lengowOrderId) && $this->lengowOrderId) {
                $this->lengowOrderError->finishOrderErrors($this->lengowOrderId);
                $this->lengowOrderError->create($this->lengowOrderId, $globalError);
            }
            return false;
        }
        return true;
    }

    /**
     * Starts some processes necessary for synchronization
     */
    private function setupSynchronization(): void
    {
        // suppress log files when too old
        $this->lengowLog->cleanLog();
        if (!$this->importOneOrder) {
            $this->setInProcess();
        }
        // check Lengow catalogs for order synchronization
        if (!$this->importOneOrder && $this->importType === self::TYPE_MANUAL) {
            $this->lengowSync->syncCatalog();
        }
        // start order synchronization
        $this->lengowLog->write(
            LengowLog::CODE_IMPORT,
            $this->lengowLog->encodeMessage('log.import.start', [
                'type' => $this->importType
            ]),
            $this->logOutput
        );
        if ($this->debugMode) {
            $this->lengowLog->write(
                LengowLog::CODE_IMPORT,
                $this->lengowLog->encodeMessage('log.import.debug_mode_active'),
                $this->logOutput
            );
        }
    }

    /**
     * Return the synchronization result
     *
     * @return array
     */
    private function getResult(): array
    {
        $nbOrdersCreated = count($this->ordersCreated);
        $nbOrdersUpdated = count($this->ordersUpdated);
        $nbOrdersFailed = count($this->ordersFailed);
        $nbOrdersIgnored = count($this->ordersIgnored);
        $nbOrdersNotFormatted = count($this->ordersNotFormatted);
        $nbOrdersProcessed = $nbOrdersCreated
            + $nbOrdersUpdated
            + $nbOrdersFailed
            + $nbOrdersIgnored
            + $nbOrdersNotFormatted;
        return [
            self::NUMBER_ORDERS_PROCESSED => $nbOrdersProcessed,
            self::NUMBER_ORDERS_CREATED => $nbOrdersCreated,
            self::NUMBER_ORDERS_UPDATED => $nbOrdersUpdated,
            self::NUMBER_ORDERS_FAILED => $nbOrdersFailed,
            self::NUMBER_ORDERS_IGNORED => $nbOrdersIgnored,
            self::NUMBER_ORDERS_NOT_FORMATTED => $nbOrdersNotFormatted,
            self::ORDERS_CREATED => $this->ordersCreated,
            self::ORDERS_UPDATED => $this->ordersUpdated,
            self::ORDERS_FAILED => $this->ordersFailed,
            self::ORDERS_IGNORED => $this->ordersIgnored,
            self::ORDERS_NOT_FORMATTED => $this->ordersNotFormatted,
            self::ERRORS => $this->errors,
        ];
    }

    /**
     * Synchronize all orders for a specific sales channel
     *
     * @param SalesChannelEntity $salesChannel Shopware sales channel instance
     *
     * @return bool
     */
    private function synchronizeOrdersBySalesChannel(SalesChannelEntity $salesChannel): bool
    {
        $this->lengowLog->write(
            LengowLog::CODE_IMPORT,
            $this->lengowLog->encodeMessage('log.import.start_for_sales_channel', [
                'sales_channel_name' => $salesChannel->getName(),
                'sales_channel_id' => $salesChannel->getId(),
            ]),
            $this->logOutput
        );
        // check sales channel catalog ids
        if (!$this->checkCatalogIds($salesChannel)) {
            return true;
        }
        try {
            // get orders from Lengow API
            $orders = $this->getOrdersFromApi($salesChannel);
            $numberOrdersFound = count($orders);
            if ($this->importOneOrder) {
                $this->lengowLog->write(
                    LengowLog::CODE_IMPORT,
                    $this->lengowLog->encodeMessage('log.import.find_one_order', [
                        'nb_order' => $numberOrdersFound,
                        'marketplace_sku' => $this->marketplaceSku,
                        'marketplace_name' => $this->marketplaceName,
                        'account_id' => $this->accountId,
                    ]),
                    $this->logOutput
                );
            } else {
                $this->lengowLog->write(
                    LengowLog::CODE_IMPORT,
                    $this->lengowLog->encodeMessage('log.import.find_all_orders', [
                        'nb_order' => $numberOrdersFound,
                        'sales_channel_name' => $salesChannel->getName(),
                        'sales_channel_id' => $salesChannel->getId(),
                    ]),
                    $this->logOutput
                );
            }
            if ($numberOrdersFound === 0 && $this->importOneOrder) {
                throw new LengowException($this->lengowLog->encodeMessage('lengow_log.error.order_not_found'));
            }
            if ($numberOrdersFound > 0) {
                // import orders in Shopware
                $this->importOrders($orders, $salesChannel);
            }
        } catch (LengowException $e) {
            $errorMessage = $e->getMessage();
        } catch (Exception $e) {
            $errorMessage = '[Shopware error]: "' . $e->getMessage()
                . '" in ' . $e->getFile() . ' on line ' . $e->getLine();
        }
        if (!isset($errorMessage)) {
            return true;
        }
        if (isset($this->lengowOrderId) && $this->lengowOrderId) {
            $this->lengowOrderError->finishOrderErrors($this->lengowOrderId);
            $this->lengowOrderError->create($this->lengowOrderId, $errorMessage);
        }
        $decodedMessage = $this->lengowLog->decodeMessage($errorMessage, LengowTranslation::DEFAULT_ISO_CODE);
        $this->lengowLog->write(
            LengowLog::CODE_IMPORT,
            $this->lengowLog->encodeMessage('log.import.import_failed', [
                'decoded_message' => $decodedMessage
            ]),
            $this->logOutput
        );
        $this->errors[$salesChannel->getName()] = $errorMessage;
        return false;
    }

    /**
     * Check catalog ids for a sales channel
     *
     * @param SalesChannelEntity $salesChannel Shopware sales channel instance
     *
     * @return bool
     */
    private function checkCatalogIds(SalesChannelEntity $salesChannel): bool
    {
        if ($this->importOneOrder) {
            return true;
        }
        $salesChannelCatalogIds = [];
        $catalogIds = $this->lengowConfiguration->getCatalogIds($salesChannel->getId());
        foreach ($catalogIds as $catalogId) {
            if (array_key_exists($catalogId, $this->catalogIds)) {
                $this->lengowLog->write(
                    LengowLog::CODE_IMPORT,
                    $this->lengowLog->encodeMessage('log.import.catalog_id_already_used', [
                        'catalog_id' => $catalogId,
                        'sales_channel_name' => $this->catalogIds[$catalogId]['sales_channel_name'],
                        'sales_channel_id' => $this->catalogIds[$catalogId]['sales_channel_id'],
                    ]),
                    $this->logOutput
                );
            } else {
                $this->catalogIds[$catalogId] = [
                    'sales_channel_id' => $salesChannel->getId(),
                    'sales_channel_name' => $salesChannel->getName(),
                ];
                $salesChannelCatalogIds[] = $catalogId;
            }
        }
        if (!empty($salesChannelCatalogIds)) {
            $this->salesChannelCatalogIds = $salesChannelCatalogIds;
            return true;
        }
        $message = $this->lengowLog->encodeMessage(
            'lengow_log.error.no_catalog_for_sales_channel',
            [
                'sales_channel_name' => $salesChannel->getName(),
                'sales_channel_id' => $salesChannel->getId(),
            ]
        );
        $this->lengowLog->write(LengowLog::CODE_IMPORT, $message, $this->logOutput);
        $this->errors[$salesChannel->getName()] = $message;
        return false;
    }

    /**
     * Call Lengow order API
     *
     * @param SalesChannelEntity $salesChannel Shopware sales channel instance
     *
     * @throws Exception|LengowException
     *
     * @return array
     */
    private function getOrdersFromApi(SalesChannelEntity $salesChannel): array
    {
        $page = 1;
        $orders = [];
        if ($this->importOneOrder) {
            $this->lengowLog->write(
                LengowLog::CODE_IMPORT,
                $this->lengowLog->encodeMessage('log.import.connector_get_order', [
                    'marketplace_sku' => $this->marketplaceSku,
                    'marketplace_name' => $this->marketplaceName,
                ]),
                $this->logOutput
            );
        } else {
            $dateFrom = $this->createdFrom
                ? $this->lengowConfiguration->gmtDate($this->createdFrom)
                : $this->lengowConfiguration->date($this->updatedFrom);
            $dateTo = $this->createdTo
                ? $this->lengowConfiguration->gmtDate($this->createdTo)
                : $this->lengowConfiguration->date($this->updatedTo);
            $this->lengowLog->write(
                LengowLog::CODE_IMPORT,
                $this->lengowLog->encodeMessage('log.import.connector_get_all_order', [
                    'date_from' => $dateFrom,
                    'date_to' => $dateTo,
                    'catalog_id' => implode(', ', $this->salesChannelCatalogIds),
                ]),
                $this->logOutput
            );
        }
        do {
            try {
                $currencyConversion = !$this->lengowConfiguration->get(
                    LengowConfiguration::CURRENCY_CONVERSION_ENABLED
                );
                if ($this->importOneOrder) {
                    $results = $this->lengowConnector->get(
                        LengowConnector::API_ORDER,
                        [
                            self::ARG_MARKETPLACE_ORDER_ID => $this->marketplaceSku,
                            self::ARG_MARKETPLACE => $this->marketplaceName,
                            self::ARG_ACCOUNT_ID => $this->accountId,
                            self::ARG_NO_CURRENCY_CONVERSION => $currencyConversion,
                        ],
                        LengowConnector::FORMAT_STREAM,
                        '',
                        $this->logOutput
                    );
                } else {
                    if ($this->createdFrom && $this->createdTo) {
                        $timeParams = [
                            self::ARG_MARKETPLACE_ORDER_DATE_FROM => $this->lengowConfiguration->gmtDate(
                                $this->createdFrom,
                                EnvironmentInfoProvider::DATE_ISO_8601
                            ),
                            self::ARG_MARKETPLACE_ORDER_DATE_TO => $this->lengowConfiguration->gmtDate(
                                $this->createdTo,
                                EnvironmentInfoProvider::DATE_ISO_8601
                            ),
                        ];
                    } else {
                        $timeParams = [
                            self::ARG_UPDATED_FROM => $this->lengowConfiguration->date(
                                $this->updatedFrom,
                                EnvironmentInfoProvider::DATE_ISO_8601
                            ),
                            self::ARG_UPDATED_TO => $this->lengowConfiguration->date(
                                $this->updatedTo,
                                EnvironmentInfoProvider::DATE_ISO_8601
                            ),
                        ];
                    }
                    $results = $this->lengowConnector->get(
                        LengowConnector::API_ORDER,
                        array_merge($timeParams, [
                            self::ARG_CATALOG_IDS => implode(',', $this->salesChannelCatalogIds),
                            self::ARG_ACCOUNT_ID => $this->accountId,
                            self::ARG_PAGE => $page,
                            self::ARG_NO_CURRENCY_CONVERSION => $currencyConversion,
                        ]),
                        LengowConnector::FORMAT_STREAM,
                        '',
                        $this->logOutput
                    );
                }
            } catch (Exception $e) {
                $message = $this->lengowLog->decodeMessage($e->getMessage(), LengowTranslation::DEFAULT_ISO_CODE);
                throw new LengowException(
                    $this->lengowLog->encodeMessage('lengow_log.exception.error_lengow_webservice', [
                        'error_code' => $e->getCode(),
                        'error_message' => $message,
                        'sales_channel_name' => $salesChannel->getName(),
                        'sales_channel_id' => $salesChannel->getId(),
                    ])
                );
            }
            // don't add true, decoded data are used as object
            $results = json_decode($results, false);
            if (!is_object($results)) {
                throw new LengowException(
                    $this->lengowLog->encodeMessage('lengow_log.exception.no_connection_webservice', [
                        'sales_channel_name' => $salesChannel->getName(),
                        'sales_channel_id' => $salesChannel->getId(),
                    ])
                );
            }
            // construct array orders
            foreach ($results->results as $order) {
                $orders[] = $order;
            }
            $page++;
            $finish = ($results->next === null || $this->importOneOrder);
        } while (!$finish);
        return $orders;
    }

    /**
     * Create or update order in Shopware
     *
     * @param array $orders API orders
     * @param SalesChannelEntity $salesChannel Shopware sales channel instance
     */
    private function importOrders(array $orders, SalesChannelEntity $salesChannel): void
    {
        $importFinished = false;
        foreach ($orders as $orderData) {
            if (!$this->importOneOrder) {
                $this->setInProcess();
            }
            $nbPackage = 0;
            $marketplaceSku = (string) $orderData->marketplace_order_id;
            if ($this->debugMode) {
                $marketplaceSku .= '--' . time();
            }
            // set current order to cancel events from SendActionSubscriber
            self::$currentOrder = $marketplaceSku;
            // if order contains no package
            if (empty($orderData->packages)) {
                $message = $this->lengowLog->encodeMessage('log.import.error_no_package');
                $this->lengowLog->write(LengowLog::CODE_IMPORT, $message, $this->logOutput, $marketplaceSku);
                $this->addOrderNotFormatted($marketplaceSku, $message, $orderData);
                continue;
            }
            // start import
            foreach ($orderData->packages as $packageData) {
                $nbPackage++;
                // check whether the package contains a shipping address
                if (!isset($packageData->delivery->id)) {
                    $message = $this->lengowLog->encodeMessage('log.import.error_no_delivery_address');
                    $this->lengowLog->write(LengowLog::CODE_IMPORT, $message, $this->logOutput, $marketplaceSku);
                    $this->addOrderNotFormatted($marketplaceSku, $message, $orderData);
                    continue;
                }
                $packageDeliveryAddressId = (int) $packageData->delivery->id;
                $firstPackage = $nbPackage <= 1;
                // check the package for re-import order
                if ($this->importOneOrder
                    && $this->deliveryAddressId !== null
                    && $this->deliveryAddressId !== $packageDeliveryAddressId
                ) {
                    $message = $this->lengowLog->encodeMessage('log.import.error_wrong_package_number');
                    $this->lengowLog->write(LengowLog::CODE_IMPORT, $message, $this->logOutput, $marketplaceSku);
                    $this->addOrderNotFormatted($marketplaceSku, $message, $orderData);
                    continue;
                }
                try {
                    // try to import or update order
                    $this->lengowImportOrder->init(
                        [
                            LengowImportOrder::PARAM_SALES_CHANNEL => $salesChannel,
                            LengowImportOrder::PARAM_FORCE_SYNC => $this->forceSync,
                            LengowImportOrder::PARAM_DEBUG_MODE => $this->debugMode,
                            LengowImportOrder::PARAM_LOG_OUTPUT => $this->logOutput,
                            LengowImportOrder::PARAM_MARKETPLACE_SKU => $marketplaceSku,
                            LengowImportOrder::PARAM_DELIVERY_ADDRESS_ID => $packageDeliveryAddressId,
                            LengowImportOrder::PARAM_ORDER_DATA => $orderData,
                            LengowImportOrder::PARAM_PACKAGE_DATA => $packageData,
                            LengowImportOrder::PARAM_FIRST_PACKAGE => $firstPackage,
                            LengowImportOrder::PARAM_IMPORT_ONE_ORDER => $this->importOneOrder,
                        ]
                    );
                    $result = $this->lengowImportOrder->exec();
                    // synchronize the merchant order id with Lengow
                    $this->synchronizeMerchantOrderId($result);
                    // save the result of the order synchronization by type
                    $this->saveSynchronizationResult($result);
                } catch (LengowException $e) {
                    $errorMessage = $e->getMessage();
                } catch (Exception $e) {
                    $errorMessage = '[Shopware error]: "' . $e->getMessage()
                        . '" in ' . $e->getFile() . ' on line ' . $e->getLine();
                }
                if (isset($errorMessage)) {
                    $decodedMessage = $this->lengowLog->decodeMessage(
                        $errorMessage,
                        LengowTranslation::DEFAULT_ISO_CODE
                    );
                    $this->lengowLog->write(
                        LengowLog::CODE_IMPORT,
                        $this->lengowLog->encodeMessage('log.import.order_import_failed', [
                            'decoded_message' => $decodedMessage
                        ]),
                        $this->logOutput,
                        $marketplaceSku
                    );
                    unset($errorMessage);
                    continue;
                }
                // if limit is set
                if ($this->limit > 0 && count($this->ordersCreated) === $this->limit) {
                    $importFinished = true;
                    break;
                }
            }
            // clean current order
            self::$currentOrder = null;
            if ($importFinished) {
                break;
            }
        }
    }

    /**
     * Return an array of result for order not formatted
     *
     * @param string $marketplaceSku id lengow of current order
     * @param string $errorMessage Error message
     * @param mixed $orderData API order data
     */
    private function addOrderNotFormatted(string $marketplaceSku, string $errorMessage, $orderData): void
    {
        $messageDecoded = $this->lengowLog->decodeMessage($errorMessage, LengowTranslation::DEFAULT_ISO_CODE);
        $this->ordersNotFormatted[] = [
            LengowImportOrder::MERCHANT_ORDER_ID => null,
            LengowImportOrder::MERCHANT_ORDER_REFERENCE => null,
            LengowImportOrder::LENGOW_ORDER_ID => $this->lengowOrderId,
            LengowImportOrder::MARKETPLACE_SKU => $marketplaceSku,
            LengowImportOrder::MARKETPLACE_NAME => (string) $orderData->marketplace,
            LengowImportOrder::DELIVERY_ADDRESS_ID => null,
            LengowImportOrder::SHOP_ID => $this->salesChannelId,
            LengowImportOrder::CURRENT_ORDER_STATUS => (string) $orderData->lengow_status,
            LengowImportOrder::PREVIOUS_ORDER_STATUS => (string) $orderData->lengow_status,
            LengowImportOrder::ERRORS => [$messageDecoded],
        ];
    }

    /**
     * Synchronize the merchant order id with Lengow
     *
     * @param array $result synchronization order result
     */
    private function synchronizeMerchantOrderId(array $result): void
    {
        if (!$this->debugMode && $result[LengowImportOrder::RESULT_TYPE] === LengowImportOrder::RESULT_CREATED) {
            /** @var OrderEntity $shopwareOrder */
            $shopwareOrder = $this->lengowOrder->getOrderById($result[LengowImportOrder::MERCHANT_ORDER_ID]);
            $success = $this->lengowOrder->synchronizeOrder($shopwareOrder, $this->logOutput);
            $messageKey = $success
                ? 'log.import.order_synchronized_with_lengow'
                : 'log.import.order_not_synchronized_with_lengow';
            $this->lengowLog->write(
                LengowLog::CODE_IMPORT,
                $this->lengowLog->encodeMessage($messageKey, [
                    'order_id' => $shopwareOrder->getOrderNumber()
                ]),
                $this->logOutput,
                $result[LengowImportOrder::MARKETPLACE_SKU]
            );
        }
    }

    /**
     * Save the result of the order synchronization by type
     *
     * @param array $result synchronization order result
     */
    private function saveSynchronizationResult(array $result): void
    {
        $resultType = $result[LengowImportOrder::RESULT_TYPE];
        unset($result[LengowImportOrder::RESULT_TYPE]);
        switch ($resultType) {
            case LengowImportOrder::RESULT_CREATED:
                $this->ordersCreated[] = $result;
                break;
            case LengowImportOrder::RESULT_UPDATED:
                $this->ordersUpdated[] = $result;
                break;
            case LengowImportOrder::RESULT_FAILED:
                $this->ordersFailed[] = $result;
                break;
            case LengowImportOrder::RESULT_IGNORED:
                $this->ordersIgnored[] = $result;
                break;
        }
    }

    /**
     * Complete synchronization and start all necessary processes
     */
    private function finishSynchronization(): void
    {
        // finish import process
        $this->setEnd();
        $this->lengowLog->write(
            LengowLog::CODE_IMPORT,
            $this->lengowLog->encodeMessage('log.import.end', [
                'type' => $this->importType
            ]),
            $this->logOutput
        );
        // check if order action is finish (Ship / Cancel)
        if (!$this->debugMode && !$this->importOneOrder && $this->importType === self::TYPE_MANUAL) {
            $this->lengowActionSync->checkFinishAction($this->logOutput);
            $this->lengowActionSync->checkOldAction($this->logOutput);
            $this->lengowActionSync->checkNotSentAction($this->logOutput);
        }
        // sending email in error for orders
        if (!$this->debugMode
            && !$this->importOneOrder
            && $this->lengowConfiguration->get(LengowConfiguration::REPORT_MAIL_ENABLED)
        ) {
            $this->sendMailAlert($this->logOutput);
        }
    }

    /**
     * Check order error table and send mail for order not imported correctly
     *
     * @param bool $logOutput see log or not
     */
    private function sendMailAlert(bool $logOutput = false): void
    {
        $orderErrorCollection = $this->lengowOrderError->getOrderErrorNotSent();
        if ($orderErrorCollection === null) {
            return;
        }
        $subject = $this->lengowLog->decodeMessage('lengow_log.mail_report.subject_report_mail');
        $mailBody = $this->getMailAlertBody($orderErrorCollection);
        $emails = $this->lengowConfiguration->getReportEmailAddress();
        foreach ($emails as $email) {
            if ($email === '') {
                continue;
            }
            if ($this->sendMail($email, $subject, $mailBody)) {
                $this->lengowLog->write(
                    LengowLog::CODE_MAIL_REPORT,
                    $this->lengowLog->encodeMessage('log.mail_report.send_mail_to', [
                        'email' => $email,
                    ]),
                    $logOutput
                );
            } else {
                $this->lengowLog->write(
                    LengowLog::CODE_MAIL_REPORT,
                    $this->lengowLog->encodeMessage('log.mail_report.unable_send_mail_to', [
                        'email' => $email,
                    ]),
                    $logOutput
                );
            }
        }
    }

    /**
     * Get mail alert body and put mail attribute at true in order lengow record
     *
     * @param EntityCollection $orderErrorCollection order errors ready to be sent
     *
     * @return string
     */
    private function getMailAlertBody(EntityCollection $orderErrorCollection): string
    {
        $mailBody = '';
        if ($orderErrorCollection->count() === 0) {
            return $mailBody;
        }
        $pluginLinks = $this->lengowSync->getPluginLinks();
        $support = $this->lengowLog->decodeMessage('lengow_log.mail_report.no_error_in_report_mail', null, [
            'support_link' => $pluginLinks[LengowSync::LINK_TYPE_SUPPORT],
        ]);
        $mailBody = '<h2>'
            . $this->lengowLog->decodeMessage('lengow_log.mail_report.subject_report_mail')
            . '</h2><p><ul>';
        /** @var LengowOrderErrorEntity $orderError */
        foreach ($orderErrorCollection as $orderError) {
            $order = $this->lengowLog->decodeMessage('lengow_log.mail_report.order', null, [
                'marketplace_sku' => $orderError->getOrder()->getMarketplaceSku(),
            ]);
            $message = $orderError->getMessage() !== ''
                ? $this->lengowLog->decodeMessage($orderError->getMessage())
                : $support;
            $mailBody .= '<li>' . $order . ' - ' . $message . '</li>';
            $this->lengowOrderError->update($orderError->getId(), [
                LengowOrderErrorDefinition::FIELD_MAIL => true,
            ]);
        }
        $mailBody .= '</ul></p>';
        return $mailBody;
    }

    /**
     * Send mail without template
     *
     * @param string $email send mail at
     * @param string $subject subject email
     * @param string $body body email
     *
     * @return bool
     */
    private function sendMail(string $email, string $subject, string $body): bool
    {
        // use of the php mail function because the different versions of Shopware do not have any service in common
        // Shopware 6.2 / 6.3 uses the SwiftMailer service and Shopware 6.4 uses the native Symfony Mailer service
        // Shopware has a mail service, but it does not have the same class name depending on the versions
        $headers = [
            'MIME-Version: 1.0',
            'Content-type: text/html; charset=iso-8859-1',
            'From: Lengow <' . $this->lengowConfiguration->get('core.basicInformation.email') . '>',
        ];
        return mail($email, $subject, $body, implode("\r\n", $headers));
    }

    /**
     * Record the date of the last import
     *
     * @param string $type (cron or manual)
     */
    private function setLastImport(string $type): void
    {
        $time = (string) time();
        if ($type === self::TYPE_CRON) {
            $this->lengowConfiguration->set(LengowConfiguration::LAST_UPDATE_CRON_SYNCHRONIZATION, $time);
        } else {
            $this->lengowConfiguration->set(LengowConfiguration::LAST_UPDATE_MANUAL_SYNCHRONIZATION, $time);
        }
    }

    /**
     * Set import to "in process" state
     */
    private function setInProcess(): void
    {
        $this->lengowConfiguration->set(LengowConfiguration::SYNCHRONIZATION_IN_PROGRESS, (string) time());
    }

    /**
     * Set import to finished
     */
    private function setEnd(): void
    {
        $this->lengowConfiguration->set(LengowConfiguration::SYNCHRONIZATION_IN_PROGRESS, '');
    }
}
