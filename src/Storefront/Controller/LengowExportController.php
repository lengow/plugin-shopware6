<?php declare(strict_types=1);

namespace Lengow\Connector\Storefront\Controller;

use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Lengow\Connector\Service\LengowAccess;
use Lengow\Connector\Service\LengowConfiguration;
use Lengow\Connector\Service\LengowExport;
use Lengow\Connector\Service\LengowLog;
use Lengow\Connector\Service\LengowTranslation;

/**
 * Class LengowExportController
 * @package Lengow\Connector\Storefront\Controller
 * @Route(defaults={"_routeScope"={"storefront"}})
 */
class LengowExportController extends LengowAbstractFrontController
{
    /**
     * @var LengowExport lengow export service
     */
    private $lengowExport;

    /**
     * LengowExportController constructor
     *
     * @param LengowAccess $lengowAccess lengow access service
     * @param LengowConfiguration $lengowConfiguration lengow configuration service
     * @param LengowLog $lengowLog lengow log service
     * @param LengowExport $lengowExport lengow export service
     */
    public function __construct(
        LengowAccess $lengowAccess,
        LengowConfiguration $lengowConfiguration,
        LengowLog $lengowLog,
        LengowExport $lengowExport
    )
    {
        parent::__construct($lengowAccess, $lengowConfiguration, $lengowLog);
        $this->lengowExport = $lengowExport;
    }

    /**
     * Export Process
     *
     * @param Request $request Http request
     *
     * @Route("/lengow/export", name="frontend.lengow.export", methods={"GET"})
     *
     * @return Response
     */
    public function export(Request $request): Response
    {
        $salesChannelName = $this->getSalesChannelName($request);
        if ($salesChannelName === null) {
            $errorMessage =  $this->lengowLog->decodeMessage(
                'log.export.specify_sales_channel',
                LengowTranslation::DEFAULT_ISO_CODE
            );
            return new Response($errorMessage, Response::HTTP_BAD_REQUEST);
        }
        $accessErrorMessage = $this->checkAccess($request, true);
        if ($accessErrorMessage !== null) {
            return new Response($accessErrorMessage, Response::HTTP_FORBIDDEN);
        }
        $exportArgs = $this->createGetArgArray($request);
        $this->lengowExport->init($exportArgs);
        if ($exportArgs[LengowExport::PARAM_GET_PARAMS]) {
            return new Response($this->lengowExport->getExportParams());
        }
        if ($exportArgs[LengowExport::PARAM_MODE]) {
            return new Response((string) $this->modeSize($exportArgs[LengowExport::PARAM_MODE]));
        }
        $this->lengowExport->exec();
        return new Response();
    }

    /**
     * Get all parameters from request
     * List params
     * string mode               Number of products exported
     * string format             Format of exported files ('csv','yaml','xml','json')
     * bool   stream             Stream file (1) or generate a file on server (0)
     * int    offset             Offset of total product
     * int    limit              Limit number of exported product
     * bool   selection          Export product selection (1) or all products (0)
     * bool   out_of_stock       Export out of stock product (1) Export only product in stock (0)
     * bool   inactive           Export inactive product (1) or not (0)
     * bool   variation          Export product variation (1) or not (0)
     * string product_ids        List of product id separate with comma (1,2,3)
     * int    sales_channel_id   Export a specific store with store id
     * string currency           Convert prices with a specific currency
     * string language           Translate content with a specific language
     * bool   log_output         See logs (1) or not (0)
     * bool   update_export_date Change last export date in data base (1) or not (0)
     * bool   get_params         See export parameters and authorized values in json format (1) or not (0)
     *
     * @param Request $request Http request
     *
     * @return array
     */
    protected function createGetArgArray(Request $request): array
    {
        return [
            LengowExport::PARAM_MODE => $request->query->get(LengowExport::PARAM_MODE),
            LengowExport::PARAM_FORMAT => $request->query->get(LengowExport::PARAM_FORMAT),
            LengowExport::PARAM_STREAM => $request->query->get(LengowExport::PARAM_STREAM) !== null
                ? $request->query->get(LengowExport::PARAM_STREAM) === '1'
                : null,
            LengowExport::PARAM_OFFSET => $request->query->get(LengowExport::PARAM_OFFSET) !== null
                ? (int) $request->query->get(LengowExport::PARAM_OFFSET)
                : null,
            LengowExport::PARAM_LIMIT => $request->query->get(LengowExport::PARAM_LIMIT) !== null
                ? (int) $request->query->get(LengowExport::PARAM_LIMIT)
                : null,
            LengowExport::PARAM_SELECTION => $request->query->get(LengowExport::PARAM_SELECTION) !== null
                ? $request->query->get(LengowExport::PARAM_SELECTION) === '1'
                : null,
            LengowExport::PARAM_OUT_OF_STOCK => $request->query->get(LengowExport::PARAM_OUT_OF_STOCK) !== null
                ? $request->query->get(LengowExport::PARAM_OUT_OF_STOCK) === '1'
                : null,
            LengowExport::PARAM_VARIATION => $request->query->get(LengowExport::PARAM_VARIATION) !== null
                ? $request->query->get(LengowExport::PARAM_VARIATION) === '1'
                : null,
            LengowExport::PARAM_INACTIVE => $request->query->get(LengowExport::PARAM_INACTIVE) !== null
                ? $request->query->get(LengowExport::PARAM_INACTIVE) === '1'
                : null,
            LengowExport::PARAM_PRODUCT_IDS => $request->query->get(LengowExport::PARAM_PRODUCT_IDS),
            LengowExport::PARAM_SALES_CHANNEL_ID => $request->query->get(LengowExport::PARAM_SALES_CHANNEL_ID),
            LengowExport::PARAM_CURRENCY => $request->query->get(LengowExport::PARAM_CURRENCY),
            LengowExport::PARAM_LANGUAGE => $request->query->get(LengowExport::PARAM_LANGUAGE ),
            LengowExport::PARAM_LOG_OUTPUT => $request->query->get(LengowExport::PARAM_LOG_OUTPUT) !== null
                ? $request->query->get(LengowExport::PARAM_LOG_OUTPUT) === '1'
                : null,
            LengowExport::PARAM_UPDATE_EXPORT_DATE => $request->query->get(
                LengowExport::PARAM_UPDATE_EXPORT_DATE
            ) !== null ? $request->query->get(LengowExport::PARAM_UPDATE_EXPORT_DATE) === '1' : null,
            LengowExport::PARAM_GET_PARAMS => $request->query->get( LengowExport::PARAM_GET_PARAMS) !== null
                ? $request->query->get( LengowExport::PARAM_GET_PARAMS) === '1'
                : null,
        ];
    }

    /**
     * Get mode size
     *
     * @param string $mode size mode
     *
     * @return int
     */
    protected function modeSize(string $mode): int
    {
        if ($mode === 'size') {
            return $this->lengowExport->getTotalExportProduct();
        }
        if ($mode === 'total') {
            return $this->lengowExport->getTotalProduct();
        }
        return 0;
    }
}
