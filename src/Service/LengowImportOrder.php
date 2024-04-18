<?php declare(strict_types=1);

namespace Lengow\Connector\Service;

use DateTime;
use Exception;
use Doctrine\DBAL\Connection;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\Order\OrderConversionContext;
use Shopware\Core\Checkout\Cart\Order\OrderConverter;
use Shopware\Core\Checkout\Cart\Price\QuantityPriceCalculator;
use Shopware\Core\Checkout\Cart\Price\Struct\CartPrice;
use Shopware\Core\Checkout\Cart\Price\Struct\QuantityPriceDefinition;
use Shopware\Core\Checkout\Cart\SalesChannel\CartService;
use Shopware\Core\Checkout\Cart\Tax\Struct\CalculatedTaxCollection;
use Shopware\Core\Checkout\Cart\Tax\Struct\TaxRuleCollection;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderDelivery\OrderDeliveryStates;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionStates;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Checkout\Order\OrderStates;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Doctrine\RetryableQuery;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
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
use Lengow\Connector\Entity\Lengow\Order\OrderDefinition as LengowOrderDefinition;
use Lengow\Connector\Entity\Lengow\Order\OrderEntity as LengowOrderEntity;
use Lengow\Connector\Entity\Lengow\OrderError\OrderErrorEntity as LengowOrderErrorEntity;
use Lengow\Connector\Entity\Lengow\OrderLine\OrderLineDefinition as LengowOrderLineDefinition;
use Lengow\Connector\Exception\LengowException;
use Lengow\Connector\Factory\LengowMarketplaceFactory;

/**
 * Class LengowImportOrder
 * @package Lengow\Connector\Service
 */
class LengowImportOrder
{
    /* Import Order construct params */
    public const PARAM_SALES_CHANNEL = 'sales_channel';
    public const PARAM_FORCE_SYNC = 'force_sync';
    public const PARAM_DEBUG_MODE = 'debug_mode';
    public const PARAM_LOG_OUTPUT = 'log_output';
    public const PARAM_MARKETPLACE_SKU = 'marketplace_sku';
    public const PARAM_DELIVERY_ADDRESS_ID = 'delivery_address_id';
    public const PARAM_ORDER_DATA = 'order_data';
    public const PARAM_PACKAGE_DATA = 'package_data';
    public const PARAM_FIRST_PACKAGE = 'first_package';
    public const PARAM_IMPORT_ONE_ORDER = 'import_one_order';

    /* Import Order data */
    public const MERCHANT_ORDER_ID = 'merchant_order_id';
    public const MERCHANT_ORDER_REFERENCE = 'merchant_order_reference';
    public const LENGOW_ORDER_ID = 'lengow_order_id';
    public  const MARKETPLACE_SKU = 'marketplace_sku';
    public const MARKETPLACE_NAME = 'marketplace_name';
    public const DELIVERY_ADDRESS_ID = 'delivery_address_id';
    public const SHOP_ID = 'shop_id';
    public const CURRENT_ORDER_STATUS = 'current_order_status';
    public const PREVIOUS_ORDER_STATUS = 'previous_order_status';
    public const ERRORS = 'errors';
    public const RESULT_TYPE = 'result_type';

