<?php declare(strict_types=1);

namespace Lengow\Connector\Service;

use \Exception;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Uuid\Uuid;
use Lengow\Connector\Entity\Lengow\OrderLine\OrderLineCollection as LengowOrderLineCollection;
use Lengow\Connector\Entity\Lengow\OrderLine\OrderLineEntity as LengowOrderLineEntity;

/**
 * Class LengowOrderLine
 * @package Lengow\Connector\Service
 */
class LengowOrderLine
{
    /**
     * @var EntityRepositoryInterface $lengowOrderLineRepository Lengow order line repository
     */
    private $lengowOrderLineRepository;

    /**
     * @var LengowLog $lengowLog Lengow log service
     */
    private $lengowLog;

    /**
     * @var array $fieldList field list for the table lengow_order_line
     * required => Required fields when creating registration
     * updated  => Fields allowed when updating registration
     */
    private $fieldList = [
        'orderId' => ['required' => true, 'updated' => false],
        'productId' => ['required' => true, 'updated' => false],
        'orderLineId' => ['required' => true, 'updated' => false],
    ];

    /**
     * LengowOrderLine constructor
     *
     * @param EntityRepositoryInterface $lengowOrderLineRepository Lengow order line repository
     * @param LengowLog $lengowLog Lengow log service
     */
    public function __construct(EntityRepositoryInterface $lengowOrderLineRepository, LengowLog $lengowLog)
    {
        $this->lengowOrderLineRepository = $lengowOrderLineRepository;
        $this->lengowLog = $lengowLog;
    }

    /**
     * Create an order line
     *
     * @param array $data All data for order line creation
     *
     * @return bool
     */
    public function create(array $data): bool
    {
        $data = array_merge($data, ['id' => Uuid::randomHex()]);
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
            $this->lengowOrderLineRepository->create([$data], Context::createDefaultContext());
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
     * Get all order line id for a Shopware order
     *
     * @param string $orderId Shopware order id
     *
     * @return array
     */
    public function getOrderLineIdsByOrderId(string $orderId): array
    {
        $orderLines = [];
        $context = Context::createDefaultContext();
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('order.id', $orderId));
        /** @var LengowOrderLineCollection $lengowOrderLineCollection */
        $lengowOrderLineCollection = $this->lengowOrderLineRepository->search($criteria, $context)->getEntities();
        if ($lengowOrderLineCollection->count() !== 0) {
            /** @var LengowOrderLineEntity $lengowOrderLine */
            foreach ($lengowOrderLineCollection as $lengowOrderLine) {
                $orderLines[] = $lengowOrderLine->getOrderLineId();
            }
        }
        return $orderLines;
    }
}
