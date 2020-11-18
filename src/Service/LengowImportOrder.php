<?php declare(strict_types=1);

namespace Lengow\Connector\Service;

use \DateTime;
use \Exception;
use Doctrine\DBAL\Connection;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\Order\OrderConversionContext;
use Shopware\Core\Checkout\Cart\Order\OrderConverter;
use Shopware\Core\Checkout\Cart\Price\QuantityPriceCalculator;
use Shopware\Core\Checkout\Cart\Price\Struct\CartPrice;
use Shopware\Core\Checkout\Cart\Price\Struct\QuantityPriceDefinition;
use Shopware\Core\Checkout\Cart\SalesChannel\CartService;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderDelivery\OrderDeliveryStates;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionStates;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Checkout\Order\OrderStates;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Doctrine\RetryableQuery;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\Currency\CurrencyCollection;
use Shopware\Core\System\Currency\CurrencyEntity;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextFactory;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextService;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;
use Lengow\Connector\Components\LengowMarketplace;
use Lengow\Connector\Entity\Lengow\Order\OrderEntity as LengowOrderEntity;
use Lengow\Connector\Entity\Lengow\OrderError\OrderErrorEntity as LengowOrderErrorEntity;
use Lengow\Connector\Exception\LengowException;
use Lengow\Connector\Factory\LengowMarketplaceFactory;

/**
 * Class LengowImportOrder
 * @package Lengow\Connector\Service
 */
class LengowImportOrder
{
    /**
     * @var string result for order imported
     */
    private const RESULT_NEW = 'new';

    /**
     * @var string result for order updated
     */
    private const RESULT_UPDATE = 'update';

    /**
     * @var string result for order in error
     */
    private const RESULT_ERROR = 'error';

    /**
     * @var int interval of months for order import
     */
    private const MONTH_INTERVAL_TIME = 3;

    /**
     * @var LengowConfiguration Lengow configuration service
     */
    private $lengowConfiguration;

    /**
     * @var LengowLog Lengow log service
     */
    private $lengowLog;

    /**
     * @var LengowMarketplaceFactory Lengow marketplace factory
     */
    private $lengowMarketplaceFactory;

    /**
     * @var LengowOrderError Lengow order error service
     */
    private $lengowOrderError;

    /**
     * @var LengowOrder Lengow order service
     */
    private $lengowOrder;

    /**
     * @var LengowOrderLine Lengow order line service
     */
    private $lengowOrderLine;

    /**
     * @var LengowProduct Lengow product service
     */
    private $lengowProduct;

    /**
     * @var LengowCustomer Lengow customer service
     */
    private $lengowCustomer;

    /**
     * @var LengowAddress Lengow address service
     */
    private $lengowAddress;

    /**
     * @var EntityRepositoryInterface Shopware currency repository
     */
    private $currencyRepository;

    /**
     * @var SalesChannelEntity Shopware sales channel entity
     */
    private $salesChannel;

    /**
     * @var SalesChannelContextFactory Shopware sales channel context factory
     */
    private $salesChannelContextFactory;

    /**
     * @var CartService Shopware cart service
     */
    private $cartService;

    /**
     * @var OrderConverter Shopware order converter service
     */
    private $orderConverter;

    /**
     * @var QuantityPriceCalculator Shopware quantity price calculator service
     */
    private $calculator;

    /**
     * @var Connection Doctrine connection service
     */
    private $connection;

    /**
     * @var array valid states lengow to create a Lengow order
     */
    private $lengowStates = [
        LengowOrder::STATE_WAITING_SHIPMENT,
        LengowOrder::STATE_SHIPPED,
        LengowOrder::STATE_CLOSED,
    ];

    /**
     * @var bool use debug mode
     */
    private $debugMode;

    /**
     * @var bool display log messages
     */
    private $logOutput;

    /**
     * @var LengowMarketplace Lengow marketplace instance
     */
    private $lengowMarketplace;

    /**
     * @var string id lengow of current order
     */
    private $marketplaceSku;

    /**
     * @var int id of delivery address for current order
     */
    private $deliveryAddressId;

    /**
     * @var mixed API order data
     */
    private $orderData;

    /**
     * @var mixed API package data
     */
    private $packageData;

    /**
     * @var bool is first package
     */
    private $firstPackage;

    /**
     * @var bool import one order var from lengow import
     */
    private $importOneOrder;

    /**
     * @var string marketplace order state
     */
    private $orderStateMarketplace;

    /**
     * @var string Lengow order state
     */
    private $orderStateLengow;

    /**
     * @var CurrencyEntity Shopware currency instance
     */
    private $currency;

    /**
     * @var float order processing fee
     */
    private $processingFee;

    /**
     * @var float order shipping cost
     */
    private $shippingCost;

    /**
     * @var float order total amount
     */
    private $orderAmount;

    /**
     * @var int number of order items
     */
    private $orderItems;

    /**
     * @var array order types (is_express, is_prime...)
     */
    private $orderTypes;

    /**
     * @var string carrier name
     */
    private $carrierName;

    /**
     * @var string carrier method
     */
    private $carrierMethod;

    /**
     * @var string carrier tracking number
     */
    private $trackingNumber;

    /**
     * @var string carrier relay id
     */
    private $relayId;

    /**
     * @var bool re-import order
     */
    private $isReimported = false;

    /**
     * @var bool order shipped by marketplace
     */
    private $shippedByMp = false;

