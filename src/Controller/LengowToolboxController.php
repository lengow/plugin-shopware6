<?php declare(strict_types=1);

namespace Lengow\Connector\Controller;

use Shopware\Core\Framework\Routing\Annotation\RouteScope;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\JsonResponse;
use Lengow\Connector\Service\LengowToolbox;

/**
 * Class LengowToolboxController
 * @package Lengow\Connector\Controller
 * @RouteScope(scopes={"api"})
 */
class LengowToolboxController extends AbstractController
{
    /**
     * @var LengowToolbox Lengow toolbox service
     */
    private $lengowToolbox;

    /**
     * LengowToolboxController constructor
     *
     * @param LengowToolbox $lengowToolbox Lengow toolbox service
     *
     */
    public function __construct(LengowToolbox $lengowToolbox )
    {
        $this->lengowToolbox = $lengowToolbox;
    }

    /**
     * Get overview data
     *
     * @Route("/api/v{version}/_action/lengow/toolbox/get-overview-data",
     *     name="api.action.lengow.toolbox.get-overview-data",
     *     methods={"GET"})
     *
     * @return JsonResponse
     */
    public function getOverviewData(): JsonResponse
    {
        return new JsonResponse([
            'checklist' => $this->lengowToolbox->getChecklistData(),
            'plugin' => $this->lengowToolbox->getPluginData(),
            'import' => $this->lengowToolbox->getImportData(),
            'export' => $this->lengowToolbox->getExportData(),
        ]);
    }

    /**
     * Get checksum data
     *
     * @Route("/api/v{version}/_action/lengow/toolbox/get-checksum-data",
     *     name="api.action.lengow.toolbox.get-checksum-data",
     *     methods={"GET"})
     *
     * @return JsonResponse
     */
    public function getChecksumData(): JsonResponse
    {
        return new JsonResponse($this->lengowToolbox->getChecksumData());
    }
}
