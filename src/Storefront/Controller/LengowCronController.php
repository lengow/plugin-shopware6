<?php declare(strict_types=1);

namespace Lengow\Connector\Storefront\Controller;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\Request;
use Lengow\Connector\Service\LengowAccess;
use Lengow\Connector\Service\LengowActionSync;
use Lengow\Connector\Service\LengowConfiguration;
use Lengow\Connector\Service\LengowImport;
use Lengow\Connector\Service\LengowLog;
use Lengow\Connector\Service\LengowSync;
use Lengow\Connector\Service\LengowTranslation;

#[Route(defaults: ['_routeScope' => 'storefront'])]
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
     * @var LengowActionSync Lengow action sync service
     */
    private $lengowActionSync;

    /**
     * LengowCronController constructor
     *
     * @param LengowAccess $lengowAccess Lengow access security service
     * @param LengowConfiguration $lengowConfiguration Lengow configuration accessor service
     * @param LengowLog $lengowLog Lengow log service
     * @param LengowImport $lengowImport Lengow import service
     * @param LengowSync $lengowSync Lengow sync service
     * @param LengowActionSync $lengowActionSync Lengow action sync service
     */
    public function __construct(
        LengowAccess $lengowAccess,
        LengowConfiguration $lengowConfiguration,
        LengowLog $lengowLog,
        LengowImport $lengowImport,
        LengowSync $lengowSync,
        LengowActionSync $lengowActionSync
    )
    {
        parent::__construct($lengowAccess, $lengowConfiguration, $lengowLog);
        $this->lengowImport = $lengowImport;
        $this->lengowSync = $lengowSync;
        $this->lengowActionSync = $lengowActionSync;
    }

    #[Route('/lengow/cron', name: 'frontend.lengow.cron', methods: ['GET'], description: 'Cron Process (Import orders, check actions and send stats)')]
    public function cron(Request $request): Response
    {
        $accessErrorMessage = $this->checkAccess($request);
        if ($accessErrorMessage !== null) {
            return new Response($accessErrorMessage, Response::HTTP_FORBIDDEN);
        }
        $cronArgs = $this->createGetArgArray($request);
        if ($cronArgs[LengowImport::PARAM_GET_SYNC] === null || $cronArgs[LengowImport::PARAM_GET_SYNC]) {
            return new Response(json_encode($this->lengowSync->getSyncData()));
        }
        // sync catalogs' id between Lengow and Shopware
        if ($cronArgs[LengowImport::PARAM_SYNC] === null
            || $cronArgs[LengowImport::PARAM_SYNC] === LengowSync::SYNC_CATALOG
        ) {
            $this->lengowSync->syncCatalog(
                $cronArgs[LengowImport::PARAM_FORCE],
                $cronArgs[LengowImport::PARAM_LOG_OUTPUT]
            );
        }
        // synchronise orders between Lengow and Shopware
        if ($cronArgs[LengowImport::PARAM_SYNC] === null
            || $cronArgs[LengowImport::PARAM_SYNC] === LengowSync::SYNC_ORDER
        ) {
            $this->lengowImport->init($cronArgs);
            $this->lengowImport->exec();
        }
        // sync actions between Lengow and Shopware
        if ($cronArgs[LengowImport::PARAM_SYNC] === null
            || $cronArgs[LengowImport::PARAM_SYNC] === LengowSync::SYNC_ACTION
        ) {
            $this->lengowActionSync->checkFinishAction($cronArgs[LengowImport::PARAM_LOG_OUTPUT]);
            $this->lengowActionSync->checkOldAction($cronArgs[LengowImport::PARAM_LOG_OUTPUT]);
            $this->lengowActionSync->checkNotSentAction($cronArgs[LengowImport::PARAM_LOG_OUTPUT]);
        }
        // sync options between Lengow and Shopware
        if ($cronArgs[LengowImport::PARAM_SYNC] === null
            || $cronArgs[LengowImport::PARAM_SYNC] === LengowSync::SYNC_CMS_OPTION
        ) {
            $this->lengowSync->setCmsOption(
                $cronArgs[LengowImport::PARAM_FORCE],
                $cronArgs[LengowImport::PARAM_LOG_OUTPUT]
            );
        }
        // synchronise marketplaces between Lengow and Shopware
        if ($cronArgs[LengowImport::PARAM_SYNC] === LengowSync::SYNC_MARKETPLACE) {
            $this->lengowSync->getMarketplaces(
                $cronArgs[LengowImport::PARAM_FORCE],
                $cronArgs[LengowImport::PARAM_LOG_OUTPUT]
            );
        }
        // synchronise plugin data between Lengow and Shopware
        if ($cronArgs[LengowImport::PARAM_SYNC] === LengowSync::SYNC_PLUGIN_DATA) {
            $this->lengowSync->getPluginData(
                $cronArgs[LengowImport::PARAM_FORCE],
                $cronArgs[LengowImport::PARAM_LOG_OUTPUT]
            );
        }
        // synchronise account status between Lengow and Shopware
        if ($cronArgs[LengowImport::PARAM_SYNC] === LengowSync::SYNC_STATUS_ACCOUNT) {
            $this->lengowSync->getAccountStatus(
                $cronArgs[LengowImport::PARAM_FORCE],
                $cronArgs[LengowImport::PARAM_LOG_OUTPUT]
            );
        }
        if ($cronArgs[LengowImport::PARAM_SYNC]
            && !$this->lengowSync->isSyncAction($cronArgs[LengowImport::PARAM_SYNC])
        ) {
            $errorMessage = $this->lengowLog->decodeMessage(
                'log.import.not_valid_action',
                LengowTranslation::DEFAULT_ISO_CODE,
                [
                    'action' => $cronArgs[LengowImport::PARAM_SYNC],
                ]
            );
            return new Response($errorMessage, Response::HTTP_BAD_REQUEST);
        }
        return new Response();
    }

    /**
     * Get all parameters from request
     * List params
     * string sync                Data type to synchronize
     * bool   debug_mode          Activate debug mode (1) or not (0)
     * bool   log_output          Display log messages (1) or not (0)
     * int    days                Synchronization interval time
     * string created_from        Synchronization of orders since
     * string created_to          Synchronization of orders until
     * int    limit               Maximum number of new orders created
     * string marketplace_sku     Lengow marketplace order id to synchronize
     * string marketplace_name    Lengow marketplace name to synchronize
     * int    sales_channel_id    Sales channel id to synchronize
     * int    delivery_address_id Lengow delivery address id to synchronize
     * bool   get_sync            See synchronization parameters in json format (1) or not (0)
     * bool   force               Force synchronization (1) or not (0)
     * bool   force_sync          Force import order even if there are errors
     *
     * @param Request $request Http request
     *
     * @return array
     */
    protected function createGetArgArray(Request $request): array
    {
        return [
            LengowImport::PARAM_SYNC => $request->query->get(LengowImport::PARAM_SYNC),
            LengowImport::PARAM_DEBUG_MODE => $request->query->get(LengowImport::PARAM_DEBUG_MODE) !== null
                ? $request->query->get(LengowImport::PARAM_DEBUG_MODE) === '1'
                : null,
            LengowImport::PARAM_LOG_OUTPUT => $request->query->get(LengowImport::PARAM_LOG_OUTPUT) === '1',
            LengowImport::PARAM_DAYS => (int) $request->query->get(LengowImport::PARAM_DAYS),
            LengowImport::PARAM_CREATED_FROM => $request->query->get(LengowImport::PARAM_CREATED_FROM),
            LengowImport::PARAM_CREATED_TO => $request->query->get(LengowImport::PARAM_CREATED_TO),
            LengowImport::PARAM_LIMIT => (int) $request->query->get(LengowImport::PARAM_LIMIT),
            LengowImport::PARAM_MARKETPLACE_SKU => $request->query->get(LengowImport::PARAM_MARKETPLACE_SKU),
            LengowImport::PARAM_MARKETPLACE_NAME => $request->query->get(LengowImport::PARAM_MARKETPLACE_NAME),
            LengowImport::PARAM_SALES_CHANNEL_ID => $request->query->get(LengowImport::PARAM_SALES_CHANNEL_ID),
            LengowImport::PARAM_DELIVERY_ADDRESS_ID => (int) $request->query->get(
                LengowImport::PARAM_DELIVERY_ADDRESS_ID
            ),
            LengowImport::PARAM_GET_SYNC => $request->query->get(LengowImport::PARAM_GET_SYNC) === '1',
            LengowImport::PARAM_FORCE => $request->query->get(LengowImport::PARAM_FORCE) === '1',
            LengowImport::PARAM_FORCE_SYNC => $request->query->get(LengowImport::PARAM_FORCE_SYNC) === '1',
            LengowImport::PARAM_TYPE => LengowImport::TYPE_CRON,
        ];
    }
}
