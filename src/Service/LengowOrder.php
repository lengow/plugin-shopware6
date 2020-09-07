<?php declare(strict_types=1);

namespace Lengow\Connector\Service;

use \Exception;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\MultiFilter;
use Shopware\Core\Framework\Uuid\Uuid;
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
     * @var EntityRepositoryInterface $orderErrorRepository Lengow order repository
     */
    private $lengowOrderRepository;

    /**
     * @var LengowLog $lengowLog Lengow log service
     */
    private $lengowLog;

    /**
     * @var array field list for the table lengow_order
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
     * LengowOrder constructor
     *
     * @param EntityRepositoryInterface $lengowOrderRepository Lengow order repository
     * @param LengowLog $lengowLog Lengow log service
     */
    public function __construct(EntityRepositoryInterface $lengowOrderRepository, LengowLog $lengowLog)
    {
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
    public function getOrderProcessState($orderState): int
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
}