    /**
     * LengowImportOrder Construct
     *
     * @param LengowConfiguration $lengowConfiguration Lengow configuration service
     * @param LengowLog $lengowLog Lengow log service
     * @param LengowMarketplaceFactory $lengowMarketplaceFactory Lengow marketplace factory
     * @param LengowOrderError $lengowOrderError Lengow order error service
     * @param LengowOrder $lengowOrder Lengow order service
     * @param LengowOrderLine $lengowOrderLine Lengow order line service
     * @param LengowProduct $lengowProduct Lengow product service
     * @param LengowCustomer $lengowCustomer Lengow customer service
     * @param LengowAddress $lengowAddress Lengow address service
     * @param EntityRepositoryInterface $currencyRepository Shopware currency repository
     * @param SalesChannelContextFactory $salesChannelContextFactory Shopware sales channel context factory
     * @param CartService $cartService Shopware cart service
     * @param OrderConverter $orderConverter Shopware order converter service
     * @param QuantityPriceCalculator $calculator Shopware quantity price calculator service
     * @param Connection $connection Doctrine connection service
     */
    public function __construct(
        LengowConfiguration $lengowConfiguration,
        LengowLog $lengowLog,
        LengowMarketplaceFactory $lengowMarketplaceFactory,
        LengowOrderError $lengowOrderError,
        LengowOrder $lengowOrder,
        LengowOrderLine $lengowOrderLine,
        LengowProduct $lengowProduct,
        LengowCustomer $lengowCustomer,
        LengowAddress $lengowAddress,
        EntityRepositoryInterface $currencyRepository,
        SalesChannelContextFactory $salesChannelContextFactory,
        CartService $cartService,
        OrderConverter $orderConverter,
        QuantityPriceCalculator $calculator,
        Connection $connection
    )
    {
        $this->lengowConfiguration = $lengowConfiguration;
        $this->lengowLog = $lengowLog;
        $this->lengowMarketplaceFactory = $lengowMarketplaceFactory;
        $this->lengowOrderError = $lengowOrderError;
        $this->lengowOrder = $lengowOrder;
        $this->lengowOrderLine = $lengowOrderLine;
        $this->lengowProduct = $lengowProduct;
        $this->lengowCustomer = $lengowCustomer;
        $this->lengowAddress = $lengowAddress;
        $this->currencyRepository = $currencyRepository;
        $this->salesChannelContextFactory = $salesChannelContextFactory;
        $this->cartService = $cartService;
        $this->orderConverter = $orderConverter;
        $this->calculator = $calculator;
        $this->connection = $connection;
    }

    /**
     * init a import order
     *
     * @param array $params optional options for load a import order
     *
     * @throws LengowException
     */
    public function init(array $params): void
    {
        $this->salesChannel = $params['sales_channel'];
        $this->debugMode = $params['debug_mode'];
        $this->logOutput = $params['log_output'];
        $this->marketplaceSku = $params['marketplace_sku'];
        $this->deliveryAddressId = $params['delivery_address_id'];
        $this->orderData = $params['order_data'];
        $this->packageData = $params['package_data'];
        $this->firstPackage = $params['first_package'];
        $this->importOneOrder = $params['import_one_order'];
        $this->lengowMarketplace = $this->lengowMarketplaceFactory->create($this->orderData->marketplace);
        $this->orderStateMarketplace = $this->orderData->marketplace_status;
        $this->orderStateLengow = $this->lengowMarketplace->getStateLengow($this->orderStateMarketplace);
    }

