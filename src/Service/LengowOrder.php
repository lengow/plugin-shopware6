<?php declare(strict_types=1);

namespace Lengow\Connector\Service;

use \Exception;
use Shopware\Core\Checkout\Order\Aggregate\OrderDelivery\OrderDeliveryCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderDelivery\OrderDeliveryDefinition;
use Shopware\Core\Checkout\Order\Aggregate\OrderDelivery\OrderDeliveryEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderDelivery\OrderDeliveryStates;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionStates;
use Shopware\Core\Checkout\Order\OrderCollection;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Checkout\Order\OrderDefinition;
use Shopware\Core\Checkout\Order\OrderStates;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\MultiFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\NotFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Grouping\FieldGrouping;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\StateMachine\Aggregation\StateMachineState\StateMachineStateCollection;
use Shopware\Core\System\StateMachine\Aggregation\StateMachineState\StateMachineStateEntity;
use Shopware\Core\System\StateMachine\Aggregation\StateMachineTransition\StateMachineTransitionActions;
use Shopware\Core\System\StateMachine\Aggregation\StateMachineTransition\StateMachineTransitionEntity;
use Shopware\Core\System\StateMachine\StateMachineRegistry;
use Shopware\Core\System\StateMachine\Transition;
use Lengow\Connector\Factory\LengowMarketplaceFactory;
use Lengow\Connector\Entity\Lengow\Order\OrderEntity as LengowOrderEntity;
use Lengow\Connector\Entity\Lengow\Order\OrderCollection as LengowOrderCollection;
use Lengow\Connector\Exception\LengowException;

/**
 * Class LengowOrder
 * @package Lengow\Connector\Service
 */
class LengowOrder
{
    /**
     * @var int order process state for order not imported
     */
    public const PROCESS_STATE_NEW = 0;

    /**
     * @var int order process state for order imported
     */
    public const PROCESS_STATE_IMPORT = 1;

    /**
     * @var int order process state for order finished
     */
    public const PROCESS_STATE_FINISH = 2;

    /**
     * @var string order state accepted
     */
    public const STATE_ACCEPTED = 'accepted';

    /**
     * @var string order state waiting_shipment
     */
    public const STATE_WAITING_SHIPMENT = 'waiting_shipment';

    /**
     * @var string order state shipped
     */
    public const STATE_SHIPPED = 'shipped';

    /**
     * @var string order state closed
     */
    public const STATE_CLOSED = 'closed';

    /**
     * @var string order state refused
     */
    public const STATE_REFUSED = 'refused';

    /**
     * @var string order state canceled
     */
    public const STATE_CANCELED = 'canceled';

    /**
     * @var string order state refunded
     */
    public const STATE_REFUNDED = 'refunded';

    /**
     * @var string order state lengow technical error
     */
    public const STATE_TECHNICAL_ERROR = 'technical_error';

    /**
     * @var string order type prime
     */
    public const TYPE_PRIME = 'is_prime';

    /**
     * @var string order type express
     */
    public const TYPE_EXPRESS = 'is_express';

    /**
     * @var string order type business
     */
    public const TYPE_BUSINESS = 'is_business';

    /**
     * @var string order type delivered by marketplace
     */
    public const TYPE_DELIVERED_BY_MARKETPLACE = 'is_delivered_by_marketplace';

    /**
     * @var EntityRepositoryInterface Shopware order repository
     */
    private $orderRepository;

    /**
     * @var EntityRepositoryInterface Shopware order delivery repository
     */
    private $orderDeliveryRepository;

    /**
     * @var EntityRepositoryInterface Shopware state machine state repository
     */
    private $stateMachineStateRepository;

    /**
     * @var StateMachineRegistry Shopware state machine registry
     */
    protected $stateMachineRegistry;

    /**
     * @var EntityRepositoryInterface $lengowOrderRepository Lengow order repository
     */
    private $lengowOrderRepository;

    /**
     * @var LengowLog Lengow log service
     */
    private $lengowLog;

    /**
     * @var LengowConfiguration Lengow configuration service
     */
    private $lengowConfiguration;

    /**
     * @var LengowConnector Lengow connector service
     */
    private $lengowConnector;

    /**
     * @var LengowOrderError Lengow order error service
     */
    private $lengowOrderError;

    /**
     * @var LengowOrderLine Lengow order line service
     */
    private $lengowOrderLine;

    /**
     * @var LengowMarketplaceFactory Lengow marketplace factory
     */
    private $lengowMarketplaceFactory;

    /**
     * @var LengowAction Lengow action service
     */
    private $lengowAction;

    /**
     * @var array $fieldList field list for the table lengow_order
     * required => Required fields when creating registration
     * updated  => Fields allowed when updating registration
     */
    private $fieldList = [
        'orderId' => ['required' => false, 'updated' => true],
        'orderSku' => ['required' => false, 'updated' => true],
        'salesChannelId' => ['required' => true, 'updated' => false],
        'deliveryAddressId' => ['required' => true, 'updated' => false],
        'deliveryCountryIso' => ['required' => false, 'updated' => true],
        'marketplaceSku' => ['required' => true, 'updated' => false],
        'marketplaceName' => ['required' => true, 'updated' => false],
        'marketplaceLabel' => ['required' => true, 'updated' => false],
        'orderLengowState' => ['required' => true, 'updated' => true],
        'orderProcessState' => ['required' => true, 'updated' => true],
        'orderDate' => ['required' => true, 'updated' => false],
        'orderItem' => ['required' => false, 'updated' => true],
        'orderTypes' => ['required' => true, 'updated' => false],
        'currency' => ['required' => false, 'updated' => true],
        'totalPaid' => ['required' => false, 'updated' => true],
        'commission' => ['required' => false, 'updated' => true],
        'customerName' => ['required' => false, 'updated' => true],
        'customerEmail' => ['required' => false, 'updated' => true],
        'customerVatNumber' => ['required' => false, 'updated' => true],
        'carrier' => ['required' => false, 'updated' => true],
        'carrierMethod' => ['required' => false, 'updated' => true],
        'carrierTracking' => ['required' => false, 'updated' => true],
        'carrierIdRelay' => ['required' => false, 'updated' => true],
        'sentMarketplace' => ['required' => false, 'updated' => true],
        'isInError' => ['required' => false, 'updated' => true],
        'isReimported' => ['required' => false, 'updated' => true],
        'message' => ['required' => true, 'updated' => true],
        'importedAt' => ['required' => false, 'updated' => true],
        'extra' => ['required' => false, 'updated' => true],
    ];

