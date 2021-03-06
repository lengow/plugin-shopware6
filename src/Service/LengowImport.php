<?php declare(strict_types=1);

namespace Lengow\Connector\Service;

use Exception;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;
use Lengow\Connector\Entity\Lengow\OrderError\OrderErrorEntity;
use Lengow\Connector\Exception\LengowException;

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
    public const PARAM_SYNC = 'sync';
    public const PARAM_GET_SYNC = 'get_sync';

    /**
     * @var string manual import type
     */
    public const TYPE_MANUAL = 'manual';

    /**
     * @var string cron import type
     */
    public const TYPE_CRON = 'cron';

    /**
     * @var int max interval time for order synchronisation old versions (1 day)
     */
    private const MIN_INTERVAL_TIME = 86400;

    /**
     * @var int max import days for old versions (10 days)
     */
    private const MAX_INTERVAL_TIME = 864000;

    /**
     * @var int security interval time for cron synchronisation (2 hours)
     */
    private const SECURITY_INTERVAL_TIME = 7200;

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
     * string marketplace_sku     lengow marketplace order id to import
     * string marketplace_name    lengow marketplace name to import
     * string type                type of current import
     * string created_from        import of orders since
     * string created_to          import of orders until
     * int    delivery_address_id Lengow delivery address id to import
     * int    order_lengow_id     Lengow order id in Magento
     * int    sales_channel_id    sales channel id for current import
     * int    days                import period
     * int    limit               number of orders to import
     * bool   log_output          display log messages
     * bool   debug_mode          debug mode
     */
    public function init(array $params = []): void
    {
        // get generic params for synchronisation
        $this->accountId = (int) $this->lengowConfiguration->get(LengowConfiguration::ACCOUNT_ID);
        $this->debugMode = $params[self::PARAM_DEBUG_MODE] ?? $this->lengowConfiguration->debugModeIsActive();
        $this->importType = $params[self::PARAM_TYPE] ?? self::TYPE_MANUAL;
        $this->logOutput = $params[self::PARAM_LOG_OUTPUT] ?? false;
        $this->salesChannelId = $params[self::PARAM_SALES_CHANNEL_ID] ?? null;
        // get params for synchronise one or all orders
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
        $orderNew = 0;
        $orderUpdate = 0;
        $orderError = 0;
        $error = [];
        $globalError = false;
        $syncOk = true;
        // clean log files
        $this->lengowLog->cleanLog();
        if (!$this->debugMode && !$this->importOneOrder && $this->isInProcess()) {
            $globalError = $this->lengowLog->encodeMessage('lengow_log.error.rest_time_to_import', [
                'rest_time' => $this->restTimeToImport()
            ]);
            $this->lengowLog->write(LengowLog::CODE_IMPORT, $globalError, $this->logOutput);
        } elseif (!$this->lengowConnector->isValidAuth($this->logOutput)) {
            $globalError = $this->lengowLog->encodeMessage('lengow_log.error.credentials_not_valid');
            $this->lengowLog->write(LengowLog::CODE_IMPORT, $globalError, $this->logOutput);
        } else {
            if (!$this->importOneOrder) {
                $this->setInProcess();
            }
            // check Lengow catalogs for order synchronisation
            if (!$this->importOneOrder && $this->importType === self::TYPE_MANUAL) {
                $this->lengowSync->syncCatalog();
            }
            // start order synchronisation
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
            // get all sales channel enabled for synchronisation
            /** @var SalesChannelEntity[] $salesChannels */
            $salesChannels = $this->lengowConfiguration->getLengowActiveSalesChannels();
            foreach ($salesChannels as $salesChannel) {
                if ($this->salesChannelId !== null && $salesChannel->getId() !== $this->salesChannelId) {
                    continue;
                }
                $this->lengowLog->write(
                    LengowLog::CODE_IMPORT,
                    $this->lengowLog->encodeMessage('log.import.start_for_sales_channel', [
                        'sales_channel_name' => $salesChannel->getName(),
                        'sales_channel_id' => $salesChannel->getId(),
                    ]),
                    $this->logOutput
                );
                try {
                    // check sales channel catalog ids
                    if (!$this->checkCatalogIds($salesChannel)) {
                        $errorCatalogIds = $this->lengowLog->encodeMessage(
                            'lengow_log.error.no_catalog_for_sales_channel',
                            [
                                'sales_channel_name' => $salesChannel->getName(),
                                'sales_channel_id' => $salesChannel->getId(),
                            ]
                        );
                        $this->lengowLog->write(LengowLog::CODE_IMPORT, $errorCatalogIds, $this->logOutput);
                        $error[$salesChannel->getName()] = $errorCatalogIds;
                        continue;
                    }
                    // get orders from Lengow API
                    $orders = $this->getOrdersFromApi($salesChannel);
                    $totalOrders = count($orders);
                    if ($this->importOneOrder) {
                        $this->lengowLog->write(
                            LengowLog::CODE_IMPORT,
                            $this->lengowLog->encodeMessage('log.import.find_one_order', [
                                'nb_order' => $totalOrders,
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
                                'nb_order' => $totalOrders,
                                'sales_channel_name' => $salesChannel->getName(),
                                'sales_channel_id' => $salesChannel->getId(),
                            ]),
                            $this->logOutput
                        );
                    }
                    if ($totalOrders <= 0 && $this->importOneOrder) {
                        throw new LengowException(
                            $this->lengowLog->encodeMessage('lengow_log.error.order_not_found')
                        );
                    }
                    if ($totalOrders <= 0) {
                        continue;
                    }
                    if ($this->lengowOrderId !== null) {
                        $this->lengowOrderError->finishOrderErrors($this->lengowOrderId);
                    }
                    $result = $this->importOrders($orders, $salesChannel);
                    if (!$this->importOneOrder) {
                        $orderNew += $result['order_new'];
                        $orderUpdate += $result['order_update'];
                        $orderError += $result['order_error'];
                    }
                } catch (LengowException $e) {
                    $errorMessage = $e->getMessage();
                } catch (Exception $e) {
                    $errorMessage = '[Shopware error] "' . $e->getMessage() . '" '
                        . $e->getFile() . ' | ' . $e->getLine();
                }
                if (isset($errorMessage)) {
                    $syncOk = false;
                    if ($this->lengowOrderId !== null) {
                        $this->lengowOrderError->finishOrderErrors($this->lengowOrderId);
                        $this->lengowOrderError->create($this->lengowOrderId, $errorMessage);
                    }
                    $decodedMessage = $this->lengowLog->decodeMessage(
                        $errorMessage,
                        LengowTranslation::DEFAULT_ISO_CODE
                    );
                    $this->lengowLog->write(
                        LengowLog::CODE_IMPORT,
                        $this->lengowLog->encodeMessage('log.import.import_failed', [
                            'decoded_message' => $decodedMessage
                        ]),
                        $this->logOutput
                    );
                    $error[$salesChannel->getName()] = $errorMessage;
                    unset($errorMessage);
                    continue;
                }
                unset($salesChannel);
            }
            if (!$this->importOneOrder) {
                $this->lengowLog->write(
                    LengowLog::CODE_IMPORT,
                    $this->lengowLog->encodeMessage('lengow_log.error.nb_order_imported', [
                        'nb_order' => $orderNew,
                    ]),
                    $this->logOutput
                );
                $this->lengowLog->write(
                    LengowLog::CODE_IMPORT,
                    $this->lengowLog->encodeMessage('lengow_log.error.nb_order_updated', [
                        'nb_order' => $orderUpdate,
                    ]),
                    $this->logOutput
                );
                $this->lengowLog->write(
                    LengowLog::CODE_IMPORT,
                    $this->lengowLog->encodeMessage('lengow_log.error.nb_order_with_error', [
                        'nb_order' => $orderError,
                    ]),
                    $this->logOutput
                );
            }
            // update last import date
            if (!$this->importOneOrder && $syncOk) {
                $this->setLastImport($this->importType);
            }
            // finish import process
            $this->setEnd();
            $this->lengowLog->write(
                LengowLog::CODE_IMPORT,
                $this->lengowLog->encodeMessage('log.import.end', [
                    'type' => $this->importType
                ]),
                $this->logOutput
            );
            // sending email in error for orders
            if (!$this->debugMode
                && !$this->importOneOrder
                && $this->lengowConfiguration->get(LengowConfiguration::REPORT_MAIL_ENABLED)
            ) {
                $this->sendMailAlert($this->logOutput);
            }
            // check if order action is finish (Ship / Cancel)
            if (!$this->debugMode && !$this->importOneOrder && $this->importType === self::TYPE_MANUAL) {
                $this->lengowActionSync->checkFinishAction($this->logOutput);
                $this->lengowActionSync->checkOldAction($this->logOutput);
                $this->lengowActionSync->checkNotSentAction($this->logOutput);
            }
        }
        if ($globalError) {
            $error[0] = $globalError;
            if ($this->lengowOrderId !== null) {
                $this->lengowOrderError->finishOrderErrors($this->lengowOrderId);
                $this->lengowOrderError->create($this->lengowOrderId, $globalError);
            }
        }
        if ($this->importOneOrder) {
            $result['error'] = $error;
            return $result;
        }
        return [
            'order_new' => $orderNew,
            'order_update' => $orderUpdate,
            'order_error' => $orderError,
            'error' => $error,
        ];
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
        return ($timestamp > 0 && ($timestamp + (60 * 1)) > time());
    }

    /**
     * Get Rest time to make re import order
     *
     * @return int
     */
    public function restTimeToImport(): int
    {
        $timestamp = (int) $this->lengowConfiguration->get(LengowConfiguration::SYNCHRONIZATION_IN_PROGRESS);
        if ($timestamp > 0) {
            return $timestamp + (60 * 1) - time();
        }
        return 0;
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
                            'marketplace_order_id' => $this->marketplaceSku,
                            'marketplace' => $this->marketplaceName,
                            'account_id' => $this->accountId,
                            'no_currency_conversion' => $currencyConversion,
                        ],
                        LengowConnector::FORMAT_STREAM,
                        '',
                        $this->logOutput
                    );
                } else {
                    if ($this->createdFrom && $this->createdTo) {
                        $timeParams = [
                            'marketplace_order_date_from' => $this->lengowConfiguration->gmtDate(
                                $this->createdFrom,
                                LengowConfiguration::API_DATE_TIME_FORMAT
                            ),
                            'marketplace_order_date_to' => $this->lengowConfiguration->gmtDate(
                                $this->createdTo,
                                LengowConfiguration::API_DATE_TIME_FORMAT
                            ),
                        ];
                    } else {
                        $timeParams = [
                            'updated_from' => $this->lengowConfiguration->date(
                                $this->updatedFrom,
                                LengowConfiguration::API_DATE_TIME_FORMAT
                            ),
                            'updated_to' => $this->lengowConfiguration->date(
                                $this->updatedTo,
                                LengowConfiguration::API_DATE_TIME_FORMAT
                            ),
                        ];
                    }
                    $results = $this->lengowConnector->get(
                        LengowConnector::API_ORDER,
                        array_merge($timeParams, [
                            'catalog_ids' => implode(',', $this->salesChannelCatalogIds),
                            'account_id' => $this->accountId,
                            'page' => $page,
                            'no_currency_conversion' => $currencyConversion,
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
            $results = json_decode($results, false);
            if ($results === null || !is_object($results)) {
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
     *
     * @return array|false
     */
    private function importOrders(array $orders, SalesChannelEntity $salesChannel)
    {
        $orderNew = 0;
        $orderUpdate = 0;
        $orderError = 0;
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
                $this->lengowLog->write(
                    LengowLog::CODE_IMPORT,
                    $this->lengowLog->encodeMessage('log.import.error_no_package'),
                    $this->logOutput,
                    $marketplaceSku
                );
                continue;
            }
            // start import
            foreach ($orderData->packages as $packageData) {
                $nbPackage++;
                // check whether the package contains a shipping address
                if (!isset($packageData->delivery->id)) {
                    $this->lengowLog->write(
                        LengowLog::CODE_IMPORT,
                        $this->lengowLog->encodeMessage('log.import.error_no_delivery_address'),
                        $this->logOutput,
                        $marketplaceSku
                    );
                    continue;
                }
                $packageDeliveryAddressId = (int) $packageData->delivery->id;
                $firstPackage = $nbPackage <= 1;
                // check the package for re-import order
                if ($this->importOneOrder
                    && $this->deliveryAddressId !== null
                    && $this->deliveryAddressId !== $packageDeliveryAddressId
                ) {
                    $this->lengowLog->write(
                        LengowLog::CODE_IMPORT,
                        $this->lengowLog->encodeMessage('log.import.error_wrong_package_number'),
                        $this->logOutput,
                        $marketplaceSku
                    );
                    continue;
                }
                try {
                    // try to import or update order
                    $this->lengowImportOrder->init(
                        [
                            'sales_channel' => $salesChannel,
                            'debug_mode' => $this->debugMode,
                            'log_output' => $this->logOutput,
                            'marketplace_sku' => $marketplaceSku,
                            'delivery_address_id' => $packageDeliveryAddressId,
                            'order_data' => $orderData,
                            'package_data' => $packageData,
                            'first_package' => $firstPackage,
                            'import_one_order' => $this->importOneOrder,
                        ]
                    );
                    $order = $this->lengowImportOrder->exec();
                } catch (LengowException $e) {
                    $errorMessage = $e->getMessage();
                } catch (Exception $e) {
                    $errorMessage = '[Shopware error]: "' . $e->getMessage() . '" '
                        . $e->getFile() . ' | ' . $e->getLine();
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
                // sync to lengow if no debug_mode
                if (!$this->debugMode && isset($order['order_new']) && $order['order_new']) {
                    /** @var OrderEntity $shopwareOrder */
                    $shopwareOrder = $this->lengowOrder->getOrderById($order['order_id']);
                    $synchro = $this->lengowOrder->synchronizeOrder($shopwareOrder, $this->logOutput);
                    $messageKey = $synchro
                        ? 'log.import.order_synchronized_with_lengow'
                        : 'log.import.order_not_synchronized_with_lengow';
                    $this->lengowLog->write(
                        LengowLog::CODE_IMPORT,
                        $this->lengowLog->encodeMessage($messageKey, [
                            'order_id' => $shopwareOrder->getOrderNumber()
                        ]),
                        $this->logOutput,
                        $marketplaceSku
                    );
                    unset($shopwareOrder);
                }
                // if re-import order -> return order information
                if (isset($order) && $this->importOneOrder) {
                    return $order;
                }
                if (isset($order)) {
                    if (isset($order['order_new']) && $order['order_new']) {
                        $orderNew++;
                    } elseif (isset($order['order_update']) && $order['order_update']) {
                        $orderUpdate++;
                    } elseif (isset($order['order_error']) && $order['order_error']) {
                        $orderError++;
                    }
                }
                // clean process
                self::$currentOrder = null;
                unset($importOrder, $order);
                // if limit is set
                if ($this->limit > 0 && $orderNew === $this->limit) {
                    $importFinished = true;
                    break;
                }
            }
            if ($importFinished) {
                break;
            }
        }
        return [
            'order_new' => $orderNew,
            'order_update' => $orderUpdate,
            'order_error' => $orderError,
        ];
    }

    /**
     * Set interval time for order synchronisation
     *
     * @param int|null $days Import period
     * @param string|null $createdFrom Import of orders since
     * @param string|null $createdTo Import of orders until
     */
    private function setIntervalTime(int $days = null, string $createdFrom = null, string $createdTo = null): void
    {
        if ($createdFrom && $createdTo) {
            // retrieval of orders created from ... until ...
            $createdFromTimestamp = strtotime($createdFrom);
            $createdToTimestamp = strtotime($createdTo) + 86399;
            $intervalTime = $createdToTimestamp - $createdFromTimestamp;
            $this->createdFrom = $createdFromTimestamp;
            $this->createdTo = $intervalTime > self::MAX_INTERVAL_TIME
                ? $createdFromTimestamp + self::MAX_INTERVAL_TIME
                : $createdToTimestamp;
            return;
        }
        if ($days) {
            $intervalTime = $days * 86400;
            $intervalTime = $intervalTime > self::MAX_INTERVAL_TIME ? self::MAX_INTERVAL_TIME : $intervalTime;
        } else {
            // order recovery updated since ... days
            $importDays = (int) $this->lengowConfiguration->get(LengowConfiguration::SYNCHRONIZATION_DAY_INTERVAL);
            $intervalTime = $importDays * 86400;
            // add security for older versions of the plugin
            $intervalTime = $intervalTime < self::MIN_INTERVAL_TIME ? self::MIN_INTERVAL_TIME : $intervalTime;
            $intervalTime = $intervalTime > self::MAX_INTERVAL_TIME ? self::MAX_INTERVAL_TIME : $intervalTime;
            // get dynamic interval time for cron synchronisation
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
     * Check order error table and send mail for order not imported correctly
     *
     * @param bool $logOutput see log or not
     *
     * @return bool
     */
    private function sendMailAlert(bool $logOutput = false): bool
    {
        $success = true;
        $orderErrorCollection = $this->lengowOrderError->getOrderErrorNotSent();
        if ($orderErrorCollection === null) {
            return $success;
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
                $success = false;
            }
        }
        return $success;
    }

    /**
     * Get mail alert body and put mail attribute at true in order lengow record
     *
     * @param EntityCollection $orderErrorCollection order errors ready to be send
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
        /** @var OrderErrorEntity $orderError */
        foreach ($orderErrorCollection as $orderError) {
            $order = $this->lengowLog->decodeMessage('lengow_log.mail_report.order', null, [
                'marketplace_sku' => $orderError->getOrder()->getMarketplaceSku(),
            ]);
            $message = $orderError->getMessage() !== ''
                ? $this->lengowLog->decodeMessage($orderError->getMessage())
                : $support;
            $mailBody .= '<li>' . $order . ' - ' . $message . '</li>';
            $this->lengowOrderError->update($orderError->getId(), [
                'mail' => true,
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
        // Shopware has a mail service but it does not have the same class name depending on the versions
        $headers = [
            'MIME-Version: 1.0',
            'Content-type: text/html; charset=iso-8859-1',
            'From: Lengow <' . $this->lengowConfiguration->get('core.basicInformation.email') . '>',
        ];
        return mail($email, $subject, $body, implode("\r\n", $headers));
    }
}