    /**
     * Create or update order
     *
     * @throws Exception|LengowException
     *
     * @return array|false
     */
    public function exec()
    {
        // if order error exist and not finished -> stop import order
        $orderErrors = $this->lengowOrderError->orderIsInError($this->marketplaceSku, $this->deliveryAddressId);
        if ($orderErrors) {
            /** @var LengowOrderErrorEntity $orderError */
            $orderError = $orderErrors->first();
            $decodedMessage = $this->lengowLog->decodeMessage(
                $orderError->getMessage(),
                LengowTranslation::DEFAULT_ISO_CODE
            );
            $dateMessage = $orderError->getCreatedAt()
                ? $this->lengowConfiguration->date($orderError->getCreatedAt()->getTimestamp())
                : $this->lengowConfiguration->date();
            $this->lengowLog->write(
                LengowLog::CODE_IMPORT,
                $this->lengowLog->encodeMessage('log.import.error_already_created', [
                    'decoded_message' => $decodedMessage,
                    'date_message' => $dateMessage,
                ]),
                $this->logOutput,
                $this->marketplaceSku
            );
            return false;
        }
        // get a Shopware order id in the lengow order table
        $order = $this->lengowOrder->getOrderByMarketplaceSku(
            $this->marketplaceSku,
            $this->lengowMarketplace->getName(),
            $this->deliveryAddressId
        );
        // if order is already exist
        if ($order) {
            $orderUpdated = $this->checkAndUpdateOrder($order);
            if ($orderUpdated && isset($orderUpdated['update'])) {
                return $this->returnResult(self::RESULT_UPDATE, $orderUpdated['lengow_order_id'], $order->getId());
            }
            if (!$this->isReimported) {
                return false;
            }
        }
        if (!$this->importOneOrder) {
            // skip import if the order is anonymized
            if ($this->orderData->anonymized) {
                $this->lengowLog->write(
                    LengowLog::CODE_IMPORT,
                    $this->lengowLog->encodeMessage('log.import.anonymized_order'),
                    $this->logOutput,
                    $this->marketplaceSku
                );
                return false;
            }
            // skip import if the order is older than 3 months
            $dateTimeOrder = new DateTime($this->orderData->marketplace_order_date);
            $interval = $dateTimeOrder->diff(new DateTime());
            $monthsInterval = $interval->m + ($interval->y * 12);
            if ($monthsInterval >= self::MONTH_INTERVAL_TIME) {
                $this->lengowLog->write(
                    LengowLog::CODE_IMPORT,
                    $this->lengowLog->encodeMessage('log.import.old_order'),
                    $this->logOutput,
                    $this->marketplaceSku
                );
                return false;
            }
        }
        // checks if an external id already exists
        $orderId = $this->checkExternalIds($this->orderData->merchant_order_id);
        if ($orderId && !$this->debugMode && !$this->isReimported) {
            $this->lengowLog->write(
                LengowLog::CODE_IMPORT,
                $this->lengowLog->encodeMessage('log.import.external_id_exist', [
                    'order_id' => $orderId
                ]),
                $this->logOutput,
                $this->marketplaceSku
            );
            return false;
        }
        // get a record in the lengow order table
        /** @var LengowOrderEntity $lengowOrder */
        $lengowOrder = $this->lengowOrder->getLengowOrderByMarketplaceSku(
            $this->marketplaceSku,
            $this->lengowMarketplace->getName(),
            $this->deliveryAddressId
        );
        // if order is new, accepted, canceled or refunded -> skip
        if (!in_array($this->orderStateLengow, $this->lengowStates, true)) {
            $orderProcessState = $this->lengowOrder->getOrderProcessState($this->orderStateLengow);
            // check and complete an order not imported if it is canceled or refunded
            if ($lengowOrder && $orderProcessState === LengowOrder::PROCESS_STATE_FINISH) {
                $this->lengowOrderError->finishOrderErrors($lengowOrder->getId());
                $this->lengowOrder->update($lengowOrder->getId(), [
                    'isInError' => false,
                    'orderLengowState' => $this->orderStateLengow,
                    'orderProcessState' => $orderProcessState,
                ]);

            }
            $this->lengowLog->write(
                LengowLog::CODE_IMPORT,
                $this->lengowLog->encodeMessage('log.import.current_order_state_unavailable', [
                    'order_state_marketplace' => $this->orderStateMarketplace,
                    'marketplace_name' => $this->lengowMarketplace->getName(),
                ]),
                $this->logOutput,
                $this->marketplaceSku
            );
            return false;
        }
        // load order types data
        $this->loadOrderTypesData();
        // create a new record in lengow order table if not exist
        if ($lengowOrder === null) {
            // created a record in the lengow order table
            $lengowOrder = $this->createLengowOrder();
            if ($lengowOrder === null) {
                $this->lengowLog->write(
                    LengowLog::CODE_IMPORT,
                    $this->lengowLog->encodeMessage('log.import.lengow_order_not_saved'),
                    $this->logOutput,
                    $this->marketplaceSku
                );
                return false;
            }
            $this->lengowLog->write(
                LengowLog::CODE_IMPORT,
                $this->lengowLog->encodeMessage('log.import.lengow_order_saved'),
                $this->logOutput,
                $this->marketplaceSku
            );
        }
        // checks if the required order data is present
        if (!$this->checkOrderData($lengowOrder)) {
            return $this->returnResult(self::RESULT_ERROR, $lengowOrder->getId());
        }
        // load order amount, processing fees, shipping cost and total items
        $this->loadOrderAmountData();
        // load tracking data
        $this->loadTrackingData();
        // get all customer data (name, contact email and VAT number)
        $customerName = $this->getCustomerName();
        $customerEmail = $this->orderData->billing_address->email !== null
            ? (string)$this->orderData->billing_address->email
            : (string)$this->packageData->delivery->email;
        $customerVatNumber = $this->getVatNumberFromOrderData();
        // update Lengow order with new data
        $this->lengowOrder->update($lengowOrder->getId(), [
            'currency' => $this->orderData->currency->iso_a3,
            'totalPaid' => $this->orderAmount,
            'orderItem' => $this->orderItems,
            'customerName' => $customerName,
            'customerEmail' => $customerEmail,
            'customerVatNumber' => $customerVatNumber,
            'commission' => (float)$this->orderData->commission,
            'carrier' => $this->carrierName,
            'carrierMethod' => $this->carrierMethod,
            'carrierTracking' => $this->trackingNumber,
            'carrierIdRelay' => $this->relayId,
            'sentMarketplace' => $this->shippedByMp,
            'deliveryCountryIso' => $this->packageData->delivery->common_country_iso_a2,
            'orderLengowState' => $this->orderStateLengow,
            'extra' => (array)$this->orderData,
        ]);
        // try to import order
        try {
            // check if the order is shipped by marketplace
            if ($this->shippedByMp) {
                $this->lengowLog->write(
                    LengowLog::CODE_IMPORT,
                    $this->lengowLog->encodeMessage('log.import.order_shipped_by_marketplace', [
                        'marketplace_name' => $this->lengowMarketplace->getName()
                    ]),
                    $this->logOutput,
                    $this->marketplaceSku
                );
                if (!$this->lengowConfiguration->get(LengowConfiguration::LENGOW_IMPORT_SHIPPED_BY_MKTP)) {
                    // update Lengow order with new data
                    $this->lengowOrder->update($lengowOrder->getId(), [
                        'orderProcessState' => LengowOrder::PROCESS_STATE_FINISH,
                        'isInError' => false,
                    ]);
                    return false;
                }
            }
            // get all Shopware products
            $products = $this->getProducts();
            // get lengow address to create all specific Shopware addresses for customer and order
            $this->lengowAddress->init([
                'billing_data' => $this->orderData->billing_address,
                'shipping_data' => $this->packageData->delivery,
                'relay_id' => $this->relayId,
                'vat_number' => $this->getVatNumberFromOrderData(),
            ]);
            // get or create Shopware customerNot specified
            $customer = $this->getCustomer();
            // create a Shopware order
            $order = $this->createOrder($customer, $products);
            // update Lengow order with new data
            $orderProcessState = $this->lengowOrder->getOrderProcessState($this->orderStateLengow);
            // update Lengow order with new data
            $this->lengowOrder->update($lengowOrder->getId(), [
                'orderId' => $order->getId(),
                'orderSku' => $order->getOrderNumber(),
                'orderProcessState' => $orderProcessState,
                'orderLengowState' => $this->orderStateLengow,
                'isInError' => false,
                'isReimported' => false,
            ]);
            $this->lengowLog->write(
                LengowLog::CODE_IMPORT,
                $this->lengowLog->encodeMessage('log.import.lengow_order_updated'),
                $this->logOutput,
                $this->marketplaceSku
            );
            // save order line id in lengow_order_line table
            $this->createLengowOrderLines($order, $products);
            // don't reduce stock for re-import order and order shipped by marketplace
            if ($this->isReimported
                || ($this->shippedByMp
                    && !$this->lengowConfiguration->get(LengowConfiguration::LENGOW_IMPORT_MKTP_DECR_STOCK)
                )
            ) {
                if ($this->isReimported) {
                    $logMessage = $this->lengowLog->encodeMessage('log.import.quantity_back_reimported_order');
                } else {
                    $logMessage = $this->lengowLog->encodeMessage('log.import.quantity_back_shipped_by_marketplace');
                }
                $this->lengowLog->write(LengowLog::CODE_IMPORT, $logMessage, $this->logOutput, $this->marketplaceSku);
            } else {
                $orderIsCompleted = $order->getStateMachineState()
                    && $order->getStateMachineState()->getTechnicalName() === OrderStates::STATE_COMPLETED;
                $this->decrementProductStocks($products, $orderIsCompleted);
            }
            $this->lengowLog->write(
                LengowLog::CODE_IMPORT,
                $this->lengowLog->encodeMessage('log.import.order_successfully_imported', [
                    'order_number' => $order->getOrderNumber(),
                ]),
                $this->logOutput,
                $this->marketplaceSku
            );
        } catch (LengowException $e) {
            $errorMessage = $e->getMessage();
        } catch (Exception $e) {
            $errorMessage = '[Shopware error] "' . $e->getMessage() . '" ' . $e->getFile() . ' | ' . $e->getLine();
        }
        if (isset($errorMessage)) {
            if ($lengowOrder->isInError()) {
                $this->lengowOrderError->create($lengowOrder->getId(), $errorMessage);
            }
            $decodedMessage = $this->lengowLog->decodeMessage($errorMessage);
            $this->lengowLog->write(
                LengowLog::CODE_IMPORT,
                $this->lengowLog->encodeMessage('log.import.order_import_failed', [
                    'decoded_message' => $decodedMessage,
                ]),
                $this->logOutput,
                $this->marketplaceSku
            );
            $this->lengowOrder->update($lengowOrder->getId(), [
                'orderLengowState' => $this->orderStateLengow,
                'isReimported' => false,
            ]);
            return $this->returnResult(self::RESULT_ERROR, $lengowOrder->getId());
        }
        return $this->returnResult(self::RESULT_NEW, $lengowOrder->getId(), isset($order) ? $order->getId() : null);
    }