    /**
     * @var array state machine state correspondence
     */
    private $stateMachineStates = [
        OrderStates::STATE_MACHINE => [
            self::STATE_ACCEPTED => OrderStates::STATE_IN_PROGRESS,
            self::STATE_WAITING_SHIPMENT => OrderStates::STATE_IN_PROGRESS,
            self::STATE_SHIPPED => OrderStates::STATE_COMPLETED,
            self::STATE_CLOSED => OrderStates::STATE_COMPLETED,
            self::STATE_REFUSED => OrderStates::STATE_COMPLETED,
            self::STATE_CANCELED => OrderStates::STATE_CANCELLED,
            self::STATE_REFUNDED => OrderStates::STATE_COMPLETED,
            self::TYPE_DELIVERED_BY_MARKETPLACE => OrderStates::STATE_COMPLETED,
        ],
        OrderTransactionStates::STATE_MACHINE => [
            self::STATE_ACCEPTED => OrderTransactionStates::STATE_PAID,
            self::STATE_WAITING_SHIPMENT => OrderTransactionStates::STATE_PAID,
            self::STATE_SHIPPED => OrderTransactionStates::STATE_PAID,
            self::STATE_CLOSED => OrderTransactionStates::STATE_PAID,
            self::STATE_REFUSED => OrderTransactionStates::STATE_PAID,
            self::STATE_CANCELED => OrderTransactionStates::STATE_CANCELLED,
            self::STATE_REFUNDED => OrderTransactionStates::STATE_REFUNDED,
            self::TYPE_DELIVERED_BY_MARKETPLACE => OrderTransactionStates::STATE_PAID,
        ],
        OrderDeliveryStates::STATE_MACHINE => [
            self::STATE_ACCEPTED => OrderDeliveryStates::STATE_OPEN,
            self::STATE_WAITING_SHIPMENT => OrderDeliveryStates::STATE_OPEN,
            self::STATE_SHIPPED => OrderDeliveryStates::STATE_SHIPPED,
            self::STATE_CLOSED => OrderDeliveryStates::STATE_SHIPPED,
            self::STATE_REFUSED => OrderDeliveryStates::STATE_CANCELLED,
            self::STATE_CANCELED => OrderDeliveryStates::STATE_CANCELLED,
            self::STATE_REFUNDED => OrderDeliveryStates::STATE_CANCELLED,
            self::TYPE_DELIVERED_BY_MARKETPLACE => OrderDeliveryStates::STATE_SHIPPED,
        ],
    ];

    /**
     * LengowOrder constructor
     *
     * @param EntityRepositoryInterface $orderRepository Shopware order repository
     * @param EntityRepositoryInterface $orderDeliveryRepository Shopware order delivery repository
     * @param EntityRepositoryInterface $stateMachineStateRepository Shopware state machine state repository
     * @param StateMachineRegistry $stateMachineRegistry Shopware state machine registry service
     * @param EntityRepositoryInterface $lengowOrderRepository Lengow order repository
     * @param LengowLog $lengowLog Lengow log service
     * @param LengowConfiguration $lengowConfiguration Lengow configuration service
     * @param LengowConnector $lengowConnector Lengow connector service
     * @param LengowOrderError $lengowOrderError Lengow order error service
     * @param LengowOrderLine $lengowOrderLine Lengow order line service
     * @param LengowMarketplaceFactory $lengowMarketplaceFactory Lengow marketplace factory
     * @param LengowAction $lengowAction Lengow action service
     */
    public function __construct(
        EntityRepositoryInterface $orderRepository,
        EntityRepositoryInterface $orderDeliveryRepository,
        EntityRepositoryInterface $stateMachineStateRepository,
        StateMachineRegistry $stateMachineRegistry,
        EntityRepositoryInterface $lengowOrderRepository,
        LengowLog $lengowLog,
        LengowConfiguration $lengowConfiguration,
        LengowConnector $lengowConnector,
        LengowOrderError $lengowOrderError,
        LengowOrderLine $lengowOrderLine,
        LengowMarketplaceFactory $lengowMarketplaceFactory,
        LengowAction $lengowAction
    )
    {
        $this->orderRepository = $orderRepository;
        $this->orderDeliveryRepository = $orderDeliveryRepository;
        $this->stateMachineStateRepository = $stateMachineStateRepository;
        $this->stateMachineRegistry = $stateMachineRegistry;
        $this->lengowOrderRepository = $lengowOrderRepository;
        $this->lengowLog = $lengowLog;
        $this->lengowConfiguration = $lengowConfiguration;
        $this->lengowConnector = $lengowConnector;
        $this->lengowOrderError = $lengowOrderError;
        $this->lengowOrderLine = $lengowOrderLine;
        $this->lengowMarketplaceFactory = $lengowMarketplaceFactory;
        $this->lengowAction = $lengowAction;
    }

