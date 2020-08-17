<?php declare(strict_types=1);

namespace Lengow\Connector\Storefront\Controller;

use Shopware\Core\Framework\Routing\Annotation\RouteScope;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Controller\StorefrontController;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\Request;

/**
 * Class LengowExportController
 * @package Lengow\Connector\Storefront\Controller
 * @RouteScope(scopes={"storefront"})
 */
class LengowExportController extends LengowAbstractFrontController
{
    /**
     * @param Request $request Http request
     * @param SalesChannelContext $context SalesChannel context
     *
     * @return void
     *
     * @Route("/lengow/export", name="frontend.lengow.export", methods={"GET"})
     */
    public function export(Request $request, SalesChannelContext $context): void
    {
        $this->checkAccess($request, $context, false);
        $exportArgs = $this->createGetArgArray($request);
        // TODO handle export here
        die(var_dump($exportArgs));
    }

    /**
     * @param Request $request Http request
     *
     * @return array
     */
    protected function createGetArgArray(Request $request): array
    {
        return [
            'format' => $request->query->get('format'),
            'mode' => $request->query->get('mode'),
            'stream' => $request->query->get('stream') === '1',
            'product_ids' => $request->query->get('product_ids'),
            'limit' => (int)$request->query->get('limit'),
            'offset' => (int)$request->query->get('offset'),
            'out_of_stock' => $request->query->get('out_of_stock') === '1',
            'variation' => $request->query->get('variation') === '1',
            'inactive' => $request->query->get('inactive') === '1',
            'selection' => $request->query->get('selection'),
            'log_output' => $request->query->get('log_output') === '1',
            'update_export_date' => $request->query->get('update_export_date') === '1',
            'currency' => $request->query->get('currency'),
        ];
    }
}
