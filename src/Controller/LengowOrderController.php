<?php declare(strict_types=1);

namespace Lengow\Connector\Controller;

use Shopware\Core\Checkout\Order\OrderStates;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\JsonResponse;
use Lengow\Connector\Entity\Lengow\Order\OrderDefinition as LengowOrderDefinition;
use Lengow\Connector\Entity\Lengow\OrderError\OrderErrorEntity as LengowOrderErrorEntity;
use Lengow\Connector\Service\LengowAction;
use Lengow\Connector\Service\LengowImport;
use Lengow\Connector\Service\LengowImportOrder;
use Lengow\Connector\Service\LengowLog;
use Lengow\Connector\Service\LengowOrder;
use Lengow\Connector\Service\LengowOrderError;

/**
 * Class LengowOrderController
 * @package Lengow\Connector\Controller
 * @Route(defaults={"_routeScope"={"api"}})
 */
class LengowOrderController extends AbstractController
{
    /**
     * @var LengowImport Lengow import service
     */
    private $lengowImport;

    /**
     * @var LengowLog Lengow log service
     */
    private $lengowLog;

    /**
     * @var LengowOrder Lengow order service
     */
    private $lengowOrder;

    /**
     * @var LengowOrderError Lengow order error service
     */
    private $lengowOrderError;

    /**
     * @var LengowAction Lengow action service
     */
    private $lengowAction;

    /**
     * LengowOrderController constructor
     *
     * @param LengowImport $lengowImport Lengow import service
     * @param LengowLog $lengowLog Lengow log service
     * @param LengowOrder $lengowOrder Lengow order service
     * @param LengowOrderError $lengowOrderError Lengow order error service
     * @param LengowAction $lengowAction Lengow action service
     */
    public function __construct(
        LengowImport $lengowImport,
        LengowLog $lengowLog,
        LengowOrder $lengowOrder,
        LengowOrderError $lengowOrderError,
        LengowAction $lengowAction
    )
    {
        $this->lengowImport = $lengowImport;
        $this->lengowLog = $lengowLog;
        $this->lengowOrder = $lengowOrder;
        $this->lengowOrderError = $lengowOrderError;
        $this->lengowAction = $lengowAction;
    }

    /**
     * Synchronise all orders
     *
     * @Route("/api/_action/lengow/order/synchronise-orders",
     *     name="api.action.lengow.order.synchronise-order",
     *     methods={"GET"})
     * @Route("/api/v{version}/_action/lengow/order/synchronise-orders",
     *     name="api.action.lengow.order.synchronise-order-old",
     *     methods={"GET"})
     *
     * @return JsonResponse
     */
    public function synchroniseOrders(): JsonResponse
    {
        $this->lengowImport->init();
        $result = $this->lengowImport->exec();
        $messages = $this->loadMessages($result);
        return new JsonResponse($messages);
    }

