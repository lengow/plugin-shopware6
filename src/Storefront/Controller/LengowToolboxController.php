<?php declare(strict_types=1);

namespace Lengow\Connector\Storefront\Controller;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\Request;
use Lengow\Connector\Service\LengowAccess;
use Lengow\Connector\Service\LengowConfiguration;
use Lengow\Connector\Service\LengowConnector;
use Lengow\Connector\Service\LengowLog;
use Lengow\Connector\Service\LengowToolbox;
use Lengow\Connector\Service\LengowTranslation;

/**
 * Class LengowToolboxController
 * @package Lengow\Connector\Storefront\Controller
 * @Route(defaults={"_routeScope"={"storefront"}})
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
        $action = $toolboxArgs[LengowToolbox::PARAM_TOOLBOX_ACTION];
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
            case LengowToolbox::ACTION_ORDER:
                if ($toolboxArgs[LengowToolbox::PARAM_PROCESS] === LengowToolbox::PROCESS_TYPE_GET_DATA) {
                    $result = $this->lengowToolbox->getOrderData(
                        $toolboxArgs[LengowToolbox::PARAM_MARKETPLACE_SKU],
                        $toolboxArgs[LengowToolbox::PARAM_MARKETPLACE_NAME],
                        $toolboxArgs[LengowToolbox::PARAM_TYPE]
                    );
                } else {
                    $result = $this->lengowToolbox->syncOrders($toolboxArgs);
                }
                $responseCode = Response::HTTP_OK;
                if (isset($result[LengowToolbox::ERRORS][LengowToolbox::ERROR_CODE])) {
                    $errorCode = $result[LengowToolbox::ERRORS][LengowToolbox::ERROR_CODE];
                    $responseCode = $errorCode === LengowConnector::CODE_404
                        ? Response::HTTP_NOT_FOUND
                        : Response::HTTP_FORBIDDEN;
                }
                return new Response(json_encode($result), $responseCode);
            default:
                $toolboxData = $this->lengowToolbox->getData($toolboxArgs[LengowToolbox::PARAM_TYPE]);
                return new Response(json_encode($toolboxData));
        }
        return new Response();
    }

    /**
     * Get all parameters from request
     * List params
     * string  toolbox_action   Toolbox specific action
     * string  type             Type of data to display
     * string  created_from     Synchronization of orders since
     * string  created_to       Synchronization of orders until
     * string  date             Log date to download
     * string  marketplace_name Lengow marketplace name to synchronize
     * string  marketplace_sku  Lengow marketplace order id to synchronize
     * string  process          Type of process for order action
     * bool    force            Force synchronization order even if there are errors (1) or not (0)
     * int     shop_id          Shop id to synchronize
     * int     days             Synchronization interval time
     *
     * @param Request $request Http request
     *
     * @return array
     */
    protected function createGetArgArray(Request $request): array
    {
        return [
            LengowToolbox::PARAM_TOOLBOX_ACTION => $request->query->get(
                LengowToolbox::PARAM_TOOLBOX_ACTION,
                LengowToolbox::ACTION_DATA
            ),
            LengowToolbox::PARAM_PROCESS => $request->query->get(
                LengowToolbox::PARAM_PROCESS,
                LengowToolbox::PROCESS_TYPE_SYNC
            ),
            LengowToolbox::PARAM_TYPE => $request->query->get(LengowToolbox::PARAM_TYPE, LengowToolbox::DATA_TYPE_CMS),
            LengowToolbox::PARAM_DATE => $request->query->get(LengowToolbox::PARAM_DATE),
            LengowToolbox::PARAM_CREATED_TO => $request->query->get(LengowToolbox::PARAM_CREATED_TO),
            LengowToolbox::PARAM_CREATED_FROM => $request->query->get(LengowToolbox::PARAM_CREATED_FROM),
            LengowToolbox::PARAM_DAYS => $request->query->get(LengowToolbox::PARAM_DAYS),
            LengowToolbox::PARAM_FORCE => $request->query->get(LengowToolbox::PARAM_FORCE),
            LengowToolbox::PARAM_MARKETPLACE_NAME => $request->query->get(LengowToolbox::PARAM_MARKETPLACE_NAME),
            LengowToolbox::PARAM_MARKETPLACE_SKU => $request->query->get(LengowToolbox::PARAM_MARKETPLACE_SKU),
            LengowToolbox::PARAM_SHOP_ID => $request->query->get(LengowToolbox::PARAM_SHOP_ID),
        ];
    }
}
