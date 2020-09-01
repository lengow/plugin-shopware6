<?php declare(strict_types=1);

namespace Lengow\Connector\Service;

use \Exception;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;
use Lengow\Connector\Exception\LengowException;

/**
 * Class LengowConnector
 * @package Lengow\Connector\Service
 */
class LengowImport
{
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
    private $marketplaceSku = null;

    /**
     * @var string|null marketplace name
     */
    private $marketplaceName = null;

    /**
     * @var string|null Lengow order id
     */
    private $lengowOrderId = null;

    /**
     * @var int|null delivery address id
     */
    private $deliveryAddressId = null;

    /**
     * @var int|null imports orders updated since (timestamp)
     */
    private $updatedFrom = null;

    /**
     * @var int|null imports orders updated until (timestamp)
     */
    private $updatedTo = null;

    /**
     * @var int|null imports orders created since (timestamp)
     */
    private $createdFrom = null;

    /**
     * @var int|null imports orders created until (timestamp)
     */
    private $createdTo = null;

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
     */
    public function __construct(
        LengowConnector $lengowConnector,
        LengowConfiguration $lengowConfiguration,
        LengowLog $lengowLog,
        LengowImportOrder $lengowImportOrder
    )
    {
        $this->lengowConnector = $lengowConnector;
        $this->lengowConfiguration = $lengowConfiguration;
        $this->lengowLog = $lengowLog;
        $this->lengowImportOrder = $lengowImportOrder;
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
     * int    store_id            store id for current import
     * int    days                import period
     * int    limit               number of orders to import
     * bool   log_output          display log messages
     * bool   debug_mode          debug mode
     */
    public function init(array $params): void
    {
        // get generic params for synchronisation
        $this->accountId = (int)$this->lengowConfiguration->get('lengowAccountId');
        $this->debugMode = $params['debug_mode'] ?? $this->lengowConfiguration->debugModeIsActive();
        $this->importType = $params['type'] ?? self::TYPE_MANUAL;
        $this->logOutput = $params['log_output'] ?? false;
        $this->salesChannelId = $params['sales_channel_id'] ?? null;
        // get params for synchronise one or all orders
        if (isset($params['marketplace_sku']) && isset($params['marketplace_name']) && $this->salesChannelId) {
            $this->limit = 1;
            $this->importOneOrder = true;
            $this->marketplaceSku = $params['marketplace_sku'];
            $this->marketplaceName = $params['marketplace_name'];
            if (isset($params['delivery_address_id']) && $params['delivery_address_id'] !== 0) {
                $this->deliveryAddressId = $params['delivery_address_id'];
            }
            if (isset($params['lengow_order_id'])) {
                $this->lengowOrderId = $params['lengow_order_id'];
            }
        } else {
            // set the time interval
            $this->setIntervalTime(
                $params['days'] ?? null,
                $params['created_from'] ?? null,
                $params['created_to'] ?? null
            );
            $this->limit = $params['limit'] ?? 0;
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
        if ($this->isInProcess() && !$this->debugMode && !$this->importOneOrder) {
            $globalError = $this->lengowLog->encodeMessage('lengow_log.error.rest_time_to_import', [
                'rest_time' => $this->restTimeToImport()
            ]);
            $this->lengowLog->write(LengowLog::CODE_IMPORT, $globalError, $this->logOutput);
        } elseif (!$this->lengowConnector->isValidAuth($this->logOutput)) {
            $globalError = $this->lengowLog->encodeMessage('lengow_log.error.credentials_not_valid');
            $this->lengowLog->write(LengowLog::CODE_IMPORT, $globalError, $this->logOutput);
        } else {
            if (!$this->importOneOrder) {
                self::setInProcess();
            }
            // check Lengow catalogs for order synchronisation
            if (!$this->importOneOrder && $this->importType === self::TYPE_MANUAL) {
                // TODO get new catalog ids with cms API
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
                        $error[$salesChannel->getId()] = $errorCatalogIds;
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
                        throw new LengowException('lengow_log.error.order_not_found');
                    } elseif ($totalOrders <= 0) {
                        continue;
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
                        // TODO Finish old order errors and create a new one
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
                    $error[$salesChannel->getId()] = $errorMessage;
                    unset($errorMessage);
                    continue;
                }
                unset($salesChannel);
            }
            if (!$this->importOneOrder) {
                $this->lengowLog->write(
                    LengowLog::CODE_IMPORT,
                    $this->lengowLog->encodeMessage('lengow_log.error.nb_order_imported', [
                        'nb_order' => $orderNew
                    ]),
                    $this->logOutput
                );
                $this->lengowLog->write(
                    LengowLog::CODE_IMPORT,
                    $this->lengowLog->encodeMessage('lengow_log.error.nb_order_updated', [
                        'nb_order' => $orderUpdate
                    ]),
                    $this->logOutput
                );
                $this->lengowLog->write(
                    LengowLog::CODE_IMPORT,
                    $this->lengowLog->encodeMessage('lengow_log.error.nb_order_with_error', [
                        'nb_order' => $orderError
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
        }
        if ($globalError) {
            $error[0] = $globalError;
            if ($this->lengowOrderId !== null) {
                // TODO Finish old order errors and create a new one
            }
        }
        if ($this->importOneOrder) {
            $result['error'] = $error;
            return $result;
        } else {
            return [
                'order_new' => $orderNew,
                'order_update' => $orderUpdate,
                'order_error' => $orderError,
                'error' => $error,
            ];
        }
    }

    /**
     * Get last import (type and timestamp)
     *
     * @return array
     */
    public function getLastImport(): array
    {
        $timestampCron = $this->lengowConfiguration->get('lengowLastImportCron');
        $timestampManual = $this->lengowConfiguration->get('lengowLastImportManual');
        if ($timestampCron && $timestampManual) {
            if ((int)$timestampCron > (int)$timestampManual) {
                return ['type' => self::TYPE_CRON, 'timestamp' => (int)$timestampCron];
            } else {
                return ['type' => self::TYPE_MANUAL, 'timestamp' => (int)$timestampManual];
            }
        }
        if ($timestampCron && !$timestampManual) {
            return ['type' => self::TYPE_CRON, 'timestamp' => (int)$timestampCron];
        }
        if ($timestampManual && !$timestampCron) {
            return ['type' => self::TYPE_MANUAL, 'timestamp' => (int)$timestampManual];
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
        $timestamp = (int)$this->lengowConfiguration->get('lengowImportInProgress');
        // security check : if last import is more than 60 seconds old => authorize new import to be launched
        if ($timestamp > 0 && ($timestamp + (60 * 1)) > time()) {
            return true;
        }
        return false;
    }

    /**
     * Get Rest time to make re import order
     *
     * @return int
     */
    public function restTimeToImport(): int
    {
        $timestamp = (int)$this->lengowConfiguration->get('lengowImportInProgress');
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
        $time = (string)time();
        if ($type === LengowImport::TYPE_CRON) {
            $this->lengowConfiguration->set('lengowLastImportCron', $time);
        } else {
            $this->lengowConfiguration->set('lengowLastImportManual', $time);
        }
    }

    /**
     * Set import to "in process" state
     */
    private function setInProcess(): void
    {
        $this->lengowConfiguration->set('lengowImportInProgress', (string)time());
    }

    /**
     * Set import to finished
     */
    private function setEnd(): void
    {
        $this->lengowConfiguration->set('lengowImportInProgress', '');
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
     * @throws LengowException
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
            $dateFrom = $this->createdFrom ?? $this->updatedFrom;
            $dateTo = $this->createdTo ?? $this->updatedTo;
            $this->lengowLog->write(
                LengowLog::CODE_IMPORT,
                $this->lengowLog->encodeMessage('log.import.connector_get_all_order', [
                    'date_from' => date('Y-m-d H:i:s', $dateFrom),
                    'date_to' => date('Y-m-d H:i:s', $dateTo),
                    'catalog_id' => implode(', ', $this->salesChannelCatalogIds),
                ]),
                $this->logOutput
            );
        }
        do {
            try {
                $currencyConversion = !(bool)$this->lengowConfiguration->get('lengowCurrencyConversion');
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
                            'marketplace_order_date_from' => date('c', $this->createdFrom),
                            'marketplace_order_date_to' => date('c', $this->createdTo),
                        ];
                    } else {
                        $timeParams = [
                            'updated_from' => date('c', $this->updatedFrom),
                            'updated_to' => date('c', $this->updatedTo),
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
            $results = json_decode($results);
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
        } while ($finish != true);
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
            $marketplaceSku = (string)$orderData->marketplace_order_id;
            if ($this->debugMode) {
                $marketplaceSku .= '--' . time();
            }
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
                $packageDeliveryAddressId = (int)$packageData->delivery->id;
                $firstPackage = $nbPackage > 1 ? false : true;
                // check the package for re-import order
                if ($this->importOneOrder) {
                    if ($this->deliveryAddressId !== null && $this->deliveryAddressId !== $packageDeliveryAddressId) {
                        $this->lengowLog->write(
                            LengowLog::CODE_IMPORT,
                            $this->lengowLog->encodeMessage('log.import.error_wrong_package_number'),
                            $this->logOutput,
                            $marketplaceSku
                        );
                        continue;
                    }
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
                    // TODO Synchronise order id with Lengow
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
            $intervalTime = (int)($createdToTimestamp - $createdFromTimestamp);
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
            $importDays = (int)$this->lengowConfiguration->get('lengowImportDays');
            $intervalTime = $importDays * 86400;
            // add security for older versions of the plugin
            $intervalTime = $intervalTime < self::MIN_INTERVAL_TIME ? self::MIN_INTERVAL_TIME : $intervalTime;
            $intervalTime = $intervalTime > self::MAX_INTERVAL_TIME ? self::MAX_INTERVAL_TIME : $intervalTime;
            // get dynamic interval time for cron synchronisation
            $lastImport = $this->getLastImport();
            $lastSettingUpdate = (int)$this->lengowConfiguration->get('lengowLastSettingUpdate');
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
}
