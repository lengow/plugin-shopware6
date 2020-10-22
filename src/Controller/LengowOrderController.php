<?php declare(strict_types=1);

namespace Lengow\Connector\Controller;

use Shopware\Core\Framework\Routing\Annotation\RouteScope;
use Shopware\Core\Framework\Context;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\JsonResponse;
use Lengow\Connector\Service\LengowImport;
use Lengow\Connector\Service\LengowLog;

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
     * LengowOrderController constructor
     *
     * @param LengowImport $lengowImport Lengow import service
     * @param LengowLog $lengowLog Lengow log service
     */
    public function __construct(LengowImport $lengowImport, LengowLog $lengowLog)
    {
        $this->lengowImport = $lengowImport;
        $this->lengowLog = $lengowLog;
    }

    /**
     * Synchronise all orders
     *
     * @Route("/api/v{version}/_action/lengow/order/synchronise-orders", name="api.action.lengow.get.synchronise-order", methods={"GET"})
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
