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
     * Get all toolbox data
     *
     * @Route("/api/v{version}/_action/lengow/toolbox/get-all-data",
     *     name="api.action.lengow.toolbox.get-all-data",
     *     methods={"GET"})
     *
     * @return JsonResponse
     */
    public function getAllData(): JsonResponse
    {
        return new JsonResponse($this->lengowToolbox->getAllData());
    }
}