    /**
     * Create an order
     *
     * @param array $data All data for order creation
     *
     * @return bool
     */
    public function create(array $data = []): bool
    {
        $data = array_merge($data, ['id' => Uuid::randomHex()]);
        if (empty($data['orderProcessState'])) {
            $data['orderProcessState'] = self::PROCESS_STATE_NEW;
        }
        // checks if all mandatory data is present
        foreach ($this->fieldList as $key => $value) {
            if (!array_key_exists($key, $data) && $value['required']) {
                $this->lengowLog->write(
                    LengowLog::CODE_ORM,
                    $this->lengowLog->encodeMessage('log.orm.field_is_required', [
                        'field' => $key,
                    ])
                );
                return false;
            }
        }
        try {
            $this->lengowOrderRepository->create([$data], Context::createDefaultContext());
        } catch (Exception $e) {
            $errorMessage = '[Shopware error] "' . $e->getMessage() . '" ' . $e->getFile() . ' | ' . $e->getLine();
            $this->lengowLog->write(
                LengowLog::CODE_ORM,
                $this->lengowLog->encodeMessage('log.orm.record_insert_failed', [
                    'decoded_message' => str_replace(PHP_EOL, '', $errorMessage),
                ])
            );
            return false;
        }
        return true;
    }

    /**
     * Update an order
     *
     * @param string $lengowOrderId Lengow order id
     * @param array $data additional data for update
     *
     * @return bool
     */
    public function update(string $lengowOrderId, array $data = []): bool
    {
        // update only authorized values
        foreach ($this->fieldList as $key => $value) {
            if (array_key_exists($key, $data) && !$value['updated']) {
                unset($data[$key]);
            }
        }
        $data = array_merge($data, ['id' => $lengowOrderId]);
        try {
            $this->lengowOrderRepository->update([$data], Context::createDefaultContext());
        } catch (Exception $e) {
            $errorMessage = '[Shopware error] "' . $e->getMessage() . '" ' . $e->getFile() . ' | ' . $e->getLine();
            $this->lengowLog->write(
                LengowLog::CODE_ORM,
                $this->lengowLog->encodeMessage('log.orm.record_insert_failed', [
                    'decoded_message' => str_replace(PHP_EOL, '', $errorMessage),
                ])
            );
            return false;
        }
        return true;
    }

    /**
     * Get Lengow order from lengow order table by id
     *
     * @param string $lengowOrderId Lengow order id
     *
     * @return LengowOrderEntity|null
     */
    public function getLengowOrderById(string $lengowOrderId): ?LengowOrderEntity
    {
        $context = Context::createDefaultContext();
        $criteria = new Criteria();
        $criteria->setIds([$lengowOrderId]);
        $criteria->addAssociation('order.deliveries')
            ->addAssociation('order.deliveries.shippingMethod')
            ->addAssociation('order.deliveries.shippingOrderAddress.country')
            ->addAssociation('order.transactions.paymentMethod')
            ->addAssociation('order.lineItems')
            ->addAssociation('order.currency')
            ->addAssociation('order.addresses.country');
        /** @var LengowOrderCollection $lengowOrderCollection */
        $lengowOrderCollection = $this->lengowOrderRepository->search($criteria, $context)->getEntities();
        if ($lengowOrderCollection->count() !== 0) {
            return $lengowOrderCollection->first();
        }
        return null;
    }

    /**
     * Get Lengow order from lengow order table by marketplace sku, marketplace name and delivery address id
     *
     * @param string $marketplaceSku marketplace order sku
     * @param string $marketplaceName marketplace name
     * @param integer $deliveryAddressId Lengow delivery address id
     *
     * @return LengowOrderEntity|null
     */
    public function getLengowOrderByMarketplaceSku(
        string $marketplaceSku,
        string $marketplaceName,
        int $deliveryAddressId
    ): ?LengowOrderEntity
    {
        $context = Context::createDefaultContext();
        $criteria = new Criteria();
        $criteria->addFilter(new MultiFilter(MultiFilter::CONNECTION_AND, [
            new EqualsFilter('marketplaceSku', $marketplaceSku),
            new EqualsFilter('marketplaceName', $marketplaceName),
            new EqualsFilter('deliveryAddressId', $deliveryAddressId),
        ]));
        $criteria->addAssociation('order.deliveries')
            ->addAssociation('order.deliveries.shippingMethod')
            ->addAssociation('order.deliveries.shippingOrderAddress.country')
            ->addAssociation('order.transactions.paymentMethod')
            ->addAssociation('order.lineItems')
            ->addAssociation('order.currency')
            ->addAssociation('order.addresses.country');
        /** @var LengowOrderCollection $lengowOrderCollection */
        $lengowOrderCollection = $this->lengowOrderRepository->search($criteria, $context)->getEntities();
        if ($lengowOrderCollection->count() !== 0) {
            return $lengowOrderCollection->first();
        }
        return null;
    }