    /**
     * Check the order and updates data if necessary
     *
     * @param OrderEntity $order Shopware order instance
     *
     * @throws Exception
     *
     * @return array|null
     */
    private function checkAndUpdateOrder(OrderEntity $order): ?array
    {
        $this->lengowLog->write(
            LengowLog::CODE_IMPORT,
            $this->lengowLog->encodeMessage('log.import.order_already_imported', [
                'order_id' => $order->getOrderNumber(),
            ]),
            $this->logOutput,
            $this->marketplaceSku
        );
        // get a record in the lengow order table
        /** @var LengowOrderEntity $lengowOrder */
        $lengowOrder = $this->lengowOrder->getLengowOrderByOrderId($order->getId());
        $result = ['lengow_order_id' => $lengowOrder->getId()];
        // Lengow -> Cancel and reimport order
        if ($lengowOrder->isReimported()) {
            $this->lengowLog->write(
                LengowLog::CODE_IMPORT,
                $this->lengowLog->encodeMessage('log.import.order_ready_to_reimport', [
                    'order_id' => $order->getOrderNumber(),
                ]),
                $this->logOutput,
                $this->marketplaceSku
            );
            $this->isReimported = true;
            return null;
        }
        // try to update Shopware order, lengow order and finish actions if necessary
        $orderUpdated = $this->lengowOrder->updateOrderState(
            $order,
            $lengowOrder,
            $this->orderStateLengow,
            $this->packageData
        );
        if ($orderUpdated) {
            $result['update'] = true;
            $this->lengowLog->write(
                LengowLog::CODE_IMPORT,
                $this->lengowLog->encodeMessage('log.import.order_state_updated', [
                    'state_name' => $orderUpdated,
                ]),
                $this->logOutput,
                $this->marketplaceSku
            );
        }
        unset($lengowOrder);
        return $result;
    }

    /**
     * Checks if an external id already exists
     *
     * @param array|null $externalIds external ids return by API
     *
     * @return int|false
     */
    private function checkExternalIds(array $externalIds = null)
    {
        if (empty($externalIds)) {
            return false;
        }
        foreach ($externalIds as $externalId) {
            if ($this->lengowOrder->getLengowOrderByOrderNumber($externalId, $this->deliveryAddressId)) {
                return $externalId;
            }
        }
        return false;
    }

