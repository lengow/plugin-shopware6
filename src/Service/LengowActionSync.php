<?php declare(strict_types=1);

namespace Lengow\Connector\Service;

use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Checkout\Order\OrderStates;
use Lengow\Connector\Entity\Lengow\Action\ActionEntity as LengowActionEntity;
use Lengow\Connector\Entity\Lengow\Order\OrderEntity as LengowOrderEntity;

/**
 * Class LengowActionSync
 * @package Lengow\Connector\Service
 */
class LengowActionSync
{
    /**
     * @var int max interval time for action synchronisation (3 days)
     */
    private const MAX_INTERVAL_TIME = 259200;

    /**
     * @var int security interval time for action synchronisation (2 hours)
     */
    private const SECURITY_INTERVAL_TIME = 7200;

    /**
     * @var LengowAction $lengowAction Lengow action service
     */
    private $lengowAction;

    /**
     * @var LengowConfiguration $lengowConfiguration Lengow configuration service
     */
    private $lengowConfiguration;

    /**
     * @var LengowConnector $lengowConnector Lengow connector service
     */
    private $lengowConnector;

    /**
     * @var LengowLog $lengowLog Lengow log service
     */
    private $lengowLog;

    /**
     * @var LengowOrder $lengowOrder Lengow order service
     */
    private $lengowOrder;

    /**
     * @var LengowOrderError $lengowOrderError Lengow order error service
     */
    private $lengowOrderError;

    /**
     * LengowActionSync constructor
     *
     * @param LengowAction $lengowAction Lengow action service
     * @param LengowConfiguration $lengowConfiguration Lengow configuration service
     * @param LengowConnector $lengowConnector Lengow connector service
     * @param LengowLog $lengowLog Lengow log service
     * @param LengowOrder $lengowOrder Lengow order service
     * @param LengowOrderError $lengowOrderError Lengow order error service
     */
    public function __construct(
        LengowAction $lengowAction,
        LengowConfiguration $lengowConfiguration,
        LengowConnector $lengowConnector,
        LengowLog $lengowLog,
        LengowOrder $lengowOrder,
        LengowOrderError $lengowOrderError
    )
    {
        $this->lengowAction = $lengowAction;
        $this->lengowConfiguration = $lengowConfiguration;
        $this->lengowConnector = $lengowConnector;
        $this->lengowLog = $lengowLog;
        $this->lengowOrder = $lengowOrder;
        $this->lengowOrderError = $lengowOrderError;
    }

    /**
     * Check if active actions are finished
     *
     * @param bool $logOutput see log or not
     */
    public function checkFinishAction(bool $logOutput = false): void
    {
        if ($this->lengowConfiguration->debugModeIsActive()) {
            return;
        }
        $this->lengowLog->write(
            LengowLog::CODE_ACTION,
            $this->lengowLog->encodeMessage('log.order_action.check_completed_action'),
            $logOutput
        );
        // get all active actions
        $activeActions = $this->lengowAction->getActiveActions();
        // if no active action, do nothing
        if ($activeActions === null) {
            return;
        }
        // get all actions with API (max 3 days)
        $apiActions = $this->getActionsFromApi($logOutput);
        if (empty($apiActions)) {
            return;
        }
        /** @var LengowActionEntity $lengowAction */
        foreach ($activeActions as $lengowAction) {
            // check foreach action if is complete
            if (isset($apiActions[$lengowAction->getActionId()])) {
                $this->checkAndUpdateAction($lengowAction, $apiActions[$lengowAction->getActionId()], $logOutput);
            }
        }
        $this->lengowConfiguration->set(LengowConfiguration::LENGOW_LAST_ACTION_SYNC, (string) time());
    }

