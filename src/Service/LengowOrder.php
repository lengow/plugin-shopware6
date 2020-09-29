<?php declare(strict_types=1);

namespace Lengow\Connector\Service;

use \Exception;
use Shopware\Core\Checkout\Order\Aggregate\OrderDelivery\OrderDeliveryStates;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionStates;
use Shopware\Core\Checkout\Order\OrderCollection;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Checkout\Order\OrderStates;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\MultiFilter;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\StateMachine\Aggregation\StateMachineState\StateMachineStateCollection;
use Shopware\Core\System\StateMachine\Aggregation\StateMachineState\StateMachineStateEntity;
use Lengow\Connector\Entity\Lengow\Order\OrderEntity as LengowOrderEntity;
use Lengow\Connector\Entity\Lengow\Order\OrderCollection as LengowOrderCollection;

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
     * @var EntityRepositoryInterface $orderRepository Shopware order repository
     */
    private $orderRepository;

    /**
     * @var EntityRepositoryInterface $stateMachineStateRepository Shopware state machine state repository
     */
    private $stateMachineStateRepository;

    /**
     * @var EntityRepositoryInterface $lengowOrderRepository Lengow order repository
     */
    private $lengowOrderRepository;

    /**
     * @var LengowLog $lengowLog Lengow log service
     */
    private $lengowLog;

    /**
     * @var array $fieldList field list for the table lengow_order
     * required => Required fields when creating registration
     * update   => Fields allowed when updating registration
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
     * @param EntityRepositoryInterface $stateMachineStateRepository Shopware state machine state repository
     * @param EntityRepositoryInterface $lengowOrderRepository Lengow order repository
     * @param LengowLog $lengowLog Lengow log service
     */
    public function __construct(
        EntityRepositoryInterface $orderRepository,
        EntityRepositoryInterface $stateMachineStateRepository,
        EntityRepositoryInterface $lengowOrderRepository,
        LengowLog $lengowLog
    )
    {
        $this->orderRepository = $orderRepository;
        $this->stateMachineStateRepository = $stateMachineStateRepository;
        $this->lengowOrderRepository = $lengowOrderRepository;
        $this->lengowLog = $lengowLog;
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
        $criteria->addFilter(new EqualsFilter('id', $lengowOrderId));
        /** @var LengowOrderCollection $LengowOrderCollection */
        $LengowOrderCollection = $this->lengowOrderRepository->search($criteria, $context)->getEntities();
        if ($LengowOrderCollection->count() !== 0) {
            return $LengowOrderCollection->first();
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
        /** @var LengowOrderCollection $LengowOrderCollection */
        $LengowOrderCollection = $this->lengowOrderRepository->search($criteria, $context)->getEntities();
        if ($LengowOrderCollection->count() !== 0) {
            return $LengowOrderCollection->first();
        }
        return null;
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
        /** @var LengowOrderCollection $LengowOrderCollection */
        $LengowOrderCollection = $this->lengowOrderRepository->search($criteria, $context)->getEntities();
        if ($LengowOrderCollection->count() !== 0) {
            /** @var LengowOrderEntity $lengowOrder */
            $lengowOrder = $LengowOrderCollection->first();
            if ($lengowOrder->getOrder() !== null) {
                return $lengowOrder->getOrder();
            }
        }
        return null;
    }

    /**
     * Get lengow order from lengow order table
     *
     * @param string $orderId Shopware order id
     * @param int $deliveryAddressId Lengow delivery address id
     *
     * @return LengowOrderEntity|null
     */
    public function getLengowOrderByOrderId(string $orderId, int $deliveryAddressId): ?LengowOrderEntity
    {
        $context = Context::createDefaultContext();
        $criteria = new Criteria();
        $criteria->addFilter(new MultiFilter(MultiFilter::CONNECTION_AND, [
            new EqualsFilter('order.id', $orderId),
            new EqualsFilter('deliveryAddressId', $deliveryAddressId),
        ]));
        /** @var LengowOrderCollection $LengowOrderCollection */
        $LengowOrderCollection = $this->lengowOrderRepository->search($criteria, $context)->getEntities();
        return $LengowOrderCollection->count() !== 0 ? $LengowOrderCollection->first() : null;
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
        $criteria->addAssociation('deliveries.shippingMethod')
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
}
