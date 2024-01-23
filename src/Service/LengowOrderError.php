<?php declare(strict_types=1);

namespace Lengow\Connector\Service;

use Exception;
use Lengow\Connector\Entity\Lengow\Order\OrderDefinition as LengowOrderDefinition;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\MultiFilter;
use Shopware\Core\Framework\Uuid\Uuid;
use Lengow\Connector\Entity\Lengow\OrderError\OrderErrorCollection as LengowOrderErrorCollection;
use Lengow\Connector\Entity\Lengow\OrderError\OrderErrorDefinition as LengowOrderErrorDefinition;
use Lengow\Connector\Util\EnvironmentInfoProvider;

/**
 * Class LengowOrderError
 * @package Lengow\Connector\Service
 */
class LengowOrderError
{
    /* Order error types */
    public const TYPE_ERROR_IMPORT = 1;
    public const TYPE_ERROR_SEND = 2;

    /**
     * @var EntityRepository $orderErrorRepository Lengow order error repository
     */
    private $lengowOrderErrorRepository;

    /**
     * @var LengowLog $lengowLog Lengow log service
     */
    private $lengowLog;

    /**
     * @var array $fieldList field list for the table lengow_order_error
     * required => Required fields when creating registration
     * updated  => Fields allowed when updating registration
     */
    private $fieldList = [
        LengowOrderErrorDefinition::FIELD_LENGOW_ORDER_ID => [
            EnvironmentInfoProvider::FIELD_REQUIRED => true,
            EnvironmentInfoProvider::FIELD_CAN_BE_UPDATED => false,
        ],
        LengowOrderErrorDefinition::FIELD_MESSAGE => [
            EnvironmentInfoProvider::FIELD_REQUIRED => true,
            EnvironmentInfoProvider::FIELD_CAN_BE_UPDATED => false,
        ],
        LengowOrderErrorDefinition::FIELD_TYPE => [
            EnvironmentInfoProvider::FIELD_REQUIRED => true,
            EnvironmentInfoProvider::FIELD_CAN_BE_UPDATED => false,
        ],
        LengowOrderErrorDefinition::FIELD_IS_FINISHED => [
            EnvironmentInfoProvider::FIELD_REQUIRED => false,
            EnvironmentInfoProvider::FIELD_CAN_BE_UPDATED => true,
        ],
        LengowOrderErrorDefinition::FIELD_MAIL => [
            EnvironmentInfoProvider::FIELD_REQUIRED => false,
            EnvironmentInfoProvider::FIELD_CAN_BE_UPDATED => true,
        ],
    ];

    /**
     * LengowOrderError constructor
     *
     * @param EntityRepository $lengowOrderErrorRepository Lengow order error repository access
     * @param LengowLog $lengowLog Lengow log service
     */
    public function __construct(EntityRepository $lengowOrderErrorRepository, LengowLog $lengowLog)
    {
        $this->lengowOrderErrorRepository = $lengowOrderErrorRepository;
        $this->lengowLog = $lengowLog;
    }