    /**
     * re-synchronise specific order
     *
     * @Route("/api/_action/lengow/order/re-synchronise-order",
     *     name="api.action.lengow.order.re-synchronise-order",
     *     methods={"POST"})
     * @Route("/api/v{version}/_action/lengow/order/re-synchronise-order",
     *     name="api.action.lengow.order.re-synchronise-order-old",
     *     methods={"POST"})
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function reSynchroniseOrder(Request $request): JsonResponse
    {
        $orderId = $request->get('orderId');
        $order = $this->lengowOrder->getOrderById($orderId);
        $result = false;
        if ($order) {
            $result = $this->lengowOrder->synchronizeOrder($order);
            $messageKey = $result
                ? 'log.import.order_synchronized_with_lengow'
                : 'log.import.order_not_synchronized_with_lengow';
            $this->lengowLog->write(
                LengowLog::CODE_IMPORT,
                $this->lengowLog->encodeMessage($messageKey, [
                    'order_id' => $order->getOrderNumber(),
                ]),
                false
            );
        }
        return new JsonResponse([
            'success' => $result,
        ]);
    }

    /**
     * Re-import a specific order
     *
     * @Route("/api/_action/lengow/order/reimport-order",
     *     defaults={"auth_enabled"=true},
     *     name="api.action.lengow.order.reimport-order",
     *     methods={"POST"})
     * @Route("/api/v{version}/_action/lengow/order/reimport-order",
     *     defaults={"auth_enabled"=true},
     *     name="api.action.lengow.order.reimport-order-old",
     *     methods={"POST"})
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function reImportOrder(Request $request): JsonResponse
    {
        $result = false;
        $lengowOrderId = $request->get('lengowOrderId');
        if ($lengowOrderId) {
            $result = $this->loadAndImportOrder($lengowOrderId);
        }
        return new JsonResponse([
            'success' => $result,
        ]);
    }

    /**
     * Re-import a specific failed order
     *
     * @Route("/api/_action/lengow/order/reimport-failed-order",
     *     defaults={"auth_enabled"=true},
     *     name="api.action.lengow.order.reimport-failed-order",
     *     methods={"POST"})
     * @Route("/api/v{version}/_action/lengow/order/reimport-failed-order",
     *     defaults={"auth_enabled"=true},
     *     name="api.action.lengow.order.reimport-failed-order-old",
     *     methods={"POST"})
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function reImportFailedOrder(Request $request): JsonResponse
    {
        $success = false;
        $newOrderId = '';
        $lengowOrderId = $request->get('lengowOrderId');
        $orderId = $request->get('orderId');
        $lengowOrder = $lengowOrderId ? $this->lengowOrder->getLengowOrderById($lengowOrderId) : null;
        $order = $orderId ? $this->lengowOrder->getOrderById($orderId) : null;
        if ($lengowOrder && !$this->lengowOrder->setAsReImported($lengowOrder)) {
            $lengowOrder = null;
        }
        if ($lengowOrder && $order) {
            $this->lengowImport->init([
                LengowImport::PARAM_TYPE => LengowImport::TYPE_MANUAL,
                LengowImport::PARAM_MARKETPLACE_SKU => $lengowOrder->getMarketplaceSku(),
                LengowImport::PARAM_MARKETPLACE_NAME => $lengowOrder->getMarketplaceName(),
                LengowImport::PARAM_DELIVERY_ADDRESS_ID => $lengowOrder->getDeliveryAddressId(),
                LengowImport::PARAM_SALES_CHANNEL_ID => $lengowOrder->getSalesChannel()->getId(),
            ]);
            $result = $this->lengowImport->exec();
            if (!empty($result[LengowImport::ORDERS_CREATED])) {
                $orderCreated = $result[LengowImport::ORDERS_CREATED][0];
                if ($orderCreated[LengowImportOrder::MERCHANT_ORDER_ID] !== $orderId) {
                    $this->lengowOrder->putOrderInLengowTechnicalErrorState($order);
                    $newOrderId = $orderCreated[LengowImportOrder::MERCHANT_ORDER_ID];
                    $success = true;
                }
            } else {
                // in the event of an error, all new order errors are finished and the order is reset
                $this->lengowOrderError->finishOrderErrors($lengowOrder->getId());
                // update Lengow order with new data
                $this->lengowOrder->update($lengowOrder->getId(), [
                    LengowOrderDefinition::FIELD_IS_IN_ERROR => false,
                    LengowOrderDefinition::FIELD_IS_REIMPORTED => false,
                ]);
            }
        }
        return new JsonResponse([
            'success' => $success,
            'new_order_id' => $newOrderId,
        ]);
    }

    /**
     * Re-send an action for an order
     *
     * @Route("/api/_action/lengow/order/resend-action",
     *     defaults={"auth_enabled"=true},
     *     name="api.action.lengow.order.resend-action",
     *     methods={"POST"})
     * @Route("/api/v{version}/_action/lengow/order/resend-action",
     *     defaults={"auth_enabled"=true},
     *     name="api.action.lengow.order.resend-action-old",
     *     methods={"POST"})
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function reSendAction(Request $request): JsonResponse
    {
        $result = false;
        $lengowOrderId = $request->get('lengowOrderId');
        if ($lengowOrderId) {
            $result = $this->loadAndResendAction($lengowOrderId);
        }
        return new JsonResponse([
            'success' => $result,
        ]);
    }

    /**
     * Re-import a list of orders
     *
     * @Route("/api/_action/lengow/order/mass-reimport-orders",
     *     defaults={"auth_enabled"=true},
     *     name="api.action.lengow.order.mass-reimport-orders",
     *     methods={"POST"})
     * @Route("/api/v{version}/_action/lengow/order/mass-reimport-orders",
     *     defaults={"auth_enabled"=true},
     *     name="api.action.lengow.order.mass-reimport-orders-old",
     *     methods={"POST"})
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function massReImportOrders(Request $request): JsonResponse
    {
        $orderNew = 0;
        $orderError = 0;
        $lengowOrderIds = $request->get('lengowOrderIds');
        if (!empty($lengowOrderIds)) {
            foreach ($lengowOrderIds as $lengowOrderId) {
                $this->loadAndImportOrder($lengowOrderId) ? $orderNew++ : $orderError++;
            }
        }
        $messages = $this->loadMessages([
            'order_new' => $orderNew,
            'order_error' => $orderError,
        ]);
        return new JsonResponse($messages);
    }

    /**
     * Re-send a list of actions
     *
     * @Route("/api/_action/lengow/order/mass-resend-actions",
     *     defaults={"auth_enabled"=true},
     *     name="api.action.lengow.order.mass-resend-actions",
     *     methods={"POST"})
     *  @Route("/api/v{version}/_action/lengow/order/mass-resend-actions",
     *     defaults={"auth_enabled"=true},
     *     name="api.action.lengow.order.mass-resend-actions-old",
     *     methods={"POST"})
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function massReSendActions(Request $request): JsonResponse
    {
        $actionSuccess = 0;
        $actionError = 0;
        $lengowOrderIds = $request->get('lengowOrderIds');
        if (!empty($lengowOrderIds)) {
            foreach ($lengowOrderIds as $lengowOrderId) {
                $this->loadAndResendAction($lengowOrderId) ? $actionSuccess++ : $actionError++;
            }
        }
        $results = [
            'action_success' => $actionSuccess,
            'action_error' => $actionError,
        ];
        $messages = $this->loadMessages($results, true);
        return new JsonResponse($messages);
    }

    /**
     * Get all available marketplaces for filter
     *
     * @Route("/api/_action/lengow/order/get-available-marketplaces",
     *     name="api.action.lengow.order.get-available-marketplaces",
     *     methods={"GET"})
     * @Route("/api/v{version}/_action/lengow/order/get-available-marketplaces",
     *     name="api.action.lengow.order.get-available-marketplaces-old",
     *     methods={"GET"})
     *
     * @return JsonResponse
     */
    public function getAvailableMarketplaces(): JsonResponse
    {
        $availableMarketplaces = [];
        $marketplaceList = $this->lengowOrder->getMarketplaceList();
        foreach ($marketplaceList as $marketplaceName => $marketplaceLabel) {
            $availableMarketplaces[] = [
                'label' => $marketplaceLabel,
                'value' => $marketplaceName,
            ];
        }
        return new JsonResponse($availableMarketplaces);
    }

