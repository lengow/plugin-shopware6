<?php declare(strict_types=1);

namespace Lengow\Connector\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\JsonResponse;
use Lengow\Connector\Service\LengowToolbox;

#[Route(defaults: ['_routeScope' => ['api']])]
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

    #[Route('/api/_action/lengow/toolbox/get-overview-data', name: 'api.action.lengow.toolbox.get-overview-data', methods: ['GET'], description: 'Get overview data')]
    #[Route('/api/v{version}/_action/lengow/toolbox/get-overview-data', name: 'api.action.lengow.toolbox.get-overview-data-old', methods: ['GET'])]
    public function getOverviewData(): JsonResponse
    {
        return new JsonResponse([
            LengowToolbox::CHECKLIST => $this->lengowToolbox->getData(LengowToolbox::DATA_TYPE_CHECKLIST),
            LengowToolbox::PLUGIN => $this->lengowToolbox->getData(LengowToolbox::DATA_TYPE_PLUGIN),
            LengowToolbox::SYNCHRONIZATION => $this->lengowToolbox->getData(LengowToolbox::DATA_TYPE_SYNCHRONIZATION),
            LengowToolbox::SHOPS => $this->lengowToolbox->getData(LengowToolbox::DATA_TYPE_SHOP),
        ]);
    }

    #[Route('/api/_action/lengow/toolbox/get-checksum-data', name: 'api.action.lengow.toolbox.get-checksum-data', methods: ['GET'], description: 'Get checksum data')]
    #[Route('/api/v{version}/_action/lengow/toolbox/get-checksum-data', name: 'api.action.lengow.toolbox.get-checksum-data-old', methods: ['GET'])]
    public function getChecksumData(): JsonResponse
    {
        return new JsonResponse($this->lengowToolbox->getData(LengowToolbox::DATA_TYPE_CHECKSUM));
    }

    #[Route('/api/_action/lengow/toolbox/get-log-data', name: 'api.action.lengow.toolbox.get-log-data', methods: ['GET'], description: 'Get log data')]
    #[Route('/api/v{version}/_action/lengow/toolbox/get-log-data', name: 'api.action.lengow.toolbox.get-log-data-old', methods: ['GET'])]
    public function getLogData(): JsonResponse
    {
        return new JsonResponse($this->lengowToolbox->getData(LengowToolbox::DATA_TYPE_LOG));
    }

    #[Route('/api/_action/lengow/order/download-log', defaults: ['auth_enabled' => true], name: 'api.action.lengow.toolbox.download-log', methods: ['POST'], description: 'Download log file individually or globally')]
    #[Route('/api/v{version}/_action/lengow/order/download-log', defaults: ['auth_enabled' => true], name: 'api.action.lengow.toolbox.download-log-old', methods: ['POST'])]
    public function downloadLog(Request $request): Response
    {
        $date = $request->get('date');
        $this->lengowToolbox->downloadLog($date === 'logs' ? null : $date);
        return new Response();
    }
}