    /**
     * Checks if order data are present
     *
     * @param LengowOrderEntity $lengowOrder Lengow Order instance
     *
     * @return bool
     */
    protected function checkOrderData(LengowOrderEntity $lengowOrder): bool
    {
        $errorMessages = [];
        if (empty($this->packageData->cart)) {
            $errorMessages[] = $this->lengowLog->encodeMessage('lengow_log.error.no_product');
        }
        if (!isset($this->orderData->currency->iso_a3)) {
            $errorMessages[] = $this->lengowLog->encodeMessage('lengow_log.error.no_currency');
        } else {
            $context = Context::createDefaultContext();
            $criteria = new Criteria();
            $criteria->addFilter(new EqualsFilter('isoCode', $this->orderData->currency->iso_a3));
            /** @var CurrencyCollection $currencyCollection */
            $currencyCollection = $this->currencyRepository->search($criteria, $context)->getEntities();
            if ($currencyCollection->count() === 0) {
                $errorMessages[] = $this->lengowLog->encodeMessage('lengow_log.error.currency_not_available', [
                    'currency_iso' => $this->orderData->currency->iso_a3,
                ]);
            } else {
               $this->currency = $currencyCollection->first();
            }
        }
        if ($this->orderData->total_order === -1) {
            $errorMessages[] = $this->lengowLog->encodeMessage('lengow_log.error.no_change_rate');
        }
        if ($this->orderData->billing_address === null) {
            $errorMessages[] = $this->lengowLog->encodeMessage('lengow_log.error.no_billing_address');
        } elseif ($this->orderData->billing_address->common_country_iso_a2 === null) {
            $errorMessages[] = $this->lengowLog->encodeMessage('lengow_log.error.no_country_for_billing_address');
        }
        if ($this->packageData->delivery->common_country_iso_a2 === null) {
            $errorMessages[] = $this->lengowLog->encodeMessage('lengow_log.error.no_country_for_delivery_address');
        }
        if (!empty($errorMessages)) {
            foreach ($errorMessages as $errorMessage) {
                $this->lengowOrderError->create($lengowOrder->getId(), $errorMessage);
                $decodedMessage = $this->lengowLog->decodeMessage($errorMessage, LengowTranslation::DEFAULT_ISO_CODE);
                $this->lengowLog->write(
                    LengowLog::CODE_IMPORT,
                    $this->lengowLog->encodeMessage('log.import.order_import_failed', [
                        'decoded_message' => $decodedMessage,
                    ]),
                    $this->logOutput,
                    $this->marketplaceSku
                );
            }
            return false;
        }
        return true;
    }

    /**
     * Return an array of result for each order
     *
     * @param string $type Type of result (new, update, error)
     * @param string $lengowOrderId Lengow order id
     * @param string|null $orderId Shopware order id
     *
     * @return array
     */
    private function returnResult(string $type, string $lengowOrderId, string $orderId = null): array
    {
        return [
            'order_id' => $orderId,
            'lengow_order_id' => $lengowOrderId,
            'marketplace_sku' => $this->marketplaceSku,
            'marketplace_name' => $this->lengowMarketplace->getName(),
            'lengow_state' => $this->orderStateLengow,
            'order_new' => $type === self::RESULT_NEW,
            'order_update' => $type === self::RESULT_UPDATE,
            'order_error' => $type === self::RESULT_ERROR,
        ];
    }

    /**
     * Create a lengow order in lengow orders table
     *
     * @return LengowOrderEntity|null
     * @throws Exception
     */
    private function createLengowOrder(): ?LengowOrderEntity
    {
        // create lengow order
        $this->lengowOrder->create([
            'salesChannelId' => $this->salesChannel->getId(),
            'marketplaceSku' => $this->marketplaceSku,
            'marketplaceName' => $this->lengowMarketplace->getName(),
            'marketplaceLabel' => $this->lengowMarketplace->getLabel(),
            'deliveryAddressId' => $this->deliveryAddressId,
            'orderLengowState' => $this->orderStateLengow,
            'orderTypes' => $this->orderTypes,
            'orderDate' => $this->getOrderDate(),
            'message' => $this->getMessage(),
            'extra' => (array)$this->orderData,
        ]);
        // get lengow order
        return $this->lengowOrder->getLengowOrderByMarketplaceSku(
            $this->marketplaceSku,
            $this->lengowMarketplace->getName(),
            $this->deliveryAddressId
        );
    }

    /**
     * Get order types data and update Lengow order record
     */
    private function loadOrderTypesData(): void
    {
        $orderTypes = [];
        $this->shippedByMp = false;
        if (!empty($this->orderData->order_types)) {
            foreach ($this->orderData->order_types as $orderType) {
                $orderTypes[$orderType->type] = $orderType->label;
                if ($orderType->type === LengowOrder::TYPE_DELIVERED_BY_MARKETPLACE) {
                    $this->shippedByMp = true;
                }
            }
        }
        $this->orderTypes = $orderTypes;
    }

    /**
     * Load all order amount data (processing fee, shipping cost, order items and order amount)
     */
    private function loadOrderAmountData(): void
    {
        $this->processingFee = (float)$this->orderData->processing_fee;
        $this->shippingCost = (float)$this->orderData->shipping;
        // rewrite processing fees and shipping cost
        if (!$this->firstPackage) {
            $this->processingFee = 0;
            $this->shippingCost = 0;
            $this->lengowLog->write(
                LengowLog::CODE_IMPORT,
                $this->lengowLog->encodeMessage('log.import.rewrite_processing_fee'),
                $this->logOutput,
                $this->marketplaceSku
            );
            $this->lengowLog->write(
                LengowLog::CODE_IMPORT,
                $this->lengowLog->encodeMessage('log.import.rewrite_shipping_cost'),
                $this->logOutput,
                $this->marketplaceSku
            );
        }
        // get total amount and the number of items
        $nbItems = 0;
        $totalAmount = 0;
        foreach ($this->packageData->cart as $product) {
            // check whether the product is canceled for amount
            if ($product->marketplace_status !== null) {
                $stateProduct = $this->lengowMarketplace->getStateLengow((string)$product->marketplace_status);
                if ($stateProduct === LengowOrder::STATE_CANCELED || $stateProduct === LengowOrder::STATE_REFUSED) {
                    continue;
                }
            }
            $nbItems += (int)$product->quantity;
            $totalAmount += (float)$product->amount;
        }
        $this->orderItems = $nbItems;
        $this->orderAmount = $totalAmount + $this->processingFee + $this->shippingCost;
    }

