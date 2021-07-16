<?php declare(strict_types=1);

namespace Lengow\Connector\Storefront\Controller;

use Shopware\Core\Framework\Routing\Annotation\RouteScope;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\Request;
use Lengow\Connector\Service\LengowAccess;
use Lengow\Connector\Service\LengowConfiguration;
use Lengow\Connector\Service\LengowLog;
use Lengow\Connector\Service\LengowToolbox;
use Lengow\Connector\Service\LengowTranslation;

/**
 * Class LengowToolboxController
 * @package Lengow\Connector\Storefront\Controller
 * @RouteScope(scopes={"storefront"})
 */
class LengowToolboxController extends LengowAbstractFrontController
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
     * @var LengowToolbox Lengow toolbox service
     */
    protected $lengowToolbox;

    /**
     * LengowToolboxController constructor
     *
     * @param LengowAccess $lengowAccess Lengow access security service
     * @param LengowConfiguration $lengowConfiguration Lengow configuration accessor service
     * @param LengowLog $lengowLog Lengow log service
     * @param LengowToolbox $lengowToolbox Lengow toolbox service
     */
    public function __construct(
        LengowAccess $lengowAccess,
        LengowConfiguration $lengowConfiguration,
        LengowLog $lengowLog,
        LengowToolbox $lengowToolbox
    )
    {
        parent::__construct($lengowAccess, $lengowConfiguration, $lengowLog);
        $this->lengowToolbox = $lengowToolbox;
    }

    /**
     * Toolbox Process
     *
     * @param Request $request Http request
     *
     * @Route("/lengow/toolbox", name="frontend.lengow.toolbox", methods={"GET"})
     *
     * @return Response
     */
    public function toolbox(Request $request): Response
    {
        $accessErrorMessage = $this->checkAccess($request);
        if ($accessErrorMessage !== null) {
            return new Response($accessErrorMessage, Response::HTTP_FORBIDDEN);
        }
        $toolboxArgs = $this->createGetArgArray($request);
        // check if toolbox action is valid
        $action = $toolboxArgs[LengowToolbox::PARAM_TOOLBOX_ACTION] ?: LengowToolbox::ACTION_DATA;
        if (!$this->lengowToolbox->isToolboxAction($action)) {
            $errorMessage = $this->lengowLog->decodeMessage(
                'log.import.not_valid_action',
                LengowTranslation::DEFAULT_ISO_CODE,
                [
                    'action' => $action,
                ]
            );
            return new Response($errorMessage, Response::HTTP_BAD_REQUEST);
        }
        switch ($action) {
            case LengowToolbox::ACTION_LOG:
                $this->lengowToolbox->downloadLog($toolboxArgs[LengowToolbox::PARAM_DATE]);
                break;
            default:
                $toolboxData = $this->lengowToolbox->getData($toolboxArgs[LengowToolbox::PARAM_TYPE]);
                return new Response(json_encode($toolboxData));
        }
        return new Response();
    }

    /**
     * Get all parameters from request
     * List params
     * string toolbox_action toolbox specific action
     * string type           type of data to display
     * string date           date of the log to export
     *
     * @param Request $request Http request
     *
     * @return array
     */
    protected function createGetArgArray(Request $request): array
    {
        return [
            LengowToolbox::PARAM_TOOLBOX_ACTION => $request->query->get(LengowToolbox::PARAM_TOOLBOX_ACTION),
            LengowToolbox::PARAM_TYPE => $request->query->get(LengowToolbox::PARAM_TYPE),
            LengowToolbox::PARAM_DATE => $request->query->get(LengowToolbox::PARAM_DATE),
        ];
    }
}
