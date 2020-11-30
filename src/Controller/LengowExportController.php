<?php declare(strict_types=1);

namespace Lengow\Connector\Controller;

use Shopware\Core\Framework\Routing\Annotation\RouteScope;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\JsonResponse;
use Lengow\Connector\Service\LengowExport;
use Lengow\Connector\Util\EnvironmentInfoProvider;

/**
 * Class LengowExportController
 * @package Lengow\Connector\Controller
 * @RouteScope(scopes={"api"})
 */
class LengowExportController extends AbstractController
{

    /**
     * @var LengowExport lengow export service
     */
    private $lengowExport;

    /**
     * @param LengowExport $lengowExport
     *
     * LengowOrderController constructor
     */
    public function __construct(LengowExport $lengowExport)
    {
        $this->lengowExport = $lengowExport;
    }

    /**
     * Get sales channel export count
     *
     * @Route("/api/v{version}/_action/lengow/export/get-export-count",
     *     name="api.action.lengow.export.get-export-count",
     *     methods={"GET"})
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function getExportCount(Request $request) : JsonResponse
    {
        if ($request->get('salesChannelId')) {
            $salesChannelId = $request->get('salesChannelId');
            $this->lengowExport->init($salesChannelId);
            $response = [
                'success' => true,
                'total' => $this->lengowExport->getTotalProduct(),
                'exported' => $this->lengowExport->getTotalExportedProduct(),
            ];
            return new JsonResponse($response);
        }
        return new JsonResponse(['success' => false]);
    }
}