    /**
     * Load all tracking data (carrier name, carrier method, tracking number and relay id)
     */
    private function loadTrackingData(): void
    {
        $tracks = $this->packageData->delivery->trackings;
        if (!empty($tracks)) {
            $tracking = $tracks[0];
            $this->carrierName = $tracking->carrier;
            $this->carrierMethod = $tracking->method;
            $this->trackingNumber = $tracking->number;
            $this->relayId = $tracking->relay->id;
        }
    }

    /**
     * Get customer name
     *
     * @return string
     */
    private function getCustomerName(): string
    {
        $firstName = ucfirst(strtolower((string)$this->orderData->billing_address->first_name));
        $lastName = ucfirst(strtolower((string)$this->orderData->billing_address->last_name));
        if (empty($firstName) && empty($lastName)) {
            return ucwords(strtolower((string)$this->orderData->billing_address->full_name));
        }
        return $firstName . ' ' . $lastName;
    }

    /**
     * Get order date in correct format for database
     *
     * @throws Exception
     *
     * @return string
     */
    private function getOrderDate(): string
    {
        $orderDate = (string)($this->orderData->marketplace_order_date ?? $this->orderData->imported_at);
        return $this->lengowConfiguration->gmtDate(strtotime($orderDate), Defaults::STORAGE_DATE_TIME_FORMAT);
    }

    /**
     * Get order messages
     *
     * @return string
     */
    private function getMessage(): string
    {
        return is_array($this->orderData->comments)
            ? implode(',', $this->orderData->comments)
            : (string)$this->orderData->comments;
    }

    /**
     * Get vat_number from lengow order data
     *
     * @return string
     */
    private function getVatNumberFromOrderData(): string
    {
        return (string)($this->orderData->billing_address->vat_number ?? $this->packageData->delivery->vat_number);
    }

    /**
     * Get products from the API
     *
     * @throws LengowException
     *
     * @return array
     */
    private function getProducts(): array
    {
        $products = [];
        foreach ($this->packageData->cart as $apiProduct) {
            $productData = $this->lengowProduct->extractProductDataFromAPI($apiProduct);
            $apiProductId = $productData['merchant_product_id']->id ?? $productData['marketplace_product_id'];
            if ($productData['marketplace_status'] !== null) {
                $productState = $this->lengowMarketplace->getStateLengow($productData['marketplace_status']);
                if ($productState === LengowOrder::STATE_CANCELED || $productState === LengowOrder::STATE_REFUSED) {
                    $this->lengowLog->write(
                        LengowLog::CODE_IMPORT,
                        $this->lengowLog->encodeMessage('log.import.product_state_canceled', [
                            'product_id' => $apiProductId,
                            'product_state' => $productState,
                        ]),
                        $this->logOutput,
                        $this->marketplaceSku
                    );
                    continue;
                }
            }
            $product = null;
            $productIds = [
                'merchant_product_id' => (string)$productData['merchant_product_id']->id,
                'marketplace_product_id' => (string)$productData['marketplace_product_id'],
            ];
            foreach ($productIds as $attributeName => $attributeValue) {
                $product = $this->lengowProduct->searchProduct(
                    $attributeName,
                    $attributeValue,
                    $this->logOutput,
                    $this->marketplaceSku
                );
                if ($product) {
                    break;
                }
            }
            if ($product === null) {
                throw new LengowException(
                    $this->lengowLog->encodeMessage('lengow_log.exception.product_not_be_found', [
                        'product_id' => $apiProductId,
                    ])
                );
            }
            // if found, id does not concerns a variation but a parent
            if ($product && (int)$product->getChildCount() !== 0) {
                throw new LengowException(
                    $this->lengowLog->encodeMessage('lengow_log.exception.product_is_a_parent', [
                        'product_number' => $product->getProductNumber(),
                        'product_id' => $product->getId(),
                    ])
                );
            }
            // product is not available for order because there is not enough stock
            if ($product && !$product->getAvailable()) {
                throw new LengowException(
                    $this->lengowLog->encodeMessage('lengow_log.exception.no_quantity_for_product', [
                        'product_number' => $product->getProductNumber(),
                        'product_id' => $product->getId(),
                    ])
                );
            }
            $productId = $product->getId();
            if (array_key_exists($productId, $products)) {
                $products[$productId]['quantity'] += (int)$productData['quantity'];
                $products[$productId]['amount'] += (float)$productData['amount'];
                $products[$productId]['order_line_ids'][] = $productData['marketplace_order_line_id'];
            } else {
                $products[$productId] = [
                    'shopware_product' => $product,
                    'quantity' => (int)$productData['quantity'],
                    'amount' => (float)$productData['amount'],
                    'price_unit' => $productData['price_unit'],
                    'order_line_ids' => [$productData['marketplace_order_line_id']],
                ];
            }
        }
        if (empty($products)) {
            throw new LengowException($this->lengowLog->encodeMessage('lengow_log.exception.no_product_to_cart'));
        }
        return $products;
    }

    /**
     * Get or create Shopware customer
     *
     * @throws LengowException
     *
     * @return CustomerEntity
     */
    private function getCustomer(): CustomerEntity
    {
        $customerEmail = $this->marketplaceSku . '-' . $this->lengowMarketplace->getName() . '@lengow.com';
        $this->lengowLog->write(
            LengowLog::CODE_IMPORT,
            $this->lengowLog->encodeMessage('log.import.generate_unique_email', [
                'email' => $customerEmail
            ]),
            $this->logOutput,
            $this->marketplaceSku
        );
        $customer = $this->lengowCustomer->getCustomerByEmail($this->salesChannel, $customerEmail);
        if ($customer === null) {
            $customer = $this->lengowCustomer->createCustomer($this->salesChannel, $customerEmail);
        }
        if ($customer === null) {
            throw new LengowException(
                $this->lengowLog->encodeMessage('lengow_log.exception.shopware_customer_not_saved')
            );
        }
        return $customer;
    }