    /**
     * Create an order error
     *
     * @param string $lengowOrderId Lengow order id
     * @param string $message error message
     * @param int $type error type (import or send)
     *
     * @return bool
     */
    public function create(string $lengowOrderId, string $message, int $type = self::TYPE_ERROR_IMPORT): bool
    {
        $data = [
            LengowOrderErrorDefinition::FIELD_ID => Uuid::randomHex(),
            LengowOrderErrorDefinition::FIELD_LENGOW_ORDER_ID => $lengowOrderId,
            LengowOrderErrorDefinition::FIELD_MESSAGE => $message,
            LengowOrderErrorDefinition::FIELD_TYPE => $type,
        ];
        // checks if all mandatory data is present
        foreach ($this->fieldList as $key => $value) {
            if (!array_key_exists($key, $data) && $value[EnvironmentInfoProvider::FIELD_REQUIRED]) {
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
            $this->lengowOrderErrorRepository->create([$data], Context::createDefaultContext());
        } catch (Exception $e) {
            $errorMessage = '[Shopware error]: "' . $e->getMessage()
                . '" in ' . $e->getFile() . ' on line ' . $e->getLine();
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
     * Update an order error
     *
     * @param string $orderErrorId Lengow order error id
     * @param array $data additional parameters for update
     *
     * @return bool
     */
    public function update(string $orderErrorId, array $data = []): bool
    {
        // update only authorized values
        foreach ($this->fieldList as $key => $value) {
            if (array_key_exists($key, $data) && !$value[EnvironmentInfoProvider::FIELD_CAN_BE_UPDATED]) {
                unset($data[$key]);
            }
        }
        $data = array_merge($data, [LengowOrderErrorDefinition::FIELD_ID => $orderErrorId]);
        try {
            $this->lengowOrderErrorRepository->update([$data], Context::createDefaultContext());
        } catch (Exception $e) {
            $errorMessage = '[Shopware error]: "' . $e->getMessage()
                . '" in ' . $e->getFile() . ' on line ' . $e->getLine();
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
     * Get all order errors by marketplace sku and marketplace name
     *
     * @param string $marketplaceSku Marketplace sku
     * @param int $type Order error type (import or send)
     *
     * @return EntityCollection|null
     */
    public function orderIsInError(
        string $marketplaceSku,
        int $type = self::TYPE_ERROR_IMPORT
    ): ?EntityCollection
    {
        $context = Context::createDefaultContext();
        $criteria = new Criteria();
        $criteria->addFilter(new MultiFilter(MultiFilter::CONNECTION_AND, [
            new EqualsFilter('order.marketplaceSku', $marketplaceSku),
            new EqualsFilter(LengowOrderErrorDefinition::FIELD_TYPE, $type),
            new EqualsFilter(LengowOrderErrorDefinition::FIELD_IS_FINISHED, false),
        ]));
        /** @var LengowOrderErrorCollection $LengowOrderErrorCollection */
        $LengowOrderErrorCollection = $this->lengowOrderErrorRepository->search($criteria, $context)->getEntities();
        if ($LengowOrderErrorCollection->count() !== 0) {
            return $LengowOrderErrorCollection;
        }
        return null;
    }

    /**
     * Get all order errors
     *
     * @param string $lengowOrderId Lengow order id
     * @param int|null $type order error type (import or send)
     * @param bool|null $finished order error finished
     *
     * @return EntityCollection|null
     */
    public function getOrderErrors(string $lengowOrderId, int $type = null, bool $finished = null): ?EntityCollection
    {
        $context = Context::createDefaultContext();
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('order.id', $lengowOrderId));
        if ($type) {
            $criteria->addFilter(new EqualsFilter(LengowOrderErrorDefinition::FIELD_TYPE, $type));
        }
        if ($finished !== null) {
            $criteria->addFilter(new EqualsFilter(LengowOrderErrorDefinition::FIELD_IS_FINISHED, $finished));
        }
        /** @var LengowOrderErrorCollection $LengowOrderErrorCollection */
        $LengowOrderErrorCollection = $this->lengowOrderErrorRepository->search($criteria, $context)->getEntities();
        if ($LengowOrderErrorCollection->count() !== 0) {
            return $LengowOrderErrorCollection;
        }
        return null;
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
            new EqualsFilter(LengowOrderErrorDefinition::FIELD_TYPE, $type),
        ]));
        /** @var LengowOrderErrorCollection $LengowOrderErrorCollection */
        $LengowOrderErrorCollection = $this->lengowOrderErrorRepository->search($criteria, $context)->getEntities();
        foreach ($LengowOrderErrorCollection as $lengowOrderError) {
            $success = $this->update($lengowOrderError->getId(), [
                LengowOrderErrorDefinition::FIELD_IS_FINISHED => true,
            ]);
            if (!$success) {
                $result = false;
            }
        }
        return $result;
    }

    /**
     * Get order error not sent by email
     *
     * @return EntityCollection|null
     */
    public function getOrderErrorNotSent(): ?EntityCollection
    {
        $context = Context::createDefaultContext();
        $criteria = new Criteria();
        $criteria->addFilter(new MultiFilter(MultiFilter::CONNECTION_AND, [
            new EqualsFilter(LengowOrderErrorDefinition::FIELD_IS_FINISHED, false),
            new EqualsFilter(LengowOrderErrorDefinition::FIELD_MAIL, false),
        ]));
        $criteria->addAssociation('order');
        /** @var LengowOrderErrorCollection $LengowOrderErrorCollection */
        $LengowOrderErrorCollection = $this->lengowOrderErrorRepository->search($criteria, $context)->getEntities();
        if ($LengowOrderErrorCollection->count() !== 0) {
            return $LengowOrderErrorCollection;
        }
        return null;
    }
}
