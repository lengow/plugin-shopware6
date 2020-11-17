<?php declare(strict_types=1);

namespace Lengow\Connector\Controller;

use Shopware\Core\Framework\Routing\Annotation\RouteScope;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\JsonResponse;
use Lengow\Connector\Service\LengowSync;

/**
 * Class LengowAccountSynchronistationController
 * @package Lengow\Connector\Controller
 * @RouteScope(scopes={"api"})
 */
class LengowAccountSynchronistationController extends AbstractController
{
    /**
     * @var LengowSync $lengowSync Lengow synchronisation service
     */
    private $lengowSync;

    /**
     * LengowOrderController constructor
     *
     * @param LengowSync $lengowSync lengow sync service
     */
    public function __construct(LengowSync $lengowSync)
    {
        $this->lengowSync = $lengowSync;
    }

    /**
     * Get all sync data
     *
     * @Route("/api/v{version}/_action/lengow/synchronisation/get-sync-data", name="api.action.lengow.synchronisation.get-sync-data", methods={"GET"})
     *
     * @return JsonResponse
     */
    public function getSyncData(): JsonResponse
    {
        $response = [
            'function' => 'sync',
            'parameters' => $this->lengowSync->getSyncData(),
        ];
        return new JsonResponse($response);
    }
}
