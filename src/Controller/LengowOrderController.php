<?php declare(strict_types=1);

namespace Lengow\Connector\Controller;

use Shopware\Core\Framework\Routing\Annotation\RouteScope;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\JsonResponse;
use Lengow\Connector\Entity\Lengow\OrderError\OrderErrorEntity as LengowOrderErrorEntity;
use Lengow\Connector\Service\LengowImport;
use Lengow\Connector\Service\LengowLog;
use Lengow\Connector\Service\LengowOrder;
use Lengow\Connector\Service\LengowOrderError;

/**
 * Class LengowOrderController
 * @package Lengow\Connector\Controller
 * @RouteScope(scopes={"api"})
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
     * LengowOrderController constructor
     *
     * @param LengowImport $lengowImport Lengow import service
     * @param LengowLog $lengowLog Lengow log service
     * @param LengowOrder $lengowOrder Lengow order service
     * @param LengowOrderError $lengowOrderError Lengow order error service
     */
    public function __construct(
        LengowImport $lengowImport,
        LengowLog $lengowLog,
        LengowOrder $lengowOrder,
        LengowOrderError $lengowOrderError
    )
    {
        $this->lengowImport = $lengowImport;
        $this->lengowLog = $lengowLog;
        $this->lengowOrder = $lengowOrder;
        $this->lengowOrderError = $lengowOrderError;
    }

    /**
     * Synchronise all orders
     *
     * @Route("/api/v{version}/_action/lengow/order/synchronise-orders",
     *     name="api.action.lengow.order.synchronise-order",
     *     methods={"GET"})
     *
     * @return JsonResponse
     */
    public function synchroniseOrders(): JsonResponse
    {
        $this->lengowImport->init();
        $result = $this->lengowImport->exec();
        $message = $this->loadMessage($result);
        return new JsonResponse($message);
    }

    /**
     * Re-import a specific order
     *
     * @Route("/api/v{version}/_action/lengow/order/reimport-order",
     *     defaults={"auth_enabled"=true},
     *     name="api.action.lengow.order.reimport-order",
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
            $lengowOrder = $this->lengowOrder->getLengowOrderById($lengowOrderId);
            if ($lengowOrder) {
                $this->lengowImport->init([
                    'type' => LengowImport::TYPE_MANUAL,
                    'lengow_order_id' => $lengowOrderId,
                    'marketplace_sku' => $lengowOrder->getMarketplaceSku(),
                    'marketplace_name' => $lengowOrder->getMarketplaceName(),
                    'delivery_address_id' => $lengowOrder->getDeliveryAddressId(),
                    'sales_channel_id' => $lengowOrder->getSalesChannel()->getId(),
                ]);
                $result = $this->lengowImport->exec();
            }
        }
        return new JsonResponse($result);
    }

    /**
     * Get all available marketplaces for filter
     *
     * @Route("/api/v{version}/_action/lengow/order/get-available-marketplaces",
     *     name="api.action.lengow.order.get-available-marketplaces",
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
     * @Route("/api/v{version}/_action/lengow/order/get-order-errors",
     *     defaults={"auth_enabled"=true},
     *     name="api.action.lengow.order.get-order-errors",
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
     * Generate message array (new, update and errors)
     *
     * @param array $result result from synchronisation process
     *
     * @return array
     */
    private function loadMessage(array $result): array
    {
        $messages = [];
        // if global error return this
        if (isset($result['error'][0])) {
            return [$this->lengowLog->decodeMessage($result['error'][0])];
        }
        if (isset($result['order_new']) && $result['order_new'] > 0) {
            $messages[] = $this->lengowLog->decodeMessage(
                'lengow_log.error.nb_order_imported',
                null,
                ['nb_order' => $result['order_new']]
            );
        }
        if (isset($result['order_update']) && $result['order_update'] > 0) {
            $messages[] = $this->lengowLog->decodeMessage(
                'lengow_log.error.nb_order_updated',
                null,
                ['nb_order' => $result['order_update']]
            );
        }
        if (isset($result['order_error']) && $result['order_error'] > 0) {
            $messages[] = $this->lengowLog->decodeMessage(
                'lengow_log.error.nb_order_with_error',
                null,
                ['nb_order' => $result['order_error']]
            );
        }
        if (empty($messages)) {
            $messages[] = $this->lengowLog->decodeMessage('lengow_log.error.no_notification');
        }
        if (isset($result['error'])) {
            foreach ($result['error'] as $salesChannelName => $values) {
                $messages[] = $salesChannelName . ': ' . $this->lengowLog->decodeMessage($values);
            }
        }
        return $messages;
    }
}
