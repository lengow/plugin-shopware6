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
        $salesChannelName = $this->checkAccess($request, $context, false);
        $exportArgs = $this->createGetArgArray($request);
        if ($exportArgs['get_params']) {
            return new Response($this->lengowExport->getExportParams());
        }
        if ($exportArgs['mode']) {
            return new Response($this->modeSize($exportArgs['mode'], $exportArgs['sales_channel_id'] ));
        }
        if (!$this->lengowExport->exec($salesChannelName, $exportArgs)) {
            $this->lengowLog->write(
                LengowLog::CODE_EXPORT,
                $this->lengowLog->encodeMessage('log.export.export_failed'),
                $exportArgs['log_output']
            );
        } else {
            $this->lengowLog->write(
                LengowLog::CODE_EXPORT,
                $this->lengowLog->encodeMessage('log.export.end', [
                    'sales_channel_name' => $salesChannelName,
                ]),
                $exportArgs['log_output']
            );
        }
        return new Response();
    }

    /**
     * @param Request $request Http request
     *
     * @return array all get args
     */
    protected function createGetArgArray(Request $request): array
    {
        return [
            'sales_channel_id' => $request->query->get('sales_channel_id'),
            'format' => $request->query->get('format') ?? '',
            'mode' => $request->query->get('mode'),
            'stream' => $request->query->get('stream') === '1',
            'product_ids' => $request->query->get('product_ids'),
            'limit' => (int) $request->query->get('limit'),
            'offset' => (int) $request->query->get('offset'),
            'out_of_stock' => (null !== $request->query->get('out_of_stock'))
                ? ($request->query->get('out_of_stock') === '1')
                : $this->lengowConfiguration->get(
                    LengowConfiguration::LENGOW_EXPORT_OUT_OF_STOCK_ENABLED,
                    $request->query->get('sales_channel_id')
                ) ?? false,
            'variation' => (null !== $request->query->get('variation'))
                ? ($request->query->get('variation') === '1')
                : $this->lengowConfiguration->get(
                    LengowConfiguration::LENGOW_EXPORT_VARIATION_ENABLED,
                    $request->query->get('sales_channel_id')
                ) ?? true,
            'inactive' => (null !== $request->query->get('inactive'))
                ? ($request->query->get('inactive') === '1')
                : $this->lengowConfiguration->get(
                    LengowConfiguration::LENGOW_EXPORT_DISABLED_PRODUCT,
                    $request->query->get('sales_channel_id')
                ) ?? false,
            'selection' => (null !== $request->query->get('selection'))
                ? ($request->query->get('selection') === '1')
                : $this->lengowConfiguration->get(
                    LengowConfiguration::LENGOW_EXPORT_SELECTION_ENABLED,
                    $request->query->get('sales_channel_id')
                ) ?? false,
            'log_output' => $request->query->get('log_output') === '1',
            'update_export_date' => $request->query->get('update_export_date') === '1',
            'currency' => $request->query->get('currency'),
            'get_params' => $request->query->get('get_params') === '1',
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
