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
 * Class LengowCronController
 * @package Lengow\Connector\Storefront\Controller
 * @RouteScope(scopes={"storefront"})
 */
class LengowCronController extends StorefrontController
{
    /**
     * @var LengowAccess
     */
    private $lengowAccessService;

    /**
     * @var SystemConfigService $systemConfigService Shopware settings access
     */
    private $systemConfigService;

    /**
     * LengowCronController constructor
     *
     * @param LengowAccess $lengowAccess Lengow settings access
     * @param SystemConfigService $systemConfigService Shopware settings access
     */
    public function __construct(LengowAccess $lengowAccess, SystemConfigService $systemConfigService)
    {
        $this->lengowAccessService = $lengowAccess;
        $this->systemConfigService = $systemConfigService;
    }

    /**
     * @param Request $request Http Request
     * @param SalesChannelContext $context SalesChannel context
     *
     * @return void
     *
     * @Route("/lengow/cron", name="frontend.lengow.cron", methods={"GET"})
     */
    public function cron(Request $request, SalesChannelContext $context) : void
    {
        $token = $request->query->get('token');
        $shopId = $request->query->get('shop_id');

        if (!$this->lengowAccessService->handleSalesChannel($shopId)) {
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
        $cronArgs = $this->createCronArgArray($request);
        // TODO handle export here
        die(var_dump($cronArgs));
    }

    /**
     * @param Request $request Http request
     *
     * @return array
     */
    private function createCronArgArray(Request $request): array
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