    /**
     * Check old actions > 3 days and create an order error if necessary
     *
     * @param bool $logOutput see log or not
     */
    public function checkOldAction(bool $logOutput = false): void
    {
        if ($this->lengowConfiguration->debugModeIsActive()) {
            return;
        }
        $this->lengowLog->write(
            LengowLog::CODE_ACTION,
            $this->lengowLog->encodeMessage('log.order_action.check_old_action'),
            $logOutput
        );
        // get all old order action (+ 3 days)
        $oldActions = $this->lengowAction->getOldActions(self::MAX_INTERVAL_TIME);
        if ($oldActions === null) {
            return;
        }
        foreach ($oldActions as $lengowAction) {
            $this->lengowAction->update($lengowAction->getId(), [
                'state' => LengowAction::STATE_FINISH,
            ]);
            $lengowOrder = $this->lengowOrder->getLengowOrderByOrderId($lengowAction->getOrder()->getId());
            if ($lengowOrder === null) {
                continue;
            }
            if ($lengowOrder->getOrderProcessState() !== LengowOrder::PROCESS_STATE_FINISH
                && !$lengowOrder->isInError()
            ) {
                $errorMessage = $this->lengowLog->encodeMessage('lengow_log.exception.action_is_too_old');
                $this->setActionInError($lengowOrder, $errorMessage, $logOutput);
            }
        }
    }

    /**
     * Check if actions are not sent
     *
     * @param bool $logOutput see log or not
     */
    public function checkNotSentAction(bool $logOutput = false): void
    {
        if ($this->lengowConfiguration->debugModeIsActive()) {
            return;
        }
        $this->lengowLog->write(
            LengowLog::CODE_ACTION,
            $this->lengowLog->encodeMessage('log.order_action.check_action_not_sent'),
            $logOutput
        );
        /** @var OrderEntity[] $unsentOrders */
        $unsentOrders = $this->lengowOrder->getUnsentOrders();
        if (empty($unsentOrders)) {
            return;
        }
        foreach ($unsentOrders as $order) {
            $activeAction = $this->lengowAction->getActiveActionByOrderId($order->getId());
            if ($activeAction) {
                continue;
            }
            if ($order->getStateMachineState()
                && $order->getStateMachineState()->getTechnicalName() === OrderStates::STATE_CANCELLED
            ) {
                $action = LengowAction::TYPE_CANCEL;
            } else {
                $action = LengowAction::TYPE_SHIP;
            }
            $orderDelivery = $action === LengowAction::TYPE_SHIP && $order->getDeliveries()
                ? $order->getDeliveries()->first()
                : null;
            $this->lengowOrder->callAction($action, $order, $orderDelivery);
        }
    }

    /**
     * Get all actions with Lengow Api
     *
     * @param bool $logOutput see log or not
     *
     * @return array
     */
    private function getActionsFromApi(bool $logOutput = false): array
    {
        $page = 1;
        $apiActions = [];
        $intervalTime = $this->getIntervalTime();
        $dateToTimestamp = time();
        $dateFromTimestamp = $dateToTimestamp - $intervalTime;
        $this->lengowLog->write(
            LengowLog::CODE_ACTION,
            $this->lengowLog->encodeMessage('log.order_action.connector_get_all_action', [
                'date_from' => $this->lengowConfiguration->date($dateFromTimestamp),
                'date_to' => $this->lengowConfiguration->date($dateToTimestamp),
            ]),
            $logOutput
        );
        do {
            $results = $this->lengowConnector->queryApi(
                LengowConnector::GET,
                LengowConnector::API_ORDER_ACTION,
                [
                    'updated_from' => $this->lengowConfiguration->date(
                        $dateFromTimestamp,
                        LengowConfiguration::API_DATE_TIME_FORMAT
                    ),
                    'updated_to' => $this->lengowConfiguration->date(
                        $dateToTimestamp,
                        LengowConfiguration::API_DATE_TIME_FORMAT
                    ),
                    'page' => $page,
                ],
                '',
                $logOutput
            );
            if (!is_object($results) || isset($results->error)) {
                break;
            }
            // construct array actions
            foreach ($results->results as $action) {
                if (isset($action->id)) {
                    $apiActions[$action->id] = $action;
                }
            }
            $page++;
        } while ($results->next !== null);
        return $apiActions;
    }

