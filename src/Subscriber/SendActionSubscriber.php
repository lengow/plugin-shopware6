<?php declare(strict_types=1);

namespace Lengow\Connector\Subscriber;

use Shopware\Core\Checkout\Order\Aggregate\OrderDelivery\OrderDeliveryStates;
use Shopware\Core\Checkout\Order\OrderEvents;
use Shopware\Core\Checkout\Order\OrderStates;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\System\StateMachine\Event\StateMachineTransitionEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Lengow\Connector\Service\LengowAction;
use Lengow\Connector\Service\LengowImport;
use Lengow\Connector\Service\LengowOrder;

/**
 * Class StateMachineStateUpdateSubscriber
 * @package Lengow\Connector\Subcriber
 */
class SendActionSubscriber implements EventSubscriberInterface
{
    /**
     * @var LengowAction Lengow action service
     */
    private $lengowAction;

    /**
     * @var EntityRepository Order state repository
     */
    private EntityRepository $orderStateRepository;

    /**
     * @var LengowOrder Lengow order service
     */
    private $lengowOrder;

    /**
     * SendActionSubscriber constructor
     * @param LengowAction $lengowAction Lengow action service
     * @param LengowOrder $lengowOrder Lengow order service
     */
    public function __construct(
        LengowAction $lengowAction,
        LengowOrder $lengowOrder,
        EntityRepository $orderStateRepository
    )
    {
        $this->lengowAction = $lengowAction;
        $this->lengowOrder = $lengowOrder;
        $this->orderStateRepository = $orderStateRepository;
    }

    /**
     * Get Subscribed events
     *
     * @return array
     */
    public static function getSubscribedEvents(): array
    {
        return [
            StateMachineTransitionEvent::class => 'detectOrderStateUpdate',
            OrderEvents::ORDER_DELIVERY_WRITTEN_EVENT => 'detectTrackingNumberUpdate',
        ];
    }

    /**
     * Detects when an action on Lengow orders should be sent
     *
     * @param StateMachineTransitionEvent $event Shopware state machine transition event
     * @throws \JsonException
     */
    public function detectOrderStateUpdate(StateMachineTransitionEvent $event): void
    {
        if ($event->getEntityName() === 'order') {
            $this->checkAndSendCancelAction($event->getEntityId());
        } elseif ($event->getEntityName() === 'order_delivery') {
            $this->checkAndSendShipAction($event->getEntityId());
        }
    }

    /**
     * Detects when a tracking number is added
     *
     * @param EntityWrittenEvent $event Shopware entity written event
     * @throws \JsonException
     */
    public function detectTrackingNumberUpdate(EntityWrittenEvent $event): void
    {
        $writeResult = $event->getWriteResults()[0];
        $payLoad = $writeResult->getPayload();
        if (isset($payLoad['id'], $payLoad['trackingCodes'])
            && !empty($payLoad['trackingCodes'])
            && $writeResult->getOperation() === 'update'
        ) {
            $this->checkAndSendShipAction($payLoad['id'], $payLoad['trackingCodes']);
        }
    }

    /**
     * Check and try to send ship action for a Shopware order
     *
     * @param string $orderDeliveryId Shopware order delivery id
     * @param array $trackingCodes Shopware tracking codes
     * @throws \JsonException
     */
    private function checkAndSendShipAction(string $orderDeliveryId, array $trackingCodes = []): void
    {
        $orderDelivery = $this->lengowOrder->getOrderDeliveryById($orderDeliveryId);
        if ($orderDelivery === null || !$this->actionCanBeSent($orderDelivery->getOrderId())) {
            return;
        }
        // send an action only when the tracking code is saved in database
        if (!empty($trackingCodes) && count($trackingCodes) !== count($orderDelivery->getTrackingCodes())) {
            return;
        }
        $order = $this->lengowOrder->getOrderById($orderDelivery->getOrderId());
        $stateId = $orderDelivery->getStateId();
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('id', $stateId));
        $context = Context::createDefaultContext();
        $orderState = $this->orderStateRepository->search($criteria, $context)->first();
        $orderStateName = $orderState->getTechnicalName();

        if ($order
            && $orderStateName
            && $orderStateName === OrderDeliveryStates::STATE_SHIPPED
        ) {
            $this->lengowOrder->callAction(LengowAction::TYPE_SHIP, $order, $orderDelivery);
        }
    }

    /**
     * Check and try to send cancel action for a Shopware order
     *
     * @param string $orderId Shopware order id
     */
    private function checkAndSendCancelAction(string $orderId): void
    {
        if (!$this->actionCanBeSent($orderId)) {
            return;
        }
        $order = $this->lengowOrder->getOrderById($orderId);
        $currentOrderState = $order ? $order->getStateMachineState() : null;
        if ($currentOrderState && $currentOrderState->getTechnicalName() === OrderStates::STATE_CANCELLED) {
            $this->lengowOrder->callAction(LengowAction::TYPE_CANCEL, $order);
        }
    }

    /**
     * Check if Lengow action can be sent for a Shopware order
     *
     * @param string $orderId Shopware order id
     *
     * @return bool
     */
    private function actionCanBeSent(string $orderId): bool
    {
        $lengowOrder = $this->lengowOrder->getLengowOrderByOrderId($orderId);
        $activeActions = $this->lengowAction->getActionsByOrderId($orderId, true);
        // if a Lengow order and order is not closed, action can be sent
        return $lengowOrder
            && $lengowOrder->getOrderProcessState() !== LengowOrder::PROCESS_STATE_FINISH
            && $lengowOrder->getMarketplaceSku() !== LengowImport::$currentOrder
            && $activeActions === null;
    }
}
