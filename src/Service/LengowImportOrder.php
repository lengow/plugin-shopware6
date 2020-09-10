<?php declare(strict_types=1);

namespace Lengow\Connector\Service;

use \DateTime;
use \Exception;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\System\Currency\CurrencyCollection;
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
     * @var LengowProduct Lengow product service
     */
    private $lengowProduct;

    /**
     * @var EntityRepositoryInterface Shopware currency repository
     */
    private $currencyRepository;

    /**
     * @var SalesChannelEntity Shopware sales channel entity
     */
    private $salesChannel;

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
     * @param LengowProduct $lengowProduct Lengow product service
     * @param EntityRepositoryInterface $currencyRepository Shopware currency repository
     */
    public function __construct(
        LengowConfiguration $lengowConfiguration,
        LengowLog $lengowLog,
        LengowMarketplaceFactory $lengowMarketplaceFactory,
        LengowOrderError $lengowOrderError,
        LengowOrder $lengowOrder,
        LengowProduct $lengowProduct,
        EntityRepositoryInterface $currencyRepository
    )
    {
        $this->lengowConfiguration = $lengowConfiguration;
        $this->lengowLog = $lengowLog;
        $this->lengowMarketplaceFactory = $lengowMarketplaceFactory;
        $this->lengowOrderError = $lengowOrderError;
        $this->lengowOrder = $lengowOrder;
        $this->lengowProduct = $lengowProduct;
        $this->currencyRepository = $currencyRepository;
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
            $this->lengowLog->write(
                LengowLog::CODE_IMPORT,
                $this->lengowLog->encodeMessage('log.import.error_already_created', [
                    'decoded_message' => $decodedMessage,
                    'date_message' => $orderError->getCreatedAt()->format('Y-m-d H:i:s'),
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
            // TODO check and update order
            return false;
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
        if (!in_array($this->orderStateLengow, $this->lengowStates)) {
            $orderProcessState = $this->lengowOrder->getOrderProcessState($this->orderStateLengow);
            // check and complete an order not imported if it is canceled or refunded
            if ($lengowOrder && $orderProcessState === LengowOrder::PROCESS_STATE_FINISH) {
                $this->lengowOrderError->finishOrderErrors($lengowOrder->getId());
                $this->lengowOrder->update($lengowOrder->getId(), [
                    'is_in_error' => false,
                    'order_lengow_state' => $this->orderStateLengow,
                    'order_process_state' => $orderProcessState,
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
                if (!(bool)$this->lengowConfiguration->get('lengowImportShipMpEnabled')) {
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
            if (empty($products)) {
                throw new LengowException($this->lengowLog->encodeMessage('lengow_log.exception.no_product_to_cart'));
            }
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
            if ($this->lengowOrder->getLengowOrderByOrderId($externalId, $this->deliveryAddressId)) {
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
            }
        }
        if ($this->orderData->total_order == -1) {
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
            };
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
        $orderDate = (string)($this->orderData->marketplace_order_date ?? $this->orderData->imported_at);
        $message = is_array($this->orderData->comments)
            ? join(',', $this->orderData->comments)
            : (string)$this->orderData->comments;
        // create lengow order
        $this->lengowOrder->create([
            'salesChannelId' => $this->salesChannel->getId(),
            'marketplaceSku' => $this->marketplaceSku,
            'marketplaceName' => $this->lengowMarketplace->getName(),
            'marketplaceLabel' => $this->lengowMarketplace->getLabel(),
            'deliveryAddressId' => $this->deliveryAddressId,
            'orderLengowState' => $this->orderStateLengow,
            'orderTypes' => $this->orderTypes,
            'orderDate' => new DateTime(date('Y-m-d H:i:s', strtotime($orderDate))),
            'message' => $message,
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
    private function loadOrderTypesData()
    {
        $orderTypes = [];
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
    private function loadOrderAmountData()
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
    private function loadTrackingData()
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
     * Get vat_number from lengow order data
     *
     * @return string
     */
    private function getVatNumberFromOrderData(): string
    {
        if (isset($this->orderData->billing_address->vat_number)) {
            return $this->orderData->billing_address->vat_number;
        } elseif (isset($this->packageData->delivery->vat_number)) {
            return $this->packageData->delivery->vat_number;
        }
        return '';
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
        return $products;
    }
}
