<?php declare(strict_types=1);

namespace Lengow\Connector\Service;

use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\MultiFilter;
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
    public const PROCESS_STATE_NOT_IMPORTED = 0;

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
     * @var EntityRepositoryInterface $orderErrorRepository Lengow order repository
     */
    private $lengowOrderRepository;

    /**
     * LengowConfiguration constructor
     *
     * @param EntityRepositoryInterface $lengowOrderRepository Lengow order repository
     */
    public function __construct(EntityRepositoryInterface $lengowOrderRepository)
    {
        $this->lengowOrderRepository = $lengowOrderRepository;
    }

    /**
     * Get Shopware order from lengow order table
     *
     * @param string $marketplaceSku Lengow order id
     * @param string $marketplaceName marketplace name
     * @param integer $deliveryAddressId Lengow delivery address id
     *
     * @return OrderEntity|null
     */
    public function getOrderFromLengowOrder(
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
    public function getLengowOrderFromOrderId(string $orderId, int $deliveryAddressId): ?LengowOrderEntity
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
}
