<?php declare(strict_types=1);

namespace Lengow\Connector\Storefront\Controller;

use Shopware\Core\Framework\Routing\Annotation\RouteScope;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\Request;
use Lengow\Connector\Service\LengowAccess;
use Lengow\Connector\Service\LengowConfiguration;
use Lengow\Connector\Service\LengowImport;
use Lengow\Connector\Service\LengowLog;
use Lengow\Connector\Service\LengowSync;

/**
 * Class LengowCronController
 * @package Lengow\Connector\Storefront\Controller
 * @RouteScope(scopes={"storefront"})
 */
class LengowCronController extends LengowAbstractFrontController
{
    /**
     * @var LengowAccess Lengow access security service
     */
    protected $lengowAccessService;

    /**
     * @var LengowConfiguration Lengow configuration accessor service
     */
    protected $lengowConfiguration;

    /**
     * @var LengowLog Lengow log service
     */
    protected $lengowLog;

    /**
     * @var LengowImport Lengow import service
     */
    private $lengowImport;

    /**
     * @var LengowSync Lengow sync service
     */
    private $lengowSync;

    /**
     * LengowAbstractFrontController constructor
     *
     * @param LengowAccess $lengowAccess Lengow access security service
     * @param LengowConfiguration $lengowConfiguration Lengow configuration accessor service
     * @param LengowLog $lengowLog Lengow log service
     * @param LengowImport $lengowImport Lengow import service
     * @param LengowSync $lengowSync Lengow sync service
     */
    public function __construct(
        LengowAccess $lengowAccess,
        LengowConfiguration $lengowConfiguration,
        LengowLog $lengowLog,
        LengowImport $lengowImport,
        LengowSync $lengowSync
    )
    {
        parent::__construct($lengowAccess, $lengowConfiguration, $lengowLog);
        $this->lengowImport = $lengowImport;
        $this->lengowSync = $lengowSync;
    }

    /**
     * @param Request $request Http Request
     * @param SalesChannelContext $context SalesChannel context
     *
     * @return Response
     *
     * @Route("/lengow/cron", name="frontend.lengow.cron", methods={"GET"})
     */
    public function cron(Request $request, SalesChannelContext $context): Response
    {
        $this->checkAccess($request, $context);
        $cronArgs = $this->createGetArgArray($request);
        if ($cronArgs['get_sync'] === null || $cronArgs['get_sync']) {
            return new Response(json_encode($this->lengowSync->getSyncData()));
        }
        // sync catalogs id between Lengow and Shopware
        if ($cronArgs['sync'] === null || $cronArgs['sync'] === LengowSync::SYNC_CATALOG) {
            $this->lengowSync->syncCatalog($cronArgs['force'], $cronArgs['log_output']);
        }
        // synchronise orders between Lengow and Shopware
        if ($cronArgs['sync'] === null || $cronArgs['sync'] === LengowSync::SYNC_ORDER) {
            $this->lengowImport->init($cronArgs);
            $this->lengowImport->exec();
        }
        // synchronise marketplaces between Lengow and Shopware
        if ($cronArgs['sync'] === LengowSync::SYNC_MARKETPLACE) {
            $this->lengowSync->getMarketplaces($cronArgs['force'], $cronArgs['log_output']);
        }
        return new Response();
    }

    /**
     * @param Request $request Http request
     *
     * @return array
     */
    protected function createGetArgArray(Request $request): array
    {
        return [
            'sync' => $request->query->get('sync'),
            'debug_mode' => $request->query->get('debug_mode') !== null
                ? $request->query->get('debug_mode') === '1'
                : null,
            'log_output' => $request->query->get('log_output') === '1',
            'days' => (int)$request->query->get('days'),
            'created_from' => $request->query->get('created_from'),
            'created_to' => $request->query->get('created_to'),
            'limit' => (int)$request->query->get('limit'),
            'marketplace_sku' => $request->query->get('marketplace_sku'),
            'marketplace_name' => $request->query->get('marketplace_name'),
            'sales_channel_id'=> $request->query->get('sales_channel_id'),
            'delivery_address_id' => (int)$request->query->get('delivery_address_id'),
            'get_sync' => $request->query->get('get_sync') === '1',
            'force' => $request->query->get('force') === '1',
            'type' => LengowImport::TYPE_CRON,
        ];
    }
}