    /**
     * Get lengow order from lengow order table by Shopware order id
     *
     * @param string $orderId Shopware order id
     *
     * @return LengowOrderEntity|null
     */
    public function getLengowOrderByOrderId(string $orderId): ?LengowOrderEntity
    {
        $context = Context::createDefaultContext();
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('order.id', $orderId));
        $criteria->addAssociation('order.deliveries')
            ->addAssociation('order.deliveries.shippingMethod')
            ->addAssociation('order.deliveries.shippingOrderAddress.country')
            ->addAssociation('order.transactions.paymentMethod')
            ->addAssociation('order.lineItems')
            ->addAssociation('order.currency')
            ->addAssociation('order.addresses.country');
        /** @var LengowOrderCollection $lengowOrderCollection */
        $lengowOrderCollection = $this->lengowOrderRepository->search($criteria, $context)->getEntities();
        return $lengowOrderCollection->count() !== 0 ? $lengowOrderCollection->first() : null;
    }

    /**
     * Get lengow order from lengow order table by Shopware order number
     *
     * @param string $orderNumber Shopware order number
     * @param int|null $deliveryAddressId Lengow delivery address id
     *
     * @return LengowOrderEntity|null
     */
    public function getLengowOrderByOrderNumber(string $orderNumber, int $deliveryAddressId = null): ?LengowOrderEntity
    {
        $context = Context::createDefaultContext();
        $criteria = new Criteria();
        $criteria->addFilter(new MultiFilter(MultiFilter::CONNECTION_AND, [
            new EqualsFilter('order.orderNumber', $orderNumber),
            new EqualsFilter('deliveryAddressId', $deliveryAddressId),
        ]));
        $criteria->addAssociation('order.deliveries')
            ->addAssociation('order.deliveries.shippingMethod')
            ->addAssociation('order.deliveries.shippingOrderAddress.country')
            ->addAssociation('order.transactions.paymentMethod')
            ->addAssociation('order.lineItems')
            ->addAssociation('order.currency')
            ->addAssociation('order.addresses.country');
        /** @var LengowOrderCollection $lengowOrderCollection */
        $lengowOrderCollection = $this->lengowOrderRepository->search($criteria, $context)->getEntities();
        return $lengowOrderCollection->count() !== 0 ? $lengowOrderCollection->first() : null;
    }

    /**
     * Get Shopware order from lengow order table
     *
     * @param string $marketplaceSku marketplace order sku
     * @param string $marketplaceName marketplace name
     * @param integer $deliveryAddressId Lengow delivery address id
     *
     * @return OrderEntity|null
     */
    public function getOrderByMarketplaceSku(
        string $marketplaceSku,
        string $marketplaceName,
        int $deliveryAddressId
    ): ?OrderEntity
    {
        $context = Context::createDefaultContext();
        $criteria = new Criteria();
        $criteria->addFilter(new MultiFilter(MultiFilter::CONNECTION_AND, [
            new EqualsFilter('marketplaceSku', $marketplaceSku),
            new EqualsFilter('marketplaceName', $marketplaceName),
            new EqualsFilter('deliveryAddressId', $deliveryAddressId),
        ]));
        $criteria->addFilter(new MultiFilter(MultiFilter::CONNECTION_OR, [
            new EqualsFilter('orderProcessState', self::PROCESS_STATE_IMPORT),
            new EqualsFilter('orderProcessState', self::PROCESS_STATE_FINISH),
        ]));
        $criteria->addAssociation('order.deliveries')
            ->addAssociation('order.deliveries.shippingMethod')
            ->addAssociation('order.deliveries.shippingOrderAddress.country')
            ->addAssociation('order.transactions.paymentMethod')
            ->addAssociation('order.lineItems')
            ->addAssociation('order.currency')
            ->addAssociation('order.addresses.country');
        /** @var LengowOrderCollection $lengowOrderCollection */
        $lengowOrderCollection = $this->lengowOrderRepository->search($criteria, $context)->getEntities();
        if ($lengowOrderCollection->count() !== 0) {
            /** @var LengowOrderEntity $lengowOrder */
            $lengowOrder = $lengowOrderCollection->first();
            if ($lengowOrder->getOrder() !== null) {
                return $lengowOrder->getOrder();
            }
        }
        return null;
    }

    /**
     * Get all Shopware order for lengow order
     *
     * @param string $marketplaceSku marketplace order sku
     * @param string $marketplaceName marketplace name
     *
     * @return array
     */
    public function getAllOrdersByMarketplaceSku(string $marketplaceSku, string $marketplaceName): array
    {
        $orders = [];
        $criteria = new Criteria();
        $criteria->addFilter(new MultiFilter(MultiFilter::CONNECTION_AND, [
            new EqualsFilter('marketplaceSku', $marketplaceSku),
            new EqualsFilter('marketplaceName', $marketplaceName),
        ]));
        $criteria->addAssociation('order.deliveries')
            ->addAssociation('order.deliveries.shippingMethod')
            ->addAssociation('order.deliveries.shippingOrderAddress.country')
            ->addAssociation('order.transactions.paymentMethod')
            ->addAssociation('order.lineItems')
            ->addAssociation('order.currency')
            ->addAssociation('order.addresses.country');
        /** @var LengowOrderCollection $lengowOrderCollection */
        $lengowOrderCollection = $this->lengowOrderRepository->search($criteria, Context::createDefaultContext())
            ->getEntities();
        if ($lengowOrderCollection->count() !== 0) {
            /** @var LengowOrderEntity $lengowOrder */
            foreach ($lengowOrderCollection as $lengowOrder) {
                if ($lengowOrder->getOrder() !== null) {
                    $orders[] = $lengowOrder->getOrder();
                }
            }
        }
        return $orders;
    }

    /**
     * Get all unsent orders
     *
     * @return array
     */
    public function getUnsentOrders(): array
    {
        $orders = [];
        $criteria = new Criteria();
        $criteria->addFilter(new MultiFilter(MultiFilter::CONNECTION_AND, [
            new EqualsFilter('orderProcessState', self::PROCESS_STATE_IMPORT),
            new EqualsFilter('isInError', false),
            new MultiFilter(MultiFilter::CONNECTION_OR, [
                new EqualsFilter(
                    'order.stateMachineState.technicalName',
                    OrderStates::STATE_COMPLETED
                ),
                new EqualsFilter(
                    'order.deliveries.stateMachineState.technicalName',
                    OrderDeliveryStates::STATE_SHIPPED
                ),
            ])
        ]));
        $criteria->addAssociation('order.deliveries')
            ->addAssociation('order.deliveries.shippingMethod')
            ->addAssociation('order.deliveries.shippingOrderAddress.country')
            ->addAssociation('order.transactions.paymentMethod')
            ->addAssociation('order.lineItems')
            ->addAssociation('order.currency')
            ->addAssociation('order.addresses.country');
        /** @var LengowOrderCollection $lengowOrderCollection */
        $lengowOrderCollection = $this->lengowOrderRepository->search($criteria, Context::createDefaultContext())
            ->getEntities();
        if ($lengowOrderCollection->count() !== 0) {
            /** @var LengowOrderEntity $lengowOrder */
            foreach ($lengowOrderCollection as $lengowOrder) {
                if ($lengowOrder->getOrder() !== null) {
                    $orders[] = $lengowOrder->getOrder();
                }
            }
        }
        return $orders;
    }

    /**
     * Get all available marketplace list (name and label)
     *
     * @return array
     */
    public function getMarketplaceList(): array
    {
        $marketplaceList = [];
        $context = Context::createDefaultContext();
        $criteria = new Criteria();
        $criteria->addGroupField(new FieldGrouping('marketplaceName'));
        /** @var LengowOrderCollection $lengowOrderCollection */
        $lengowOrderCollection = $this->lengowOrderRepository->search($criteria, $context)->getEntities();
        if ($lengowOrderCollection->count() !== 0) {
            foreach ($lengowOrderCollection as $lengowOrder) {
                /** @var LengowOrderEntity $lengowOrder */
                $marketplaceList[$lengowOrder->getMarketplaceName()] = $lengowOrder->getMarketplaceLabel();
            }
        }
        return $marketplaceList;
    }

    /**
     * Get order process state
     *
     * @param string $orderState order state to be matched
     *
     * @return int
     */
    public function getOrderProcessState(string $orderState): int
    {
        switch ($orderState) {
            case self::STATE_ACCEPTED:
            case self::STATE_WAITING_SHIPMENT:
                return self::PROCESS_STATE_IMPORT;
            case self::STATE_SHIPPED:
            case self::STATE_CLOSED:
            case self::STATE_REFUSED:
            case self::STATE_CANCELED:
            case self::STATE_REFUNDED:
                return self::PROCESS_STATE_FINISH;
            default:
                return self::PROCESS_STATE_NEW;
        }
    }

    /**
     * Get Shopware state machine state for a specific Lengow order state and Shopware state machine
     *
     * @param string $stateMachineTechnicalName Shopware state machine technical name
     * @param string $orderStateLengow Lengow order state
     * @param bool $deliveredByMarketplace order is delivered by marketplace
     *
     * @return StateMachineStateEntity|null
     */
    public function getStateMachineStateByOrderState(
        string $stateMachineTechnicalName,
        string $orderStateLengow,
        bool $deliveredByMarketplace = false
    ): ?StateMachineStateEntity
    {
        if ($deliveredByMarketplace) {
            $orderStateLengow = self::TYPE_DELIVERED_BY_MARKETPLACE;
        }
        if (!isset($this->stateMachineStates[$stateMachineTechnicalName])
            && !isset($this->stateMachineStates[$stateMachineTechnicalName][$orderStateLengow])
        ) {
            return null;
        }
        $stateMachineStateTechnicalName = $this->stateMachineStates[$stateMachineTechnicalName][$orderStateLengow];
        return $this->getStateMachineState($stateMachineTechnicalName, $stateMachineStateTechnicalName);
    }

    /**
     * Get state machine state instance by technical name
     *
     * @param string $stateMachineTechnicalName Shopware state machine technical name
     * @param string $stateMachineStateTechnicalName Shopware state machine state technical name
     *
     * @return StateMachineStateEntity|null
     */
    public function getStateMachineState(
        string $stateMachineTechnicalName,
        string $stateMachineStateTechnicalName
    ): ?StateMachineStateEntity
    {
        $context = Context::createDefaultContext();
        $criteria = new Criteria();
        $criteria->addFilter(new MultiFilter(MultiFilter::CONNECTION_AND, [
            new EqualsFilter('stateMachine.technicalName', $stateMachineTechnicalName),
            new EqualsFilter('technicalName', $stateMachineStateTechnicalName),
        ]));
        /** @var StateMachineStateCollection $stateMachineStateCollection */
        $stateMachineStateCollection = $this->stateMachineStateRepository->search($criteria, $context)->getEntities();
        return $stateMachineStateCollection->count() !== 0 ? $stateMachineStateCollection->first() : null;
    }

    /**
     * Get Shopware order by id
     *
     * @param string $orderId Shopware order id
     *
     * @return OrderEntity|null
     */
    public function getOrderById(string $orderId): ?OrderEntity
    {
        $criteria = new Criteria();
        $criteria->setIds([$orderId]);
        $criteria->addAssociation('deliveries')
            ->addAssociation('deliveries.shippingMethod')
            ->addAssociation('deliveries.shippingOrderAddress.country')
            ->addAssociation('transactions.paymentMethod')
            ->addAssociation('lineItems')
            ->addAssociation('currency')
            ->addAssociation('addresses.country');
        /** @var OrderCollection $orderCollection */
        $orderCollection = $this->orderRepository->search($criteria, Context::createDefaultContext())
            ->getEntities();
        if ($orderCollection->count() !== 0) {
            return $orderCollection->first();
        }
        return null;
    }

    /**
     * Get Shopware order by lengow order id
     *
     * @param string $lengowOrderId Lengow order id
     *
     * @return OrderEntity|null
     */
    public function getOrderByLengowOrderId(string $lengowOrderId): ?OrderEntity
    {
        $lengowOrder = $this->getLengowOrderById($lengowOrderId);
        if ($lengowOrder && $lengowOrder->getOrder()) {
            return $lengowOrder->getOrder();
        }
        return null;
    }

    /**
     * Create a Shopware Order
     *
     * @param array $orderData all data for Shopware order creation
     *
     * @return bool
     */
    public function createOrder(array $orderData): bool
    {
        try {
            $this->orderRepository->create([$orderData], Context::createDefaultContext());
        } catch (Exception $e) {
            $errorMessage = '[Shopware error] "' . $e->getMessage() . '" ' . $e->getFile() . ' | ' . $e->getLine();
            $this->lengowLog->write(
                LengowLog::CODE_ORM,
                $this->lengowLog->encodeMessage('log.orm.record_insert_failed', [
                    'decoded_message' => str_replace(PHP_EOL, '', $errorMessage),
                ])
            );
            return false;
        }
        return true;
    }

    /**
     * Get Shopware order delivery by id
     *
     * @param string $orderDeliveryId Shopware order delivery id
     *
     * @return OrderDeliveryEntity|null
     */
    public function getOrderDeliveryById(string $orderDeliveryId): ?OrderDeliveryEntity
    {
        $criteria = new Criteria();
        $criteria->setIds([$orderDeliveryId]);
        $criteria->addAssociation('shippingMethod');
        /** @var OrderDeliveryCollection $orderDeliveryCollection */
        $orderDeliveryCollection = $this->orderDeliveryRepository
            ->search($criteria, Context::createDefaultContext())
            ->getEntities();
        return $orderDeliveryCollection->count() !== 0 ? $orderDeliveryCollection->first() : null;
    }

    /**
     * Update order state to marketplace state
     *
     * @param OrderEntity $order Shopware order instance
     * @param LengowOrderEntity $lengowOrder Lengow order instance
     * @param string $orderStateLengow lengow order status
     * @param object $packageData package data
     *
     * @return string|null
     */
    public function updateOrderState(
        OrderEntity $order,
        LengowOrderEntity $lengowOrder,
        string $orderStateLengow,
        object $packageData
    ): ?string
    {
        // finish actions if lengow order is shipped, closed, cancel or refunded
        $orderProcessState = $this->getOrderProcessState($orderStateLengow);
        $tracks = $packageData->delivery->trackings;
        $trackingCode = !empty($tracks) && !empty($tracks[0]->number) ? (string) $tracks[0]->number : false;
        if ($orderProcessState === self::PROCESS_STATE_FINISH) {
            $this->lengowAction->finishActions($order->getId());
            $this->lengowOrderError->finishOrderErrors($lengowOrder->getId(), LengowOrderError::TYPE_ERROR_SEND);
        }
        // update Lengow order if necessary
        $data = [];
        if ($lengowOrder->getOrderLengowState() !== $orderStateLengow) {
            $data['orderLengowState'] = $orderStateLengow;
            if ($trackingCode) {
                $data['carrierTracking'] = $trackingCode;
            }
        }
        if ($orderProcessState === self::PROCESS_STATE_FINISH) {
            if ($lengowOrder->getOrderProcessState() !== $orderProcessState) {
                $data['orderProcessState'] = $orderProcessState;
            }
            if ($lengowOrder->isInError()) {
                $data['isInError'] = false;
            }
        }
        if (!empty($data)) {
            $this->update($lengowOrder->getId(), $data);
        }
        // get all state machine state to compare state
        $orderState = $order->getStateMachineState();
        $technicalName = OrderStates::STATE_MACHINE;
        $currentOrderState = $this->getStateMachineStateByOrderState($technicalName, $orderStateLengow);
        $stateWaitingShipment = $this->getStateMachineStateByOrderState($technicalName, self::STATE_WAITING_SHIPMENT);
        $stateShipped = $this->getStateMachineStateByOrderState($technicalName, self::STATE_SHIPPED);
        $stateCanceled = $this->getStateMachineStateByOrderState($technicalName, self::STATE_CANCELED);
        // update Shopware order's status only if in accepted, waiting_shipment, shipped, closed or cancel
        if ($orderState && $currentOrderState && $orderState->getId() !== $currentOrderState->getId()) {
            if (($stateWaitingShipment && $stateShipped)
                && $orderState->getId() === $stateWaitingShipment->getId()
                && $currentOrderState->getId() === $stateShipped->getId()
            ) {
                if ($trackingCode) {
                    $this->addTrackingCode($order, $trackingCode);
                }
                $this->changeOrderStates($order, StateMachineTransitionActions::ACTION_SHIP);
                return OrderStates::STATE_COMPLETED;
            }
            if (($stateWaitingShipment && $stateShipped && $stateCanceled)
                && $currentOrderState->getId() === $stateCanceled->getId()
                && ($orderState->getId() === $stateWaitingShipment->getId()
                    || $orderState->getId() === $stateShipped->getId()
                )
            ) {
                $this->changeOrderStates($order, StateMachineTransitionActions::ACTION_CANCEL);
                return OrderStates::STATE_CANCELLED;
            }
        }
        return null;
    }

    /**
     * Cancel or ship a Shopware order
     *
     * @param OrderEntity $order Shopware order instance
     * @param string $actionName action name for transition
     */
    public function changeOrderStates(OrderEntity $order, string $actionName): void
    {
        // change order delivery state machine
        if ($order->getDeliveries() !== null && $order->getDeliveries()->count() > 0) {
            $delivery = $order->getDeliveries()->first();
            if ($delivery !== null) {
                $this->addTransition(OrderDeliveryDefinition::ENTITY_NAME, $delivery->getId(), $actionName);
            }
        }
        // change order state machine
        $actionName = $actionName === StateMachineTransitionActions::ACTION_SHIP
            ? StateMachineTransitionActions::ACTION_COMPLETE
            : $actionName;
        $this->addTransition(OrderDefinition::ENTITY_NAME, $order->getId(), $actionName);
    }

    /**
     * Put a shopware order in Lengow technical error state
     *
     * @param OrderEntity $order Shopware order
     */
    public function putOrderInLengowTechnicalErrorState(OrderEntity $order) : void
    {
        $this->addTransition(
            OrderDefinition::ENTITY_NAME,
            $order->getId(),
            self::STATE_TECHNICAL_ERROR
        );
    }

    /**
     * Set a lengow order as re-imported
     *
     * @param LengowOrderEntity $lengowOrder the lengow order
     * @return bool
     */
    public function setAsReImported(LengowOrderEntity $lengowOrder) : bool
    {
        return $this->update($lengowOrder->getId(), [
            'isReimported' => true,
        ]);
    }

    /**
     * Add new transition for a specific entity
     *
     * @param string $entityName Shopware entity name
     * @param string $entityId Shopware entity id
     * @param string $actionName Shopware transition action name
     */
    public function addTransition(string $entityName, string $entityId, string $actionName): void
    {
        $orderAvailableActions = $this->getAvailableTransitions($entityName, $entityId);
        if (in_array($actionName, $orderAvailableActions, true)) {
            $this->stateMachineRegistry->transition(
                new Transition($entityName, $entityId, $actionName, 'stateId'),
                Context::createDefaultContext()
            );
        }
    }

    /**
     * Get all available action for a state machine (order, delivery or transaction)
     *
     * @param string $entityName Shopware entity name
     * @param string $entityId Shopware entity id
     *
     * @return array
     */
    public function getAvailableTransitions(string $entityName, string $entityId): array
    {
        $availableActions = [];
        /** @var StateMachineTransitionEntity[] $availableTransitions */
        $availableTransitions = $this->stateMachineRegistry->getAvailableTransitions(
            $entityName,
            $entityId,
            'stateId',
            Context::createDefaultContext()
        );
        foreach ($availableTransitions as $transition) {
            $availableActions[] = $transition->getActionName();
        }
        return $availableActions;
    }

    /**
     * Add a tracking code to the Shopware order
     *
     * @param OrderEntity $order Shopware order instance
     * @param string $trackingCode tracking code
     *
     * @return bool
     */
    public function addTrackingCode(OrderEntity $order, string $trackingCode): bool
    {
        if ($order->getDeliveries() === null) {
            return false;
        }
        /** @var orderDeliveryEntity $delivery */
        $delivery = $order->getDeliveries()->first();
        if ($delivery === null) {
            return false;
        }
        $trackingCodes = $delivery->getTrackingCodes();
        if (in_array($trackingCode, $trackingCodes, true)) {
            return true;
        }
        $data = [
            'id' => $delivery->getId(),
            'trackingCodes' => array_merge([$trackingCode], $trackingCodes),
        ];
        try {
            $this->orderDeliveryRepository->update([$data], Context::createDefaultContext());
        } catch (Exception $e) {
            $errorMessage = '[Shopware error] "' . $e->getMessage() . '" ' . $e->getFile() . ' | ' . $e->getLine();
            $this->lengowLog->write(
                LengowLog::CODE_ORM,
                $this->lengowLog->encodeMessage('log.orm.record_insert_failed', [
                    'decoded_message' => str_replace(PHP_EOL, '', $errorMessage),
                ])
            );
            return false;
        }
        return true;
    }

    /**
     * Synchronize order with Lengow API
     *
     * @param OrderEntity $order Shopware order instance
     * @param bool $logOutput see log or not
     *
     * @return bool
     */
    public function synchronizeOrder(OrderEntity $order, bool $logOutput = false): bool
    {
        $lengowOrder = $this->getLengowOrderByOrderId($order->getId());
        if ($lengowOrder === null || !$this->lengowConnector->isValidAuth($logOutput)) {
            return false;
        }
        /** @var OrderEntity[] $orders */
        $orders = $this->getAllOrdersByMarketplaceSku(
            $lengowOrder->getMarketplaceSku(),
            $lengowOrder->getMarketplaceName()
        );
        if (empty($orders)) {
            return false;
        }
        $merchantOrderIds = [];
        foreach ($orders as $shopwareOrder) {
            $merchantOrderIds[] = $shopwareOrder->getOrderNumber();
        }
        $body = [
            'account_id' => (int) $this->lengowConfiguration->get(LengowConfiguration::ACCOUNT_ID),
            'marketplace_order_id' => $lengowOrder->getMarketplaceSku(),
            'marketplace' => $lengowOrder->getMarketplaceName(),
            'merchant_order_id' => $merchantOrderIds,
        ];
        try {
            $result = $this->lengowConnector->patch(
                LengowConnector::API_ORDER_MOI,
                [],
                LengowConnector::FORMAT_JSON,
                json_encode($body),
                $logOutput
            );
        } catch (Exception $e) {
            $message = $this->lengowLog->decodeMessage($e->getMessage(), LengowTranslation::DEFAULT_ISO_CODE);
            $this->lengowLog->write(
                LengowLog::CODE_CONNECTOR,
                $this->lengowLog->encodeMessage('log.connector.error_api', [
                    'error_code' => $e->getCode(),
                    'error_message' => $message,
                ]),
                $logOutput,
                $lengowOrder->getMarketplaceSku()
            );
            return false;
        }
        return !($result === null
            || (isset($result['detail']) && $result['detail'] === 'Pas trouvé.')
            || isset($result['error'])
        );
    }

    /**
     * Send Order action
     *
     * @param string $action Lengow Actions (ship or cancel)
     * @param OrderEntity $order Shopware order instance
     * @param OrderDeliveryEntity|null $orderDelivery Shopware order delivery instance
     *
     * @return bool
     */
    public function callAction(string $action, OrderEntity $order, OrderDeliveryEntity $orderDelivery = null): bool
    {
        $success = true;
        $lengowOrder = $this->getLengowOrderByOrderId($order->getId());
        if ($lengowOrder === null) {
            return false;
        }
        $this->lengowLog->write(
            LengowLog::CODE_ACTION,
            $this->lengowLog->encodeMessage('log.order_action.try_to_send_action', [
                'action' => $action,
                'order_number' => $order->getOrderNumber(),
            ]),
            false,
            $lengowOrder->getMarketplaceSku()
        );
        try {
            // finish all order errors before API call
            $this->lengowOrderError->finishOrderErrors($lengowOrder->getId(), LengowOrderError::TYPE_ERROR_SEND);
            if ($lengowOrder->isInError()) {
                $this->update($lengowOrder->getId(), [
                    'isInError' => false,
                ]);
            }
            $marketplace = $this->lengowMarketplaceFactory->create($lengowOrder->getMarketplaceName());
            if ($marketplace->containOrderLine($action)) {
                $orderLineIds = $this->lengowOrderLine->getOrderLineIdsByOrderId($order->getId());
                // get order line ids by API for security
                if (empty($orderLineIds)) {
                    $orderLineCollection = $this->getOrderLineIdsByApi($lengowOrder);
                }
                if (empty($orderLineIds)) {
                    throw new LengowException(
                        $this->lengowLog->encodeMessage('lengow_log.exception.order_line_required')
                    );
                }
                foreach ($orderLineIds as $orderLineId) {
                    $marketplace->callAction($action, $lengowOrder, $order, $orderDelivery, $orderLineId);
                }
            } else {
                $marketplace->callAction($action, $lengowOrder, $order, $orderDelivery);
            }
        } catch (LengowException $e) {
            $errorMessage = $e->getMessage();
        } catch (Exception $e) {
            $errorMessage = 'Shopware error: "' . $e->getMessage() . '" ' . $e->getFile() . ' line ' . $e->getLine();
        }
        if (isset($errorMessage)) {
            if ($lengowOrder->getOrderProcessState() !== self::PROCESS_STATE_FINISH) {
                $this->update($lengowOrder->getId(), [
                    'isInError' => true,
                ]);
                $this->lengowOrderError->create(
                    $lengowOrder->getId(),
                    $errorMessage,
                    LengowOrderError::TYPE_ERROR_SEND
                );
            }
            $decodedMessage = $this->lengowLog->decodeMessage($errorMessage, LengowTranslation::DEFAULT_ISO_CODE);
            $this->lengowLog->write(
                LengowLog::CODE_ACTION,
                $this->lengowLog->encodeMessage('log.order_action.call_action_failed', [
                    'decoded_message' => $decodedMessage,
                ]),
                false,
                $lengowOrder->getMarketplaceSku()
            );
            $success = false;
        }
        $key = $success ? 'log.order_action.action_send': 'log.order_action.action_not_send';
        $message = $this->lengowLog->encodeMessage($key, [
            'action' => $action,
            'order_number' => $order->getOrderNumber(),
        ]);
        $this->lengowLog->write(LengowLog::CODE_ACTION, $message, false, $lengowOrder->getMarketplaceSku());
        return $success;
    }

    /**
     * Get order line by API
     *
     * @param LengowOrderEntity $lengowOrder Lengow order instance
     *
     * @return array
     */
    public function getOrderLineIdsByApi(LengowOrderEntity $lengowOrder): array
    {
        $orderLinesByPackage = [];
        $results =  $this->lengowConnector->queryApi(
            LengowConnector::GET,
            LengowConnector::API_ORDER,
            [
                'marketplace_order_id' => $lengowOrder->getMarketplaceSku(),
                'marketplace' => $lengowOrder->getMarketplaceName(),
            ]
        );
        if (isset($results->count) && (int) $results->count === 0) {
            return [];
        }
        $orderData = $results->results[0];
        foreach ($orderData->packages as $package) {
            $packageLines = [];
            foreach ($package->cart as $product) {
                $packageLines[] = (string) $product->marketplace_order_line_id;
            }
            $orderLinesByPackage[(int) $package->delivery->id] = $packageLines;
        }
        return $orderLinesByPackage[$lengowOrder->getDeliveryAddressId()] ?? [];
    }

    /**
     * Return the number of Lengow orders imported in Shopware
     *
     * @return int
     */
    public function countOrderImportedByLengow(): int
    {
        $context = Context::createDefaultContext();
        $criteria = new Criteria();
        $criteria->addFilter(new NotFilter(NotFilter::CONNECTION_AND, [new EqualsFilter('orderId', null)]));
        /** @var LengowOrderCollection $lengowOrderCollection */
        return $this->lengowOrderRepository->search($criteria, $context)->getEntities()->count();
    }

    /**
     * Return the number of Lengow orders with error
     *
     * @return int
     */
    public function countOrderWithError(): int
    {
        $context = Context::createDefaultContext();
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('isInError', true));
        /** @var LengowOrderCollection $lengowOrderCollection */
        return $this->lengowOrderRepository->search($criteria, $context)->getEntities()->count();
    }

    /**
     * Return the number of Lengow orders to be sent
     *
     * @return int
     */
    public function countOrderToBeSent(): int
    {
        $context = Context::createDefaultContext();
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('orderProcessState', 1));
        /** @var LengowOrderCollection $lengowOrderCollection */
        return $this->lengowOrderRepository->search($criteria, $context)->getEntities()->count();
    }
}
