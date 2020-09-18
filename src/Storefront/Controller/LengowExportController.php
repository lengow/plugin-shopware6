<?php declare(strict_types=1);

namespace Lengow\Connector\Storefront\Controller;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Routing\Annotation\RouteScope;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Controller\StorefrontController;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
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
     * LengowExportController constructor.
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
    ) {
        parent::__construct($lengowAccess, $lengowConfiguration, $lengowLog);
        $this->lengowExport = $lengowExport;
    }

    /**
     * @param Request $request Http request
     * @param SalesChannelContext $context SalesChannel context
     *
     * @Route("/lengow/export", name="frontend.lengow.export", methods={"GET"})
     * @return Response
     */
    public function export(Request $request, SalesChannelContext $context)
    {
        $this->checkAccess($request, $context, false);
        $exportArgs = $this->createGetArgArray($request);

        if ($exportArgs['get_params']) {
            return new Response($this->lengowExport->getExportParams());
        }

        $this->lengowExport->init(
            $exportArgs['sales_channel_id'],
            $exportArgs['selection'],
            $exportArgs['out_of_stock'],
            $exportArgs['variation'],
            $exportArgs['inactive'],
            $exportArgs['product_ids'] ?: ''
        );

        if ($exportArgs['mode']) {
            return new Response($this->modeSize($exportArgs['mode'], $exportArgs['sales_channel_id'] ));
        }

        // TODO handle export here
        die(var_dump($exportArgs));
    }

    /**
     * @param Request $request Http request
     *
     * @return array all get args
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
            'out_of_stock' => (bool) $request->query->get('out_of_stock') === '1',
            'variation' => (bool) $request->query->get('variation') === '1',
            'inactive' => (bool) $request->query->get('inactive') === '1',
            'selection' => (bool) $request->query->get('selection'),
            'log_output' => $request->query->get('log_output') === '1',
            'update_export_date' => $request->query->get('update_export_date') === '1',
            'currency' => $request->query->get('currency'),
            'sales_channel_id' => $request->query->get('sales_channel_id'),
            'get_params' => (bool) $request->query->get('get_params'),
        ];
    }

    /**
     * @param string $mode size mode
     * @param string $salesChannelId sales channel id to size
     * @return int size
     */
    protected function modeSize(string $mode, string $salesChannelId): int
    {
        if ($mode === 'size') {
            return count($this->lengowExport->getProductIdsExport($salesChannelId));
        } else if ($mode === 'total') {
            return $this->lengowExport->getTotalExport($salesChannelId);
        }
        return 0;
    }
}