    /**
     * Create Shopware order
     *
     * @param CustomerEntity $customer Shopware customer instance
     * @param array $products Shopware products
     *
     * @throws Exception|LengowException
     *
     * @return OrderEntity
     */
    private function createOrder(CustomerEntity $customer, array $products): OrderEntity
    {
        $token = Uuid::randomHex();
        $shippingMethodId = $this->lengowConfiguration->get(
            LengowConfiguration::LENGOW_IMPORT_DEFAULT_SHIPPING_METHOD,
            $this->salesChannel->getId()
        );
        // create a specific context with all order data
        $salesChannelContext = $this->salesChannelContextFactory->create($token, $this->salesChannel->getId(), [
            SalesChannelContextService::CUSTOMER_ID => $customer->getId(),
            SalesChannelContextService::CURRENCY_ID => $this->currency->getId(),
            SalesChannelContextService::SHIPPING_METHOD_ID => $shippingMethodId,
        ]);
        // create a generic cart
        $cart = $this->createCart($token, $products, $salesChannelContext);
        // get and modify order data for Shopware order creation
        $orderData = $this->getOrderData($cart, $products, $salesChannelContext);
        // delete cart after order creation
        $this->cartService->deleteCart($salesChannelContext);
        // create Shopware order
        $order = $this->lengowOrder->createOrder($orderData);
        if (!$order) {
            throw new LengowException(
                $this->lengowLog->encodeMessage('lengow_log.exception.shopware_order_not_saved')
            );
        }
        $order = $this->lengowOrder->getOrderById($orderData['id']);
        return $order;
    }

    /**
     * Create a generic cart
     *
     * @param string $token cart token
     * @param array $products Shopware products
     * @param SalesChannelContext $salesChannelContext Shopware sales channel context
     *
     * @throws LengowException
     *
     * @return Cart
     */
    private function createCart(string $token, array $products, SalesChannelContext $salesChannelContext): Cart
    {
        // create new empty cart
        $cart = $this->cartService->createNew($token);
        // add all products to cart
        foreach ($products as $productId => $productData) {
            $lineItem = new LineItem(
                $productId,
                LineItem::PRODUCT_LINE_ITEM_TYPE,
                $productId,
                $productData['quantity']
            );
            $lineItem->setStackable(true);
            $this->cartService->add($cart, $lineItem, $salesChannelContext);
        }
        if ($cart->getLineItems()->count() === 0) {
            throw new LengowException($this->lengowLog->encodeMessage('lengow_log.exception.no_product_to_cart'));
        }
        // recalculate the cart with new products and sales channel context
        return $this->cartService->recalculate($cart, $salesChannelContext);
    }

    /**
     * Get and modify order data for Shopware order creation
     *
     * @param Cart $cart Shopware cart instance
     * @param array $products Shopware products
     * @param SalesChannelContext $salesChannelContext Shopware sales channel context
     *
     * @throws Exception
     *
     * @return array
     */
    private function getOrderData(Cart $cart, array $products, SalesChannelContext $salesChannelContext): array
    {
        // convert cart to order
        $orderData = $this->orderConverter->convertToOrder($cart, $salesChannelContext, new OrderConversionContext());
        // change the price of the product with the price from the marketplace
        $orderData = $this->changeProductPrice($orderData, $products, $salesChannelContext);
        // change the shipping costs of the order by those of the marketplace
        $orderData = $this->changeShippingCosts($orderData, $salesChannelContext);
        // change order transaction state and order transaction amount
        $orderData = $this->changeTransaction($orderData, $salesChannelContext);
        // change order amount and order state
        $orderData = $this->changeOrderAmountAndState($orderData);
        // change order date and customer comment
        $orderData['orderDateTime'] = $this->getOrderDate();
        $orderData['customerComment'] = $this->getMessage();
        return $orderData;
    }

    /**
     * Change the price of the product with the price from the marketplace
     *
     * @param array $orderData Shopware order data
     * @param array $products Shopware products
     * @param SalesChannelContext $salesChannelContext Shopware sales channel context
     *
     * @return array
     */
    private function changeProductPrice(
        array $orderData,
        array $products,
        SalesChannelContext $salesChannelContext
    ): array
    {
        foreach ($orderData['lineItems'] as $key => $lineItem) {
            $productData = $products[$lineItem['productId']];
            $calculatedPrice = $lineItem['price'];
            $definition = new QuantityPriceDefinition(
                $productData['price_unit'],
                $calculatedPrice->getTaxRules(),
                $salesChannelContext->getCurrency()->getDecimalPrecision(),
                $productData['quantity'],
                true
            );
            $calculated = $this->calculator->calculate($definition, $salesChannelContext);
            // set new price into line item
            $lineItem['price'] = $calculated;
            $lineItem['priceDefinition'] = $definition;
            $orderData['lineItems'][$key] = $lineItem;
        }
        return $orderData;
    }

    /**
     * Change the shipping costs of the order by those of the marketplace
     *
     * @param array $orderData Shopware order data
     * @param SalesChannelContext $salesChannelContext Shopware sales channel context
     *
     * @return array
     */
    private function changeShippingCosts(array $orderData, SalesChannelContext $salesChannelContext): array
    {
        $shippingCosts = $this->shippingCost + $this->processingFee;
        $orderDeliveryState = $this->lengowOrder->getStateMachineStateByOrderState(
            OrderDeliveryStates::STATE_MACHINE,
            $this->orderStateLengow,
            $this->shippedByMp
        );
        $calculatedPrice = $orderData['shippingCosts'];
        $definition = new QuantityPriceDefinition(
            $shippingCosts,
            $calculatedPrice->getTaxRules(),
            $salesChannelContext->getCurrency()->getDecimalPrecision(),
            1,
            true
        );
        $orderData['shippingCosts'] = $this->calculator->calculate($definition, $salesChannelContext);
        $orderData['deliveries'][0]['shippingCosts'] = $orderData['shippingCosts'];
        if ($orderDeliveryState) {
            $orderData['deliveries'][0]['stateId'] = $orderDeliveryState->getId();
        }
        if (!empty($this->trackingNumber)) {
            $orderData['deliveries'][0]['trackingCodes'] = [$this->trackingNumber];
        }
        return $orderData;
    }

