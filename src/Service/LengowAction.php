<?php declare(strict_types=1);

namespace Lengow\Connector\Service;

use \Exception;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\MultiFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\RangeFilter;
use Shopware\Core\Framework\Uuid\Uuid;
use Lengow\Connector\Entity\Lengow\Action\ActionCollection as LengowActionCollection;
use Lengow\Connector\Entity\Lengow\Action\ActionEntity as LengowActionEntity;
use Lengow\Connector\Entity\Lengow\Order\OrderEntity as LengowOrderEntity;
use Lengow\Connector\Exception\LengowException;

/**
 * Class LengowAction
 * @package Lengow\Connector\Service
 */
class LengowAction
{
    /**
     * @var integer action state for new action
     */
    public const STATE_NEW = 0;

    /**
     * @var integer action state for action finished
     */
    public const STATE_FINISH = 1;

    /**
     * @var string action type ship
     */
    public const TYPE_SHIP = 'ship';

    /**
     * @var string action type cancel
     */
    public const TYPE_CANCEL = 'cancel';

    /**
     * @var string action argument action type
     */
    public const ARG_ACTION_TYPE = 'action_type';

    /**
     * @var string action argument line
     */
    public const ARG_LINE = 'line';

    /**
     * @var string action argument carrier
     */
    public const ARG_CARRIER = 'carrier';

    /**
     * @var string action argument carrier name
     */
    public const ARG_CARRIER_NAME = 'carrier_name';

    /**
     * @var string action argument custom carrier
     */
    public const ARG_CUSTOM_CARRIER = 'custom_carrier';

    /**
     * @var string action argument shipping method
     */
    public const ARG_SHIPPING_METHOD = 'shipping_method';

    /**
     * @var string action argument tracking number
     */
    public const ARG_TRACKING_NUMBER = 'tracking_number';

    /**
     * @var string action argument tracking url
     */
    public const ARG_TRACKING_URL = 'tracking_url';

    /**
     * @var string action argument shipping price
     */
    public const ARG_SHIPPING_PRICE = 'shipping_price';

    /**
     * @var string action argument shipping date
     */
    public const ARG_SHIPPING_DATE = 'shipping_date';

    /**
     * @var string action argument delivery date
     */
    public const ARG_DELIVERY_DATE = 'delivery_date';

    /**
     * @var array all valid actions
     */
    public static $validActions = [
        self::TYPE_SHIP,
        self::TYPE_CANCEL,
    ];

    /**
     * @var array Parameters to delete for Get call
     */
    public static $getParamsToDelete = [
        self::ARG_SHIPPING_DATE,
        self::ARG_DELIVERY_DATE,
    ];

    /**
     * @var EntityRepositoryInterface $lengowActionRepository Lengow action repository
     */
    private $lengowActionRepository;

    /**
     * @var LengowLog $lengowLog Lengow log service
     */
    private $lengowLog;

    /**
     * @var LengowConnector $lengowConnector Lengow connector service
     */
    private $lengowConnector;

    /**
     * @var LengowConfiguration $lengowConfiguration Lengow configuration service
     */
    private $lengowConfiguration;

    /**
     * @var array $fieldList field list for the table lengow_action
     * required => Required fields when creating registration
     * updated  => Fields allowed when updating registration
     */
    private $fieldList = [
        'orderId' => ['required' => true, 'updated' => false],
        'actionId' => ['required' => true, 'updated' => false],
        'orderLineSku' => ['required' => false, 'updated' => false],
        'actionType' => ['required' => true, 'updated' => false],
        'retry' => ['required' => false, 'updated' => true],
        'parameters' => ['required' => true, 'updated' => false],
        'state' => ['required' => false, 'updated' => true],
    ];

    /**
     * LengowAction constructor
     *
     * @param EntityRepositoryInterface $lengowActionRepository Lengow action repository
     * @param LengowLog $lengowLog Lengow log service
     * @param LengowConnector $lengowConnector Lengow connector service
     * @param LengowConfiguration $lengowConfiguration Lengow configuration service
     */
    public function __construct(
        EntityRepositoryInterface $lengowActionRepository,
        LengowLog $lengowLog,
        LengowConnector $lengowConnector,
        LengowConfiguration $lengowConfiguration
    )
    {
        $this->lengowActionRepository = $lengowActionRepository;
        $this->lengowLog = $lengowLog;
        $this->lengowConnector = $lengowConnector;
        $this->lengowConfiguration = $lengowConfiguration;
    }

