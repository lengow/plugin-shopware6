<?php declare(strict_types=1);

namespace Lengow\Connector\Storefront\Controller;

use Lengow\Connector\Service\LengowTranslation;
use Shopware\Core\Framework\Routing\Annotation\RouteScope;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Lengow\Connector\Service\LengowAccess;
use Lengow\Connector\Service\LengowConfiguration;
use Lengow\Connector\Service\LengowLog;
use Lengow\Connector\Service\LengowExport;

/**
 * Class LengowExportController
 * @package Lengow\Connector\Storefront\Controller
 * @RouteScope(scopes={"storefront"})
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
     * @param SalesChannelContext $context SalesChannel context
     *
     * @Route("/lengow/export", name="frontend.lengow.export", methods={"GET"})
     *
     * @return Response
     */
    public function export(Request $request, SalesChannelContext $context): Response
    {
        $salesChannelName = $this->getSalesChannelName($request);
        if ($salesChannelName === null) {
            $errorMessage =  $this->lengowLog->decodeMessage(
                'log.export.specify_sales_channel',
                LengowTranslation::DEFAULT_ISO_CODE
            );
            return new Response($errorMessage, Response::HTTP_BAD_REQUEST);
        }
        $accessErrorMessage = $this->checkAccess($request);
        if ($accessErrorMessage !== null) {
            return new Response($accessErrorMessage, Response::HTTP_FORBIDDEN);
        }
        $exportArgs = $this->createGetArgArray($request);
        $this->lengowExport->init($exportArgs);
        if ($exportArgs['get_params']) {
            return new Response($this->lengowExport->getExportParams());
        }
        if ($exportArgs['mode']) {
            return new Response($this->modeSize($exportArgs['mode']));
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
            'mode' => $request->query->get('mode'),
            'format' => $request->query->get('format'),
            'stream' => $request->query->get('stream') !== null
                ? $request->query->get('stream') === '1'
                : null,
            'offset' => $request->query->get('offset') !== null ? (int) $request->query->get('offset') : null,
            'limit' => $request->query->get('limit') !== null ? (int) $request->query->get('limit') : null,
            'selection' => $request->query->get('selection') !== null
                ? $request->query->get('selection') === '1'
                : null,
            'out_of_stock' => $request->query->get('out_of_stock') !== null
                ? $request->query->get('out_of_stock') === '1'
                : null,
            'variation' => $request->query->get('variation') !== null
                ? $request->query->get('variation') === '1'
                : null,
            'inactive' => $request->query->get('inactive') !== null
                ? $request->query->get('inactive') === '1'
                : null,
            'product_ids' => $request->query->get('product_ids'),
            'sales_channel_id' => $request->query->get('sales_channel_id'),
            'currency' => $request->query->get('currency'),
            'log_output' => $request->query->get('log_output') !== null
                ? $request->query->get('log_output') === '1'
                : null,
            'update_export_date' => $request->query->get('update_export_date') !== null
                ? $request->query->get('update_export_date') === '1'
                : null,
            'get_params' => $request->query->get('get_params') !== null
                ? $request->query->get('get_params') === '1'
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
            return $this->lengowExport->getTotalExportedProduct();
        }
        if ($mode === 'total') {
            return $this->lengowExport->getTotalProduct();
        }
        return 0;
    }
}
