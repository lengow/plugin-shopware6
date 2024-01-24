<?php declare(strict_types=1);

namespace Lengow\Connector\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\JsonResponse;
use Lengow\Connector\Service\LengowToolbox;

/**
 * Class LengowToolboxController
 * @package Lengow\Connector\Controller
 * @Route(defaults={"_routeScope"={"api"}})
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
     * @Route("/api/_action/lengow/toolbox/get-overview-data",
     *     name="api.action.lengow.toolbox.get-overview-data",
     *     methods={"GET"})
     * @Route("/api/v{version}/_action/lengow/toolbox/get-overview-data",
     *     name="api.action.lengow.toolbox.get-overview-data-old",
     *     methods={"GET"})
     *
     * @return JsonResponse
     */
    public function getOverviewData(): JsonResponse
    {
        return new JsonResponse([
            LengowToolbox::CHECKLIST => $this->lengowToolbox->getData(LengowToolbox::DATA_TYPE_CHECKLIST),
            LengowToolbox::PLUGIN => $this->lengowToolbox->getData(LengowToolbox::DATA_TYPE_PLUGIN),
            LengowToolbox::SYNCHRONIZATION => $this->lengowToolbox->getData(LengowToolbox::DATA_TYPE_SYNCHRONIZATION),
            LengowToolbox::SHOPS => $this->lengowToolbox->getData(LengowToolbox::DATA_TYPE_SHOP),
        ]);
    }

    /**
     * Get checksum data
     *
     * @Route("/api/_action/lengow/toolbox/get-checksum-data",
     *     name="api.action.lengow.toolbox.get-checksum-data",
     *     methods={"GET"})
     * @Route("/api/v{version}/_action/lengow/toolbox/get-checksum-data",
     *     name="api.action.lengow.toolbox.get-checksum-data-old",
     *     methods={"GET"})
     *
     * @return JsonResponse
     */
    public function getChecksumData(): JsonResponse
    {
        return new JsonResponse($this->lengowToolbox->getData(LengowToolbox::DATA_TYPE_CHECKSUM));
    }

    /**
     * Get log data
     *
     * @Route("/api/_action/lengow/toolbox/get-log-data",
     *     name="api.action.lengow.toolbox.get-log-data",
     *     methods={"GET"})
     * @Route("/api/v{version}/_action/lengow/toolbox/get-log-data",
     *     name="api.action.lengow.toolbox.get-log-data-old",
     *     methods={"GET"})
     *
     * @return JsonResponse
     */
    public function getLogData(): JsonResponse
    {
        return new JsonResponse($this->lengowToolbox->getData(LengowToolbox::DATA_TYPE_LOG));
    }

    /**
     * Download log file individually or globally
     *
     * @Route("/api/_action/lengow/order/download-log",
     *     defaults={"auth_enabled"=true},
     *     name="api.action.lengow.toolbox.download-log",
     *     methods={"POST"})
     * @Route("/api/v{version}/_action/lengow/order/download-log",
     *     defaults={"auth_enabled"=true},
     *     name="api.action.lengow.toolbox.download-log-old",
     *     methods={"POST"})
     *
     * @param Request $request
     *
     * @return Response
     */
    public function downloadLog(Request $request): Response
    {
        $date = $request->get('date');
        $this->lengowToolbox->downloadLog($date === 'logs' ? null : $date);
        return new Response();
    }
}
