<?php declare(strict_types=1);

namespace Lengow\Connector\Storefront\Controller;

use Shopware\Core\Framework\Routing\Annotation\RouteScope;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Controller\StorefrontController;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\Request;
use Lengow\Connector\Service\LengowAccess;

/**
 * Class LengowExportController
 * @package Lengow\Connector\Storefront\Controller
 * @RouteScope(scopes={"storefront"})
 */
class LengowExportController extends StorefrontController
{
    /**
     * @var \Lengow\Connector\Service\LengowAccess
     */
    private $lengowAccessService;

    /**
     * @var SystemConfigService $systemConfigService Shopware settings access
     */
    private $systemConfigService;

    /**
     * LengowExportController constructor
     *
     * @param LengowAccess $lengowAccess
     * @param SystemConfigService $systemConfigService Shopware settings access
     */
    public function __construct(LengowAccess $lengowAccess, SystemConfigService $systemConfigService)
    {
        $this->lengowAccessService = $lengowAccess;
        $this->systemConfigService = $systemConfigService;
    }

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
        $token = $request->query->get('token');
        $shopId = $request->query->get('shop');

        if (!$this->lengowAccessService->handleSalesChannel($shopId))
        {
            header('HTTP/1.1 400 Bad Request');
            die('no shop specified'); // TODO add trad here
        }
        if (!$this->lengowAccessService->checkWebserviceAccess($token, $shopId))
        {
            if ($this->systemConfigService->get('Connector.config.AuthorizedIpListCheckbox')) { // TODO use lengowConfig
                $errorMessage = 'unauthorised IP: ' . $_SERVER['REMOTE_ADDR'];
            } else {
                $errorMessage = strlen($token) > 0
                    ? 'unauthorised access for this token: ' . $token
                    : 'unauthorised access: token parameter is empty';
            }
            header('HTTP/1.1 403 Forbidden');
            die($errorMessage);
        }
        $exportArgs = $this->createExportArgArray($request);
        // TODO handle export here
        die(var_dump($exportArgs));
    }

    /**
     * @param Request $request Http request
     *
     * @return array
     */
    private function createExportArgArray(Request $request) : array
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