    /**
     * Check the action and updates data if necessary
     *
     * @param LengowActionEntity $lengowAction Lengow action entity
     * @param Object $apiAction Action data from api
     * @param bool $logOutput see log or not
     */
    private function checkAndUpdateAction(
        LengowActionEntity $lengowAction,
        Object $apiAction,
        bool $logOutput = false
    ): void
    {
        if (!isset($apiAction->queued, $apiAction->processed, $apiAction->errors) || $apiAction->queued !== false) {
            return;
        }
        // order action is waiting to return from the marketplace
        if ($apiAction->processed === false && empty($apiAction->errors)) {
            return;
        }
        // finish action in lengow_action table
        $this->lengowAction->update($lengowAction->getId(), [
            'state' => LengowAction::STATE_FINISH,
        ]);
        $lengowOrder = $this->lengowOrder->getLengowOrderByOrderId($lengowAction->getOrder()->getId());
        if ($lengowOrder === null) {
            return;
        }
        // finish old order errors before create a new error
        $this->lengowOrderError->finishOrderErrors($lengowOrder->getId(), LengowOrderError::TYPE_ERROR_SEND);
        if ($lengowOrder->isInError()) {
            $this->lengowOrder->update($lengowOrder->getId(), [
                'isInError' => false,
            ]);
        }
        if ($lengowOrder->getOrderProcessState() === LengowOrder::PROCESS_STATE_FINISH) {
            return;
        }
        // if action is accepted -> close order and finish all order actions
        if ($apiAction->processed === true && empty($apiAction->errors)) {
            $this->lengowOrder->update($lengowOrder->getId(), [
                'orderProcessState' => LengowOrder::PROCESS_STATE_FINISH,
            ]);
            $this->lengowAction->finishActions($lengowAction->getOrder()->getId());
        } else {
            // if action is denied -> create order error
            $this->setActionInError($lengowOrder, $apiAction->errors, $logOutput);
        }
    }

    /**
     * Create an action order error and pass the order in error
     *
     * @param LengowOrderEntity $lengowOrder Lengow order entity
     * @param string $errorMessage error message
     * @param bool $logOutput see log or not
     */
    private function setActionInError(
        LengowOrderEntity $lengowOrder,
        string $errorMessage,
        bool $logOutput = false
    ): void
    {
        $this->lengowOrder->update($lengowOrder->getId(), [
            'isInError' => true,
        ]);
        $this->lengowOrderError->create($lengowOrder->getId(), $errorMessage, LengowOrderError::TYPE_ERROR_SEND);
        $decodedMessage = $this->lengowLog->decodeMessage($errorMessage, LengowTranslation::DEFAULT_ISO_CODE);
        $this->lengowLog->write(
            LengowLog::CODE_ACTION,
            $this->lengowLog->encodeMessage('log.order_action.call_action_failed', [
                'decoded_message' => $decodedMessage,
            ]),
            $logOutput,
            $lengowOrder->getMarketplaceSku()
        );
    }

    /**
     * Get interval time for action synchronisation
     *
     * @return int
     */
    private function getIntervalTime(): int
    {
        $intervalTime = self::MAX_INTERVAL_TIME;
        $lastActionSynchronisation = $this->lengowConfiguration->get(LengowConfiguration::LENGOW_LAST_ACTION_SYNC);
        if ($lastActionSynchronisation) {
            $lastIntervalTime = time() - (int)$lastActionSynchronisation;
            $lastIntervalTime += self::SECURITY_INTERVAL_TIME;
            $intervalTime = $lastIntervalTime > $intervalTime ? $intervalTime : $lastIntervalTime;
        }
        return $intervalTime;
    }
}