    /**
     * Change order transaction state and order transaction amount
     *
     * @param array $orderData Shopware order data
     * @param SalesChannelContext $salesChannelContext Shopware sales channel context
     *
     * @return array
     */
    private function changeTransaction(array $orderData, SalesChannelContext $salesChannelContext): array
    {
        $cartPrice = $orderData['price'];
        // get order amount calculated price
        $definition = new QuantityPriceDefinition(
            $this->orderAmount,
            $cartPrice->getTaxRules(),
            $salesChannelContext->getCurrency()->getDecimalPrecision(),
            1,
            true
        );
        $orderAmountCalculatedPrice = $this->calculator->calculate($definition, $salesChannelContext);
        // modify payment state to paid and change payment amount
        $orderTransactionState = $this->lengowOrder->getStateMachineStateByOrderState(
            OrderTransactionStates::STATE_MACHINE,
            $this->orderStateLengow,
            $this->shippedByMp
        );
        $orderData['transactions'][0]['amount'] = $orderAmountCalculatedPrice;
        if ($orderTransactionState) {
            $orderData['transactions'][0]['stateId'] = $orderTransactionState->getId();
        }
        return $orderData;
    }

    /**
     * Change order amount and order state
     *
     * @param array $orderData Shopware order data
     *
     * @return array
     */
    private function changeOrderAmountAndState(array $orderData): array
    {
        $cartPrice = $orderData['price'];
        $orderAmountCalculatedPrice = $orderData['transactions'][0]['amount'];
        // modify order amount
        $calculatedTax = $orderAmountCalculatedPrice->getCalculatedTaxes()->first();
        $netPrice = $calculatedTax !== null ? $this->orderAmount - $calculatedTax->getTax() : $this->orderAmount;
        $orderData['price'] = new CartPrice(
            $netPrice,
            $this->orderAmount,
            $this->orderAmount,
            $orderAmountCalculatedPrice->getCalculatedTaxes(),
            $cartPrice->getTaxRules(),
            $cartPrice->getTaxStatus()
        );
        // get current order state and change order state id
        $orderState = $this->lengowOrder->getStateMachineStateByOrderState(
            OrderStates::STATE_MACHINE,
            $this->orderStateLengow,
            $this->shippedByMp
        );
        if ($orderState) {
            $orderData['stateId'] = $orderState->getId();
        }
        return $orderData;
    }

    /**
     * Decrements the stock of all products on the order
     *
     * @param array $products Shopware products
     * @param bool $orderIsCompleted check if Shopware order is completed to decrement stock
     */
    private function decrementProductStocks(array $products, bool $orderIsCompleted = false): void
    {
        foreach ($products as $productData) {
            /** @var ProductEntity $product */
            $product = $productData['shopware_product'];
            // decreases article detail stock
            $initialStock = $product->getAvailableStock();
            $stock = $orderIsCompleted ? $product->getStock() - $productData['quantity'] : $product->getStock();
            $availableStock = $initialStock - $productData['quantity'];
            try {
                // use SQL query because update via the product repository don't work
                // and Shopware has not any service allowing to decrement properly a product
                $query = new RetryableQuery(
                    $this->connection->prepare('
                        UPDATE product
                        SET stock = :stock, available_stock = :available_stock, version_id = :version
                        WHERE id = :id
                    ')
                );
                $query->execute([
                    'stock' => (int)$stock,
                    'available_stock' => (int)$availableStock,
                    'id' => Uuid::fromHexToBytes($product->getId()),
                    'version' => Uuid::fromHexToBytes(Defaults::LIVE_VERSION),
                ]);
                $this->lengowLog->write(
                    LengowLog::CODE_IMPORT,
                    $this->lengowLog->encodeMessage('log.import.stock_decreased', [
                        'product_number' => $product->getProductNumber(),
                        'initial_stock' => $initialStock,
                        'new_stock' => $availableStock,
                    ]),
                    $this->logOutput,
                    $this->marketplaceSku
                );
            } catch (Exception $e) {
                $errorMessage = '[Shopware error] "' . $e->getMessage() . '" ' . $e->getFile() . ' | ' . $e->getLine();
                $this->lengowLog->write(
                    LengowLog::CODE_ORM,
                    $this->lengowLog->encodeMessage('log.orm.record_insert_failed', [
                        'decoded_message' => str_replace(PHP_EOL, '', $errorMessage),
                    ])
                );
            }
        }
    }

    /**
     * Create lines in lengow order line table
     *
     * @param OrderEntity $order Shopware order instance
     * @param array $products Shopware products
     */
    private function createLengowOrderLines(OrderEntity $order, array $products): void
    {
        $orderLineSaved = '';
        foreach ($products as $productId => $productData) {
            // create Lengow order line entity
            foreach ($productData['order_line_ids'] as $orderLineId) {
                $result = $this->lengowOrderLine->create([
                    'orderId' => $order->getId(),
                    'orderLineId' => $orderLineId,
                    'productId' => $productId,
                ]);
                if ($result) {
                    $orderLineSaved .= empty($orderLineSaved) ? $orderLineId : ' / ' . $orderLineId;
                }
            }
        }
        $this->lengowLog->write(
            LengowLog::CODE_IMPORT,
            $this->lengowLog->encodeMessage('log.import.lengow_order_line_saved', [
                'order_line_saved' => $orderLineSaved,
            ]),
            $this->logOutput,
            $this->marketplaceSku
        );
    }
}