    /* Synchronisation results */
    public const RESULT_CREATED = 'created';
    public const RESULT_UPDATED = 'updated';
    public const RESULT_FAILED = 'failed';
    public const RESULT_IGNORED = 'ignored';

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
     * @var EntityRepository Shopware currency repository
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
        LengowOrder::STATE_PARTIALLY_REFUNDED,
    ];

    /**
     * @var boolean force import order even if there are errors
     */
    private $forceSync;

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
     * @var string id of the record Lengow order table
     */
    private $lengowOrderId;

    /**
     * @var string id of the record Shopware order table
     */
    private $orderId;

    /**
     * @var string Magento order reference
     */
    private $orderReference;

    /**
     * @var string marketplace order state
     */
    private $orderStateMarketplace;

    /**
     * @var string Lengow order state
     */
    private $orderStateLengow;

    /**
     * @var string Previous Lengow order state
     */
    private $previousOrderStateLengow;

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
     * @var string Customer VAT number
     */
    private $customerVatNumber;

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
     * @var array order errors
     */
    private $errors = [];

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
     * @param EntityRepository $currencyRepository Shopware currency repository
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
        EntityRepository $currencyRepository,
        $salesChannelContextFactory,
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
     * init an import order
     *
     * @param array $params optional options for load an import order
     *
     * object sales_channel       Sales channel instance
     * bool   force_sync          Force import order even if there are errors
     * bool   debug_mode          Debug mode
     * bool   log_output          Display log messages
     * string marketplace_sku     Order marketplace sku
     * int    delivery_address_id Order delivery address id
     * mixed  order_data          Order data
     * mixed  package_data        Package data
     * bool   first_package       It is the first package
     * bool   import_one_order    Synchronisation process for only one order
     */
    public function init(array $params): void
    {
        $this->errors = [];
        $this->salesChannel = $params[self::PARAM_SALES_CHANNEL];
        $this->forceSync = $params[self::PARAM_FORCE_SYNC];
        $this->debugMode = $params[self::PARAM_DEBUG_MODE];
        $this->logOutput = $params[self::PARAM_LOG_OUTPUT];
        $this->marketplaceSku = $params[self::PARAM_MARKETPLACE_SKU];
        $this->deliveryAddressId = $params[self::PARAM_DELIVERY_ADDRESS_ID];
        $this->orderData = $params[self::PARAM_ORDER_DATA];
        $this->packageData = $params[self::PARAM_PACKAGE_DATA];
        $this->firstPackage = $params[self::PARAM_FIRST_PACKAGE];
        $this->importOneOrder = $params[self::PARAM_IMPORT_ONE_ORDER];
    }

    /**
     * Create or update order
     *
     * @return array
     */
    public function exec(): array
    {
        // load marketplace singleton and marketplace data
        if (!$this->loadMarketplaceData()) {
            return $this->returnResult(self::RESULT_IGNORED);
        }
        // checks if a record already exists in the lengow order table
        /** @var LengowOrderEntity $lengowOrder */
        $lengowOrder = $this->lengowOrder->getLengowOrderByMarketplaceSku(
            $this->marketplaceSku,
            $this->lengowMarketplace->getName()
        );
        $this->lengowOrderId = $lengowOrder ? $lengowOrder->getId() : null;
        // checks if an order already has an error in progress
        if ($this->lengowOrderId && $this->orderErrorAlreadyExist()) {
            return $this->returnResult(self::RESULT_IGNORED);
        }
        // get a Shopware order id in the lengow order table
        $order = $this->lengowOrder->getOrderByMarketplaceSku(
            $this->marketplaceSku,
            $this->lengowMarketplace->getName()
        );
        // if order is already exist
        if ($order) {
            $orderUpdated = $this->checkAndUpdateOrder($order);
            if ($orderUpdated) {
                return $this->returnResult(self::RESULT_UPDATED);
            }
            if (!$this->isReimported) {
                return $this->returnResult(self::RESULT_IGNORED);
            }
        }
        // checks if the order is not anonymized or too old
        if (!$this->lengowOrderId && !$this->canCreateOrder()) {
            return $this->returnResult(self::RESULT_IGNORED);
        }
        // checks if an external id already exists
        if (!$this->lengowOrderId && $this->externalIdAlreadyExist()) {
            return $this->returnResult(self::RESULT_IGNORED);
        }
        // checks if the order status is valid for order creation
        if (!$this->orderStatusIsValid()) {
            return $this->returnResult(self::RESULT_IGNORED);
        }
        // load data and create a new record in lengow order table if not exist
        if (!$this->createLengowOrder()) {
            return $this->returnResult(self::RESULT_IGNORED);
        }
        // checks if the required order data is present and update Lengow order record
        if (!$this->checkAndUpdateLengowOrderData()) {
            return $this->returnResult(self::RESULT_FAILED);
        }
        // checks if an order sent by the marketplace must be created or not
        if (!$this->canCreateOrderShippedByMarketplace()) {
            return $this->returnResult(self::RESULT_IGNORED);
        }
        // create Shopware order
        if (!$this->createOrder()) {
            return $this->returnResult(self::RESULT_FAILED);
        }
        return $this->returnResult(self::RESULT_CREATED);
    }

    /**
     * Load marketplace singleton and marketplace data
     *
     * @return boolean
     */
    private function loadMarketplaceData(): bool
    {
        try {
            $this->lengowMarketplace = $this->lengowMarketplaceFactory->create($this->orderData->marketplace);
            $this->orderStateMarketplace = $this->orderData->marketplace_status;
            $this->orderStateLengow = $this->lengowMarketplace->getStateLengow($this->orderStateMarketplace);
            $this->previousOrderStateLengow = $this->orderStateLengow;
            return true;
        } catch (LengowException $e) {
            $this->errors[] = $this->lengowLog->decodeMessage($e->getMessage(), LengowTranslation::DEFAULT_ISO_CODE);
            $this->lengowLog->write(LengowLog::CODE_IMPORT, $e->getMessage(), $this->logOutput, $this->marketplaceSku);
        }
        return false;
    }

    /**
     * Return an array of result for each order
     *
     * @param string $resultType Type of result (created, updated, failed or ignored)
     *
     * @return array
     */
    private function returnResult(string $resultType): array
    {
        return [
            self::MERCHANT_ORDER_ID => $this->orderId,
            self::MERCHANT_ORDER_REFERENCE => $this->orderReference,
            self::LENGOW_ORDER_ID => $this->lengowOrderId,
            self::MARKETPLACE_SKU => $this->marketplaceSku,
            self::MARKETPLACE_NAME => $this->lengowMarketplace ? $this->lengowMarketplace->getName() : null,
            self::DELIVERY_ADDRESS_ID => $this->deliveryAddressId,
            self::SHOP_ID => $this->salesChannel->getId(),
            self::CURRENT_ORDER_STATUS => $this->orderStateLengow,
            self::PREVIOUS_ORDER_STATUS => $this->previousOrderStateLengow,
            self::ERRORS => $this->errors,
            self::RESULT_TYPE => $resultType,
        ];
    }

    /**
     * Checks if an order already has an error in progress
     *
     * @return bool
     */
    private function orderErrorAlreadyExist(): bool
    {
        // if order error exist and not finished -> stop import order
        $orderErrors = $this->lengowOrderError->orderIsInError($this->marketplaceSku);
        if ($orderErrors === null) {
            return false;
        }
        // force order synchronization by removing pending errors
        if ($this->forceSync) {
            $this->lengowOrderError->finishOrderErrors($this->lengowOrderId);
            return false;
        }
        /** @var LengowOrderErrorEntity $orderError */
        $orderError = $orderErrors->first();
        $dateMessage = $orderError->getCreatedAt()
            ? $this->lengowConfiguration->date($orderError->getCreatedAt()->getTimestamp())
            : $this->lengowConfiguration->date();
        $decodedMessage = $this->lengowLog->decodeMessage(
            $orderError->getMessage(),
            LengowTranslation::DEFAULT_ISO_CODE
        );
        $message =  $this->lengowLog->encodeMessage('log.import.error_already_created', [
            'decoded_message' => $decodedMessage,
            'date_message' => $dateMessage,
        ]);
        $this->errors[] = $this->lengowLog->decodeMessage($message, LengowTranslation::DEFAULT_ISO_CODE);
        $this->lengowLog->write(LengowLog::CODE_IMPORT, $message, $this->logOutput, $this->marketplaceSku);
        return true;
    }

    /**
     * Check the order and updates data if necessary
     *
     * @param OrderEntity $order Shopware order instance
     *
     * @return bool
     * @throws Exception
     */
    private function checkAndUpdateOrder(OrderEntity $order): bool
    {
        $orderUpdated = false;
        $this->lengowLog->write(
            LengowLog::CODE_IMPORT,
            $this->lengowLog->encodeMessage('log.import.order_already_imported', [
                'order_id' => $order->getOrderNumber(),
            ]),
            $this->logOutput,
            $this->marketplaceSku
        );

        // Get a record in the Lengow order table
        /** @var LengowOrderEntity $lengowOrder */
        $lengowOrder = $this->lengowOrder->getLengowOrderByOrderId($order->getId());

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
            unset($lengowOrder);
            return $orderUpdated;
        }

        // Load data for return
        $this->orderId = $order->getId();
        $this->orderReference = $order->getOrderNumber();
        $this->previousOrderStateLengow = $lengowOrder->getOrderLengowState();

        // Load VAT number from lengow order data
        $this->loadVatNumberFromOrderData();

        // Check and update VAT number data
        if ($lengowOrder->getCustomerVatNumber() !== $this->customerVatNumber) {
            $this->checkAndUpdateLengowOrderData();
            $orderUpdated = true;
            $this->lengowLog->write(
                LengowLog::CODE_IMPORT,
                $this->lengowLog->encodeMessage('log.import.lengow_order_updated'),
                $this->logOutput,
                $this->marketplaceSku
            );
        }

        // Try to update Shopware order, Lengow order, and finish actions if necessary
        if (!$orderUpdated) {
            $orderUpdated = $this->lengowOrder->updateOrderState(
                $order,
                $lengowOrder,
                $this->orderStateLengow,
                $this->packageData
            );
        }

        unset($lengowOrder);
        return (bool)$orderUpdated;
    }

    /**
     * Checks if the order is not anonymized or too old
     *
     * @return bool
     */
    private function canCreateOrder(): bool
    {
        if ($this->importOneOrder) {
            return true;
        }
        // skip import if the order is anonymized
        if ($this->orderData->anonymized) {
            $message = $this->lengowLog->encodeMessage('log.import.anonymized_order');
            $this->errors[] = $this->lengowLog->decodeMessage($message, LengowTranslation::DEFAULT_ISO_CODE);
            $this->lengowLog->write(LengowLog::CODE_IMPORT, $message, $this->logOutput, $this->marketplaceSku);
            return false;
        }
        // skip import if the order is older than 3 months
        try {
            $dateTimeOrder = new DateTime($this->orderData->marketplace_order_date);
            $interval = $dateTimeOrder->diff(new DateTime());
            $monthsInterval = $interval->m + ($interval->y * 12);
            if ($monthsInterval >= self::MONTH_INTERVAL_TIME) {
                $message = $this->lengowLog->encodeMessage('log.import.old_order');
                $this->errors[] = $this->lengowLog->decodeMessage($message, LengowTranslation::DEFAULT_ISO_CODE);
                $this->lengowLog->write(LengowLog::CODE_IMPORT, $message, $this->logOutput, $this->marketplaceSku);
                return false;
            }
        } catch (Exception $e) {
            $this->lengowLog->write(
                LengowLog::CODE_IMPORT,
                $this->lengowLog->encodeMessage('log.import.unable_verify_date'),
                $this->logOutput,
                $this->marketplaceSku
            );
        }
        return true;
    }

    /**
     * Checks if an external id already exists
     *
     * @return bool
     */
    private function externalIdAlreadyExist(): bool
    {
        if (empty($this->orderData->merchant_order_id) || $this->debugMode || $this->isReimported) {
            return false;
        }
        foreach ($this->orderData->merchant_order_id as $externalId) {
            if ($this->lengowOrder->getLengowOrderByOrderNumber($externalId, $this->marketplaceSku)) {
                $message = $this->lengowLog->encodeMessage('log.import.external_id_exist', [
                    'order_id' => $externalId,
                ]);
                $this->errors[] = $this->lengowLog->decodeMessage($message, LengowTranslation::DEFAULT_ISO_CODE);
                $this->lengowLog->write(LengowLog::CODE_IMPORT, $message, $this->logOutput, $this->marketplaceSku);
                return true;
            }
        }
        return false;
    }

    /**
     * Checks if the order status is valid for order creation
     *
     * @return bool
     */
    private function orderStatusIsValid(): bool
    {
        if (in_array($this->orderStateLengow, $this->lengowStates, true)) {
            return true;
        }
        $orderProcessState = $this->lengowOrder->getOrderProcessState($this->orderStateLengow);
        // check and complete an order not imported if it is canceled or refunded
        if ($this->lengowOrderId && $orderProcessState === LengowOrder::PROCESS_STATE_FINISH) {
            $this->lengowOrderError->finishOrderErrors($this->lengowOrderId);
            $this->lengowOrder->update($this->lengowOrderId, [
                LengowOrderDefinition::FIELD_IS_IN_ERROR => false,
                LengowOrderDefinition::FIELD_ORDER_LENGOW_STATE => $this->orderStateLengow,
                LengowOrderDefinition::FIELD_ORDER_PROCESS_STATE => $orderProcessState,
            ]);
        }
        $message = $this->lengowLog->encodeMessage('log.import.current_order_state_unavailable', [
            'order_state_marketplace' => $this->orderStateMarketplace,
            'marketplace_name' => $this->lengowMarketplace->getName(),
        ]);
        $this->errors[] = $this->lengowLog->decodeMessage($message, LengowTranslation::DEFAULT_ISO_CODE);
        $this->lengowLog->write(LengowLog::CODE_IMPORT, $message, $this->logOutput, $this->marketplaceSku);
        return false;
    }

    /**
     * Create a lengow order in lengow orders table
     *
     * @return bool
     */
    private function createLengowOrder(): bool
    {
        // load order types data
        $this->loadOrderTypesData();
        // load customer VAT number
        $this->loadVatNumberFromOrderData();
        // If the Lengow order already exists do not recreate it
        if ($this->lengowOrderId) {
            return true;
        }
        // create lengow order
        $this->lengowOrder->create([
            LengowOrderDefinition::FIELD_SALES_CHANNEL_ID => $this->salesChannel->getId(),
            LengowOrderDefinition::FIELD_MARKETPLACE_SKU => $this->marketplaceSku,
            LengowOrderDefinition::FIELD_MARKETPLACE_NAME => $this->lengowMarketplace->getName(),
            LengowOrderDefinition::FIELD_MARKETPLACE_LABEL => $this->lengowMarketplace->getLabel(),
            LengowOrderDefinition::FIELD_DELIVERY_ADDRESS_ID => $this->deliveryAddressId,
            LengowOrderDefinition::FIELD_ORDER_LENGOW_STATE => $this->orderStateLengow,
            LengowOrderDefinition::FIELD_ORDER_TYPES => $this->orderTypes,
            LengowOrderDefinition::FIELD_CUSTOMER_VAT_NUMBER => $this->customerVatNumber,
            LengowOrderDefinition::FIELD_ORDER_DATE => $this->getOrderDate(),
            LengowOrderDefinition::FIELD_MESSAGE => $this->getMessage(),
            LengowOrderDefinition::FIELD_EXTRA => (array) $this->orderData,
        ]);
        // get lengow order
        $lengowOrder = $this->lengowOrder->getLengowOrderByMarketplaceSku(
            $this->marketplaceSku,
            $this->lengowMarketplace->getName()
        );
        if ($lengowOrder) {
            $this->lengowOrderId = $lengowOrder->getId();
            $this->lengowLog->write(
                LengowLog::CODE_IMPORT,
                $this->lengowLog->encodeMessage('log.import.lengow_order_saved'),
                $this->logOutput,
                $this->marketplaceSku
            );
            return true;
        }
        $message = $this->lengowLog->encodeMessage('log.import.lengow_order_not_saved');
        $this->errors[] = $this->lengowLog->decodeMessage($message, LengowTranslation::DEFAULT_ISO_CODE);
        $this->lengowLog->write(LengowLog::CODE_IMPORT, $message, $this->logOutput, $this->marketplaceSku);
        return false;
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
     * Load vat_number from lengow order data
     */
    private function loadVatNumberFromOrderData(): void
    {
        $this->customerVatNumber = (string) (
            $this->orderData->billing_address->vat_number ?? $this->packageData->delivery->vat_number
        );
    }

    /**
     * Get order date in correct format for database
     *
     * @return string
     */
    private function getOrderDate(): string
    {
        $orderDate = (string) ($this->orderData->marketplace_order_date ?? $this->orderData->imported_at);
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
            : (string) $this->orderData->comments;
    }

    /**
     * Checks if the required order data is present and update Lengow order record
     *
     * @return bool
     */
    private function checkAndUpdateLengowOrderData(): bool
    {
        // checks if all necessary order data are present
        if (!$this->checkOrderData()) {
            return false;
        }
        // load order amount, processing fees and shipping costs
        $this->loadOrderAmount();
        // load tracking data
        $this->loadTrackingData();
        // update Lengow order with new data
        $this->lengowOrder->update($this->lengowOrderId, [
            LengowOrderDefinition::FIELD_CURRENCY => $this->orderData->currency->iso_a3,
            LengowOrderDefinition::FIELD_TOTAL_PAID => $this->orderAmount,
            LengowOrderDefinition::FIELD_ORDER_ITEM => $this->orderItems,
            LengowOrderDefinition::FIELD_CUSTOMER_NAME => $this->getCustomerName(),
            LengowOrderDefinition::FIELD_CUSTOMER_EMAIL => $this->getCustomerEmail(),
            LengowOrderDefinition::FIELD_CUSTOMER_VAT_NUMBER => $this->customerVatNumber,
            LengowOrderDefinition::FIELD_COMMISSION => (float) $this->orderData->commission,
            LengowOrderDefinition::FIELD_CARRIER => $this->carrierName,
            LengowOrderDefinition::FIELD_CARRIER_METHOD => $this->carrierMethod,
            LengowOrderDefinition::FIELD_CARRIER_TRACKING => $this->trackingNumber,
            LengowOrderDefinition::FIELD_CARRIER_RELAY_ID => $this->relayId,
            LengowOrderDefinition::FIELD_SENT_MARKETPLACE => $this->shippedByMp,
            LengowOrderDefinition::FIELD_DELIVERY_COUNTRY_ISO => $this->packageData->delivery->common_country_iso_a2,
            LengowOrderDefinition::FIELD_ORDER_LENGOW_STATE => $this->orderStateLengow,
            LengowOrderDefinition::FIELD_EXTRA => (array) $this->orderData,
        ]);
        return true;
    }

    /**
     * Checks if order data are present
     *
     * @return bool
     */
    protected function checkOrderData(): bool
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
        if (empty($errorMessages)) {
            return true;
        }
        foreach ($errorMessages as $errorMessage) {
            $this->lengowOrderError->create($this->lengowOrderId, $errorMessage);
            $decodedMessage = $this->lengowLog->decodeMessage($errorMessage, LengowTranslation::DEFAULT_ISO_CODE);
            $this->errors[] = $decodedMessage;
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

    /**
     * Load all order amount data (processing fee, shipping cost, order items and order amount)
     */
    private function loadOrderAmount(): void
    {
        $this->processingFee = (float) $this->orderData->processing_fee;
        $this->shippingCost = (float) $this->orderData->shipping;
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
                $stateProduct = $this->lengowMarketplace->getStateLengow((string) $product->marketplace_status);
                if ($stateProduct === LengowOrder::STATE_CANCELED || $stateProduct === LengowOrder:: STATE_REFUSED) {
                    continue;
                }
            }
            $nbItems += (int) $product->quantity;
            $totalAmount += (float) $product->amount;
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
        $firstName = ucfirst(strtolower((string) $this->orderData->billing_address->first_name));
        $lastName = ucfirst(strtolower((string) $this->orderData->billing_address->last_name));
        if (empty($firstName) && empty($lastName)) {
            return ucwords(strtolower((string) $this->orderData->billing_address->full_name));
        }
        if (empty($firstName)) {
            return $lastName;
        }
        if (empty($lastName)) {
            return $firstName;
        }
        return $firstName . ' ' . $lastName;
    }

    /**
     * Get customer email
     *
     * @return string
     */
    private function getCustomerEmail(): string
    {
        return $this->orderData->billing_address->email !== null
            ? (string) $this->orderData->billing_address->email
            : (string) $this->packageData->delivery->email;
    }

    /**
     * Checks if an order sent by the marketplace must be created or not
     *
     * @return bool
     */
    private function canCreateOrderShippedByMarketplace(): bool
    {
        // check if the order is shipped by marketplace
        if ($this->shippedByMp) {
            $message = $this->lengowLog->encodeMessage('log.import.order_shipped_by_marketplace', [
                'marketplace_name' => $this->lengowMarketplace->getName()
            ]);
            $this->lengowLog->write(LengowLog::CODE_IMPORT, $message, $this->logOutput, $this->marketplaceSku);
            if (!$this->lengowConfiguration->get(LengowConfiguration::SHIPPED_BY_MARKETPLACE_ENABLED)) {
                $this->errors[] = $this->lengowLog->decodeMessage($message, LengowTranslation::DEFAULT_ISO_CODE);
                $this->lengowOrder->update($this->lengowOrderId, [
                    LengowOrderDefinition::FIELD_ORDER_PROCESS_STATE => LengowOrder::PROCESS_STATE_FINISH,
                    LengowOrderDefinition::FIELD_IS_IN_ERROR => false,
                ]);
                return false;
            }
        }
        return true;
    }

    /**
     * Create a Shopware order
     *
     * @return bool
     */
    private function createOrder(): bool
    {
        try {
            // search and get all products
            $products = $this->getProducts();
            // get lengow address to create all specific Shopware addresses for customer and order
            $this->lengowAddress->init([
                'billing_data' => $this->orderData->billing_address,
                'shipping_data' => $this->packageData->delivery,
                'relay_id' => $this->relayId,
                'vat_number' => $this->customerVatNumber,
            ]);
            // get or create Shopware customer
            $customer = $this->getCustomer();
            // create a Shopware order
            $order = $this->createShopwareOrder($customer, $products);
            // update Lengow order with new data
            $this->lengowOrder->update($this->lengowOrderId, [
                LengowOrderDefinition::FIELD_ORDER_ID => $order->getId(),
                LengowOrderDefinition::FIELD_ORDER_SKU => $order->getOrderNumber(),
                LengowOrderDefinition::FIELD_ORDER_PROCESS_STATE => $this->lengowOrder->getOrderProcessState(
                    $this->orderStateLengow
                ),
                LengowOrderDefinition::FIELD_ORDER_LENGOW_STATE => $this->orderStateLengow,
                LengowOrderDefinition::FIELD_IS_IN_ERROR => false,
                LengowOrderDefinition::FIELD_IS_REIMPORTED => false,
                LengowOrderDefinition::FIELD_IMPORTED_AT => $this->lengowConfiguration->gmtDate(
                    null,
                    Defaults::STORAGE_DATE_TIME_FORMAT
                ),
            ]);
            $this->lengowLog->write(
                LengowLog::CODE_IMPORT,
                $this->lengowLog->encodeMessage('log.import.lengow_order_updated'),
                $this->logOutput,
                $this->marketplaceSku
            );
            // load order data for return
            $this->orderId = $order->getId();
            $this->orderReference = $order->getOrderNumber();
            // save order line id in lengow_order_line table
            $this->createLengowOrderLines($order, $products);
            $this->lengowLog->write(
                LengowLog::CODE_IMPORT,
                $this->lengowLog->encodeMessage('log.import.order_successfully_imported', [
                    'order_number' => $order->getOrderNumber(),
                ]),
                $this->logOutput,
                $this->marketplaceSku
            );
            // add quantity back for re-imported order and order shipped by marketplace
            $this->addQuantityBack($order, $products);
        } catch (LengowException $e) {
            $errorMessage = $e->getMessage();
        } catch (Exception $e) {
            $errorMessage = '[Shopware error]: "' . $e->getMessage()
                . '" in ' . $e->getFile() . ' on line ' . $e->getLine();
        }
        if (!isset($errorMessage)) {
            return true;
        }
        $this->lengowOrderError->create($this->lengowOrderId, $errorMessage);
        $decodedMessage = $this->lengowLog->decodeMessage($errorMessage, LengowTranslation::DEFAULT_ISO_CODE);
        $this->errors[] = $decodedMessage;
        $this->lengowLog->write(
            LengowLog::CODE_IMPORT,
            $this->lengowLog->encodeMessage('log.import.order_import_failed', [
                'decoded_message' => $decodedMessage,
            ]),
            $this->logOutput,
            $this->marketplaceSku
        );
        $this->lengowOrder->update($this->lengowOrderId, [
            LengowOrderDefinition::FIELD_ORDER_LENGOW_STATE => $this->orderStateLengow,
            LengowOrderDefinition::FIELD_IS_REIMPORTED => false,
            LengowOrderDefinition::FIELD_EXTRA => (array) $this->orderData,
        ]);
        return false;
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
                'merchant_product_id' => (string) $productData['merchant_product_id']->id,
                'marketplace_product_id' => (string) $productData['marketplace_product_id'],
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
            // if found, id does not concern a variation but a parent
            if ($product && (int) $product->getChildCount() !== 0) {
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
                $products[$productId]['quantity'] += (int) $productData['quantity'];
                $products[$productId]['amount'] += (float) $productData['amount'];
                $products[$productId]['order_line_ids'][] = $productData['marketplace_order_line_id'];
            } else {
                $products[$productId] = [
                    'shopware_product' => $product,
                    'quantity' => (int) $productData['quantity'],
                    'amount' => (float) $productData['amount'],
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
    private function createShopwareOrder(CustomerEntity $customer, array $products): OrderEntity
    {
        $token = Uuid::randomHex();
        $shippingMethodId = $this->lengowConfiguration->get(
            LengowConfiguration::DEFAULT_IMPORT_CARRIER_ID,
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
        return $this->lengowOrder->getOrderById($orderData['id']);
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
        // set cart vat mode for taxes
        $cart = $this->setOrderVatMode($cart);
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
     * Set cart vat mode (free for b2b, normal for other)
     *
     * @param Cart $cart Shopware order cart
     *
     * @return Cart
     */
    private function setOrderVatMode(Cart $cart): Cart
    {
        // if b2b import is activated and order is b2b type : set order as vat free
        if (isset($this->orderTypes[LengowOrder::TYPE_BUSINESS])
            && $this->orderTypes[LengowOrder::TYPE_BUSINESS]
            && $this->lengowConfiguration->get(LengowConfiguration::B2B_WITHOUT_TAX_ENABLED)
        ) {
            $cart->setPrice(
                new CartPrice(
                    0,
                    0,
                    0,
                    new CalculatedTaxCollection(),
                    new TaxRuleCollection(),
                    CartPrice::TAX_STATE_FREE
                )
            );
        }
        return $cart;
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
            1
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
            1
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
                    LengowOrderLineDefinition::FIELD_ORDER_ID => $order->getId(),
                    LengowOrderLineDefinition::FIELD_ORDER_LINE_ID => $orderLineId,
                    LengowOrderLineDefinition::FIELD_PRODUCT_ID => $productId,
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

    /**
     * Add quantity back to stock
     *
     * @param OrderEntity $order Shopware order instance
     * @param array $products Lengow products from Api
     */
    private function addQuantityBack(OrderEntity $order, array $products): void
    {
        if ($this->isReimported
            || ($this->shippedByMp
                && !$this->lengowConfiguration->get(LengowConfiguration::SHIPPED_BY_MARKETPLACE_STOCK_ENABLED)
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
                $sql = '
                    UPDATE product
                    SET stock = :stock, available_stock = :available_stock, version_id = :version
                    WHERE id = :id
                ';
                $retryableQuery = new RetryableQuery($this->connection, $this->connection->prepare($sql));

                $retryableQuery->execute([
                    'stock' => (int) $stock,
                    'available_stock' => (int) $availableStock,
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
                $errorMessage = '[Shopware error]: "' . $e->getMessage()
                    . '" in ' . $e->getFile() . ' on line ' . $e->getLine();
                $this->lengowLog->write(
                    LengowLog::CODE_ORM,
                    $this->lengowLog->encodeMessage('log.orm.record_insert_failed', [
                        'decoded_message' => str_replace(PHP_EOL, '', $errorMessage),
                    ])
                );
            }
        }
    }
}
