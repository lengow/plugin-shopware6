<?php declare(strict_types=1);

namespace Lengow\Connector\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\JsonResponse;
use Lengow\Connector\Service\LengowConfiguration;
use Lengow\Connector\Service\LengowExport;

#[Route(defaults: ['_routeScope' => ['api']])]
class LengowExportController extends AbstractController
{
    /**
     * @var LengowConfiguration Lengow configuration service
     */
    private $lengowConfiguration;

    /**
     * @var LengowExport lengow export service
     */
    private $lengowExport;

    /**
     * LengowExportController constructor
     *
     * @param LengowConfiguration $lengowConfiguration Lengow configuration service
     * @param LengowExport $lengowExport Lengow export service
     */
    public function __construct(LengowConfiguration $lengowConfiguration, LengowExport $lengowExport)
    {
        $this->lengowConfiguration = $lengowConfiguration;
        $this->lengowExport = $lengowExport;
    }

    #[Route('/api/_action/lengow/export/get-export-link', name: 'api.action.lengow.export.get-export-link', methods: ['GET'], description: 'Get feed url for a specific sales channel')]
    #[Route('/api/v{version}/_action/lengow/export/get-export-link', name: 'api.action.lengow.export.get-export-link-old', methods: ['GET'])]
    public function getExportLink(Request $request) : JsonResponse
    {
        if ($request->get('salesChannelId')) {
            $salesChannelId = $request->get('salesChannelId');
            $feedUrl = $this->lengowConfiguration->getFeedUrl($salesChannelId);
            $response = [
                'success' => true,
                'link' => $feedUrl . '&stream=1&update_export_date=0&format=csv`',
            ];
            return new JsonResponse($response);
        }
        return new JsonResponse(['success' => false]);
    }

    #[Route('/api/_action/lengow/export/get-export-count', name: 'api.action.lengow.export.get-export-count', methods: ['GET'], description: 'Get sales channel export count')]
    #[Route('/api/v{version}/_action/lengow/export/get-export-count', name: 'api.action.lengow.export.get-export-count-old', methods: ['GET'])]
    public function getExportCount(Request $request) : JsonResponse
    {
        if ($request->get('salesChannelId')) {
            $salesChannelId = $request->get('salesChannelId');
            $this->lengowExport->init([
                LengowExport::PARAM_SALES_CHANNEL_ID => $salesChannelId,
            ]);
            $response = [
                'success' => true,
                'total' => $this->lengowExport->getTotalProduct(),
                'exported' => $this->lengowExport->getTotalExportProduct(),
            ];
            return new JsonResponse($response);
        }
        return new JsonResponse(['success' => false]);
    }

    #[Route('/api/_action/lengow/export/get-product-count', name: 'api.action.lengow.export.get-product-count', methods: ['GET'], description: 'Get product count value (parent + all variants)')]
    #[Route('/api/v{version}/_action/lengow/export/get-product-count', name: 'api.action.lengow.export.get-product-count-old', methods: ['GET'])]
    public function getProductCount(Request $request) : JsonResponse
    {
        if ($request->get('productId') && $request->get('salesChannelId')) {
            $salesChannelId = $request->get('salesChannelId');
            $this->lengowExport->init([
                LengowExport::PARAM_SALES_CHANNEL_ID => $salesChannelId,
            ]);
            $response = [
                'success' => true,
                'countValue' => count($this->lengowExport->getProductIdsExport([$request->get('productId')])),
            ];
        } else {
            $response = [
                'success' => false,
            ];
        }
        return new JsonResponse($response);
    }

    #[Route('/api/_action/lengow/export/get-product-list', name: 'api.action.lengow.export.get-product-list', methods: ['GET'], description: 'Get product list for salesChannelId')]
    #[Route('/api/v{version}/_action/lengow/export/get-product-list', name: 'api.action.lengow.export.get-product-list-old', methods: ['GET'])]
    public function getProductList(Request $request) : JsonResponse
    {
        if ($request->get('salesChannelId')) {
            $salesChannelId = $request->get('salesChannelId');
            $this->lengowExport->init([
                LengowExport::PARAM_SALES_CHANNEL_ID => $salesChannelId,
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
