<?php declare(strict_types=1);

namespace Lengow\Connector\Storefront\Controller;

use Shopware\Core\Framework\Routing\Annotation\RouteScope;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Controller\StorefrontController;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\Request;

/**
 * Class LengowCronController
 * @package Lengow\Connector\Storefront\Controller
 * @RouteScope(scopes={"storefront"})
 */
class LengowCronController extends LengowAbstractFrontController
{
    /**
     * @param Request $request Http Request
     * @param SalesChannelContext $context SalesChannel context
     *
     * @return void
     *
     * @Route("/lengow/cron", name="frontend.lengow.cron", methods={"GET"})
     */
    public function cron(Request $request, SalesChannelContext $context): void
    {
        $this->checkAccess($request, $context);
        $cronArgs = $this->createGetArgArray($request);
        // TODO handle import here
        die(var_dump($cronArgs));
    }

    /**
     * @param Request $request Http request
     *
     * @return array
     */
    protected function createGetArgArray(Request $request): array
    {
        return [
            'sync' => $request->query->get('sync') === '1',
            'debug_mode' => $request->query->get('debug_mode') === '1',
            'log_output' => $request->query->get('log_output') === '1',
            'days' => (int)$request->query->get('days'),
            'created_from' => $request->query->get('created_from'),
            'created_to' => $request->query->get('created_to'),
            'limit' => (int)$request->query->get('limit'),
            'marketplace_sku' => $request->query->get('marketplace_sku'),
            'marketplace_name' => $request->query->get('marketplace_name'),
            'delivery_address_id' => $request->query->get('delivery_address_id'),
            'get_sync' => $request->query->get('get_sync') === '1',
            'force' => $request->query->get('force') === '1',
        ];
    }
}