    /**
     * Create an action
     *
     * @param array $data All data for order line creation
     *
     * @return bool
     */
    public function create(array $data): bool
    {
        $data = array_merge($data, ['id' => Uuid::randomHex()]);
        if (empty($data['state'])) {
            $data['state'] = self::STATE_NEW;
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
            $this->lengowActionRepository->create([$data], Context::createDefaultContext());
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
     * Update an action
     *
     * @param string $actionId Lengow action id
     * @param array $data additional parameters for update
     *
     * @return bool
     */
    public function update(string $actionId, array $data = []): bool
    {
        // update only authorized values
        foreach ($this->fieldList as $key => $value) {
            if (array_key_exists($key, $data) && !$value['updated']) {
                unset($data[$key]);
            }
        }
        $data = array_merge($data, ['id' => $actionId]);
        try {
            $this->lengowActionRepository->update([$data], Context::createDefaultContext());
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
     * Get lengow action from lengow action table by Lengow action id from API
     *
     * @param int $apiActionId Lengow action id from API
     *
     * @return LengowActionEntity|null
     */
    public function getActionByApiActionId(int $apiActionId): ?LengowActionEntity
    {
        $context = Context::createDefaultContext();
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('actionId', $apiActionId));
        /** @var LengowActionCollection $lengowActionCollection */
        $lengowActionCollection = $this->lengowActionRepository->search($criteria, $context)->getEntities();
        return $lengowActionCollection->count() !== 0 ? $lengowActionCollection->first() : null;
    }

    /**
     * Get all active actions
     *
     * @return EntityCollection|null
     */
    public function getActiveActions(): ?EntityCollection
    {
        $context = Context::createDefaultContext();
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('state', self::STATE_NEW));
        /** @var LengowActionCollection $lengowActionCollection */
        $lengowActionCollection = $this->lengowActionRepository->search($criteria, $context)->getEntities();
        return $lengowActionCollection->count() !== 0 ? $lengowActionCollection : null;
    }

    /**
     * Get all active actions for a Shopware order
     *
     * @param string $orderId Shopware order id
     *
     * @return EntityCollection|null
     */
    public function getActiveActionByOrderId(string $orderId): ?EntityCollection
    {
        $context = Context::createDefaultContext();
        $criteria = new Criteria();
        $criteria->addFilter(new MultiFilter(MultiFilter::CONNECTION_AND, [
            new EqualsFilter('order.id', $orderId),
            new EqualsFilter('state', self::STATE_NEW),
        ]));
        /** @var LengowActionCollection $lengowActionCollection */
        $lengowActionCollection = $this->lengowActionRepository->search($criteria, $context)->getEntities();
        return $lengowActionCollection->count() !== 0 ? $lengowActionCollection : null;
    }

    /**
     * Get old untreated actions of more than x days
     *
     * @param int $intervalTime interval time in seconds
     *
     * @return EntityCollection|null
     */
    public function getOldActions(int $intervalTime): ?EntityCollection
    {
        $context = Context::createDefaultContext();
        $criteria = new Criteria();
        $criteria->addFilter(new MultiFilter(MultiFilter::CONNECTION_AND, [
            new RangeFilter('createdAt', [
                RangeFilter::LT => date('Y-m-d h:m:i', (time() - $intervalTime)),
            ]),
            new EqualsFilter('state', self::STATE_NEW),
        ]));
        /** @var LengowActionCollection $lengowActionCollection */
        $lengowActionCollection = $this->lengowActionRepository->search($criteria, $context)->getEntities();
        return $lengowActionCollection->count() !== 0 ? $lengowActionCollection : null;
    }

    /**
     * Get last order action type to re-send action
     *
     * @param string $orderId Shopware order id
     *
     * @return string|null
     */
    public function getLastOrderActionType(string $orderId): ?string
    {
        $context = Context::createDefaultContext();
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('order.id', $orderId));
        /** @var LengowActionCollection $lengowActionCollection */
        $lengowActionCollection = $this->lengowActionRepository->search($criteria, $context)->getEntities();
        if ($lengowActionCollection->count() !== 0) {
            /** @var LengowActionEntity $lengowAction */
            $lengowAction = $lengowActionCollection->last();
            return $lengowAction->getActionType();
        }
        return null;
    }

    /**
     * Indicates whether an action can be created if it does not already exist
     *
     * @param array $params all available values
     * @param OrderEntity $order Shopware order instance
     *
     * @throws Exception|LengowException
     *
     * @return bool
     */
    public function canSendAction(array $params, OrderEntity $order): bool
    {
        $sendAction = true;
        // check if action is already created
        $getParams = array_merge($params, ['queued' => 'True']);
        // array key deletion for GET verification
        foreach (self::$getParamsToDelete as $paramToDelete) {
            if (isset($getParams[$paramToDelete])) {
                unset($getParams[$paramToDelete]);
            }
        }
        $result = $this->lengowConnector->queryApi(LengowConnector::GET, LengowConnector::API_ORDER_ACTION, $getParams);
        if (isset($result->error, $result->error->message)) {
            throw new LengowException($result->error->message);
        }
        if (!isset($result->count) || (int)$result->count === 0) {
            return $sendAction;
        }
        foreach ($result->results as $row) {
            $orderAction = $this->getActionByApiActionId((int) $row->id);
            if ($orderAction) {
                if ($orderAction->getState() === self::STATE_NEW) {
                    $this->update($orderAction->getId(), [
                        'retry' => $orderAction->getRetry() + 1,
                    ]);
                    $sendAction = false;
                }
            } else {
                // if update doesn't work, create new action
                $success = $this->create([
                    'orderId' => $order->getId(),
                    'actionType' => $params[self::ARG_ACTION_TYPE],
                    'actionId' => $row->id,
                    'orderLineSku' => (string) ($params[self::ARG_LINE] ?? ''),
                    'parameters' => $params,
                ]);
                if ($success) {
                    $this->lengowLog->write(
                        LengowLog::CODE_ACTION,
                        $this->lengowLog->encodeMessage('log.order_action.action_saved'),
                        false,
                        $params['marketplace_order_id']
                    );
                }
                $sendAction = false;
            }
        }
        return $sendAction;
    }

    /**
     * Send a new action on the order via the Lengow API
     *
     * @param array $params all available values
     * @param OrderEntity $order Magento order instance
     * @param LengowOrderEntity $lengowOrder Lengow order instance
     *
     * @throws LengowException
     */
    public function sendAction(array $params, OrderEntity $order, LengowOrderEntity $lengowOrder): void
    {
        if (!$this->lengowConfiguration->debugModeIsActive()) {
            $result = $this->lengowConnector->queryApi(
                LengowConnector::POST,
                LengowConnector::API_ORDER_ACTION,
                $params
            );
            if (isset($result->id)) {
                $success = $this->create([
                    'orderId' => $order->getId(),
                    'actionType' => $params[self::ARG_ACTION_TYPE],
                    'actionId' => $result->id,
                    'orderLineSku' => (string) ($params[self::ARG_LINE] ?? ''),
                    'parameters' => $params,
                ]);
                if ($success) {
                    $this->lengowLog->write(
                        LengowLog::CODE_ACTION,
                        $this->lengowLog->encodeMessage('log.order_action.action_saved'),
                        false,
                        $params['marketplace_order_id']
                    );
                }
                unset($orderAction);
            } else {
                if ($result) {
                    $message = $this->lengowLog->encodeMessage('lengow_log.exception.action_not_created', [
                        'error_message' => json_encode($result),
                    ]);
                } else {
                    // generating a generic error message when the Lengow API is unavailable
                    $message = $this->lengowLog->encodeMessage('lengow_log.exception.action_not_created_api');
                }
                throw new LengowException($message);
            }
        }
        // create log for call action
        $paramList = false;
        foreach ($params as $param => $value) {
            $paramList .= !$paramList ? '"' . $param . '": ' . $value : ' -- "' . $param . '": ' . $value;
        }
        $this->lengowLog->write(
            LengowLog::CODE_ACTION,
            $this->lengowLog->encodeMessage('log.order_action.call_tracking', [
                'parameters' => $paramList,
            ]),
            false,
            $lengowOrder->getMarketplaceSku()
        );
    }

    /**
     * Finish all order action for one Shopware order
     *
     * @param string $orderId Shopware order id
     * @param string|null $actionType action type (ship or cancel)
     *
     * @return bool
     */
    public function finishActions(string $orderId, string $actionType = null): bool
    {
        $result = true;
        $context = Context::createDefaultContext();
        $criteria = new Criteria();
        $criteria->addFilter(new MultiFilter(MultiFilter::CONNECTION_AND, [
            new EqualsFilter('order.id', $orderId),
            new EqualsFilter('state', self::STATE_NEW),
        ]));
        if ($actionType) {
            $criteria->addFilter(new EqualsFilter('actionType', $actionType));
        }
        /** @var LengowActionCollection $lengowActionCollection */
        $lengowActionCollection = $this->lengowActionRepository->search($criteria, $context)->getEntities();
        foreach ($lengowActionCollection as $lengowAction) {
            $success = $this->update($lengowAction->getId(), [
                'state' => self::STATE_FINISH,
            ]);
            if (!$success) {
                $result = false;
            }
        }
        return $result;
    }
}