    /**
     * Get all order error messages
     *
     * @Route("/api/_action/lengow/order/get-order-errors",
     *     defaults={"auth_enabled"=true},
     *     name="api.action.lengow.order.get-order-errors",
     *     methods={"POST"})
     * @Route("/api/v{version}/_action/lengow/order/get-order-errors",
     *     defaults={"auth_enabled"=true},
     *     name="api.action.lengow.order.get-order-errors-old",
     *     methods={"POST"})
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function getOrderErrors(Request $request): JsonResponse
    {
        $orderErrorMessages = [];
        $lengowOrderId = $request->get('lengowOrderId');
        $orderErrorType = (int) $request->get('orderErrorType');
        if ($lengowOrderId) {
            /** @var LengowOrderErrorEntity[] $orderErrors */
            $orderErrors = $this->lengowOrderError->getOrderErrors($lengowOrderId, $orderErrorType, false);
            if ($orderErrors) {
                foreach ($orderErrors as $orderError) {
                    $orderErrorMessages[] = $this->lengowLog->decodeMessage($orderError->getMessage());
                }
            }
        }
        return new JsonResponse($orderErrorMessages);
    }

    /**
     * Load lengow order entity a try a re-import
     *
     * @param string $lengowOrderId Lengow order id
     *
     * @return bool
     */
    private function loadAndImportOrder(string $lengowOrderId): bool
    {
        $lengowOrder = $this->lengowOrder->getLengowOrderById($lengowOrderId);
        if ($lengowOrder) {
            $this->lengowImport->init([
                LengowImport::PARAM_TYPE => LengowImport::TYPE_MANUAL,
                LengowImport::PARAM_LENGOW_ORDER_ID => $lengowOrderId,
                LengowImport::PARAM_MARKETPLACE_SKU => $lengowOrder->getMarketplaceSku(),
                LengowImport::PARAM_MARKETPLACE_NAME => $lengowOrder->getMarketplaceName(),
                LengowImport::PARAM_DELIVERY_ADDRESS_ID => $lengowOrder->getDeliveryAddressId(),
                LengowImport::PARAM_SALES_CHANNEL_ID => $lengowOrder->getSalesChannel()->getId(),
            ]);
            $result = $this->lengowImport->exec();
            if (!empty($result[LengowImport::ORDERS_CREATED])) {
                return true;
            }
        }
        return false;
    }

    /**
     * Load Shopware order entity a try a re-send action
     *
     * @param string $lengowOrderId Lengow order id
     *
     * @return bool
     */
    private function loadAndResendAction(string $lengowOrderId): bool
    {
        $success = false;
        $order = $this->lengowOrder->getOrderByLengowOrderId($lengowOrderId);
        if ($order) {
            $action = $this->lengowAction->getLastOrderActionType($order->getId());
            if ($action === null) {
                if ($order->getStateMachineState()
                    && $order->getStateMachineState()->getTechnicalName() === OrderStates::STATE_CANCELLED
                ) {
                    $action = LengowAction::TYPE_CANCEL;
                } else {
                    $action = LengowAction::TYPE_SHIP;
                }
            }
            $orderDelivery = $action === LengowAction::TYPE_SHIP && $order->getDeliveries()
                ? $order->getDeliveries()->first()
                : null;
            $success = $this->lengowOrder->callAction($action, $order, $orderDelivery);
        }
        return $success;
    }

    /**
     * Generate message array (new, update and errors)
     *
     * @param array $result result from synchronisation process
     * @param bool $action action synchronisation or not
     *
     * @return array
     */
    private function loadMessages(array $result, bool $action = false): array
    {
        $messages = [];
        // if global error return this
        if (isset($result[LengowImport::ERRORS][0])) {
            return [$this->lengowLog->decodeMessage($result[LengowImport::ERRORS][0])];
        }
        // specific messages for order synchronisation
        if (isset($result[LengowImport::NUMBER_ORDERS_CREATED]) && $result[LengowImport::NUMBER_ORDERS_CREATED] > 0) {
            $messages[] = $this->lengowLog->decodeMessage(
                'lengow_log.error.nb_order_imported',
                null,
                ['nb_order' => $result[LengowImport::NUMBER_ORDERS_CREATED]]
            );
        }
        if (isset($result[LengowImport::NUMBER_ORDERS_UPDATED]) && $result[LengowImport::NUMBER_ORDERS_UPDATED] > 0) {
            $messages[] = $this->lengowLog->decodeMessage(
                'lengow_log.error.nb_order_updated',
                null,
                ['nb_order' => $result[LengowImport::NUMBER_ORDERS_UPDATED]]
            );
        }
        if (isset($result[LengowImport::NUMBER_ORDERS_FAILED]) && $result[LengowImport::NUMBER_ORDERS_FAILED] > 0) {
            $messages[] = $this->lengowLog->decodeMessage(
                'lengow_log.error.nb_order_with_error',
                null,
                ['nb_order' => $result[LengowImport::NUMBER_ORDERS_FAILED]]
            );
        }
        // specific message for mass resend action
        if ($action && isset($result['action_success']) && $result['action_success'] > 0) {
            $messages[] = $this->lengowLog->decodeMessage(
                'lengow_log.error.nb_action_success',
                null,
                ['nb_action' => $result['action_success']]
            );
        }
        if ($action && isset($result['action_error']) && $result['action_error'] > 0) {
            $messages[] = $this->lengowLog->decodeMessage(
                'lengow_log.error.nb_action_error',
                null,
                ['nb_action' => $result['action_error']]
            );
        }
        // if no notification about orders or actions
        if (empty($messages)) {
            $key = $action ? 'lengow_log.error.no_action_notification' : 'lengow_log.error.no_order_notification';
            $messages[] = $this->lengowLog->decodeMessage($key);
        }
        // return specific error for a Sales Channel
        if (isset($result[LengowImport::ERRORS])) {
            foreach ($result[LengowImport::ERRORS] as $salesChannelName => $values) {
                $messages[] = $salesChannelName . ': ' . $this->lengowLog->decodeMessage($values);
            }
        }
        return $messages;
    }
}
