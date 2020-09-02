<?php declare(strict_types=1);

namespace Lengow\Connector\Service;

use \Exception;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\MultiFilter;
use Shopware\Core\Framework\Uuid\Uuid;
use Lengow\Connector\Entity\Lengow\OrderError\OrderErrorCollection as LengowOrderErrorCollection;
use Lengow\Connector\Entity\Lengow\Order\OrderEntity as LengowOrderEntity;

/**
 * Class LengowOrderError
 * @package Lengow\Connector\Service
 */
class LengowOrderError
{
    /**
     * @var int order error import type
     */
    public const TYPE_ERROR_IMPORT = 1;

    /**
     * @var int order error send type
     */
    public const TYPE_ERROR_SEND = 2;

    /**
     * @var EntityRepositoryInterface $orderErrorRepository Lengow order error repository
     */
    private $lengowOrderErrorRepository;

    /**
     * LengowConfiguration constructor
     *
     * @param EntityRepositoryInterface $lengowOrderErrorRepository Lengow order error repository access
     */
    public function __construct(EntityRepositoryInterface $lengowOrderErrorRepository)
    {
        $this->lengowOrderErrorRepository = $lengowOrderErrorRepository;
    }

    /**
     * Create an order error
     *
     * @param LengowOrderEntity $lengowOrder Lengow order instance
     * @param string $message error message
     * @param int $type error type (import or send)
     *
     * @return bool
     */
    public function create(LengowOrderEntity $lengowOrder, string $message, int $type = self::TYPE_ERROR_IMPORT): bool
    {
        $data = [
            'id' => Uuid::randomHex(),
            'order' => $lengowOrder,
            'message' => $message,
            'type' => $type,
        ];
        try {
            $this->lengowOrderErrorRepository->create([$data], Context::createDefaultContext());
        } catch (Exception $e) {
            return false;
        }
        return true;
    }

    /**
     * Update an order error
     *
     * @param string $orderErrorId Lengow order error id
     * @param array $params additional parameters for update
     *
     * @return bool
     */
    public function update(string $orderErrorId, array $params = []): bool
    {
        $data = ['id' => $orderErrorId];
        if (isset($params['is_finished'])) {
            $data['is_finished'] = $params['is_finished'];
        }
        if (isset($params['mail'])) {
            $data['mail'] = $params['mail'];
        }
        try {
            $this->lengowOrderErrorRepository->update([$data], Context::createDefaultContext());
        } catch (Exception $e) {
            return false;
        }
        return true;
    }

    /**
     * Get all order errors by marketplace sku and delivery address id
     *
     * @param string $marketplaceSku Marketplace sku
     * @param int $deliveryAddressId Lengow delivery address id
     * @param int $type order error type (import or send)
     *
     * @return EntityCollection|false
     */
    public function orderIsInError(string $marketplaceSku, int $deliveryAddressId, int $type = self::TYPE_ERROR_IMPORT)
    {
        $context = Context::createDefaultContext();
        $criteria = new Criteria();
        $criteria->addFilter(new MultiFilter(MultiFilter::CONNECTION_AND, [
            new EqualsFilter('order.marketplaceSku', $marketplaceSku),
            new EqualsFilter('order.deliveryAddressId', $deliveryAddressId),
            new EqualsFilter('type', $type),
            new EqualsFilter('isFinished', false),
        ]));
        /** @var LengowOrderErrorCollection $LengowOrderErrorCollection */
        $LengowOrderErrorCollection = $this->lengowOrderErrorRepository->search($criteria, $context)->getEntities();
        if ($LengowOrderErrorCollection->count() !== 0) {
            return $LengowOrderErrorCollection;
        }
        return false;
    }

    /**
     * Finish all order error for one lengow order
     *
     * @param string $lengowOrderId Lengow order id
     * @param int $type order error type (import or send)
     *
     * @return bool
     */
    public function finishOrderErrors(string $lengowOrderId, int $type = self::TYPE_ERROR_IMPORT): bool
    {
        $result = true;
        $context = Context::createDefaultContext();
        $criteria = new Criteria();
        $criteria->addFilter(new MultiFilter(MultiFilter::CONNECTION_AND, [
            new EqualsFilter('order.id', $lengowOrderId),
            new EqualsFilter('type', $type),
        ]));
        /** @var LengowOrderErrorCollection $LengowOrderErrorCollection */
        $LengowOrderErrorCollection = $this->lengowOrderErrorRepository->search($criteria, $context)->getEntities();
        foreach ($LengowOrderErrorCollection as $lengowOrderError) {
            $success = $this->update($lengowOrderError->getId(), ['is_finished' => true]);
            if (!$success) {
                $result = false;
            }
        }
        return $result;
    }
}
