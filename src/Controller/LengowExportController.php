<?php declare(strict_types=1);

namespace Lengow\Connector\Controller;

use Shopware\Core\Framework\Routing\Annotation\RouteScope;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\JsonResponse;
use Lengow\Connector\Service\LengowExport;

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
     * LengowOrderController constructor
     *
     * @param LengowExport $lengowExport
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
            $this->lengowExport->init([
                'sales_channel_id' => $salesChannelId,
            ]);
            $response = [
                'success' => true,
                'total' => $this->lengowExport->getTotalProduct(),
                'exported' => $this->lengowExport->getTotalExportedProduct(),
            ];
            return new JsonResponse($response);
        }
        return new JsonResponse(['success' => false]);
    }

    /**
     * Get product count value (parent + all variants)
     *
     * @Route("/api/v{version}/_action/lengow/export/get-product-count",
     *     name="api.action.lengow.export.get-product-count",
     *     methods={"GET"})
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function getProductCount(Request $request) : JsonResponse
    {
        if ($request->get('productId') && $request->get('salesChannelId')) {
            $salesChannelId = $request->get('salesChannelId');
            $this->lengowExport->init([
                'sales_channel_id' => $salesChannelId,
            ]);
            $response = [
                'success' => true,
                'countValue' => count($this->lengowExport->getSelectionProductIdsExport([$request->get('productId')])),
            ];
        } else {
            $response = [
                'success' => false,
            ];
        }
        return new JsonResponse($response);
    }

    /**
     * Get product list for salesChannelId
     *
     * @Route("/api/v{version}/_action/lengow/export/get-product-list",
     *     name="api.action.lengow.export.get-product-list",
     *     methods={"GET"})
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function getProductList(Request $request) : JsonResponse
    {
        if ($request->get('salesChannelId')) {
            $salesChannelId = $request->get('salesChannelId');
            $this->lengowExport->init([
                'sales_channel_id' => $salesChannelId,
            ]);
            $response = [
                'success' => true,
                'productList' => $this->lengowExport->getAllProductIdForSalesChannel(),
            ];
        } else {
            $response = [
                'success' => false,
            ];
        }
        return new JsonResponse($response);
    }
}
