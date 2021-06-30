<?php declare(strict_types=1);

namespace Lengow\Connector\Controller;

use Shopware\Core\Framework\Routing\Annotation\RouteScope;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\JsonResponse;
use Lengow\Connector\Service\LengowSync;
use Lengow\Connector\Util\EnvironmentInfoProvider;

/**
 * Class LengowSyncController
 * @package Lengow\Connector\Controller
 * @RouteScope(scopes={"api"})
 */
class LengowSyncController extends AbstractController
{
    /**
     * @var LengowSync $lengowSync Lengow synchronisation service
     */
    private $lengowSync;

    /**
     * LengowSyncController constructor
     *
     * @param LengowSync $lengowSync lengow sync service
     */
    public function __construct(LengowSync $lengowSync)
    {
        $this->lengowSync = $lengowSync;
    }

    /**
     * Get plugin data
     *
     * @Route("/api/_action/lengow/sync/get-plugin-data",
     *      name="api.action.lengow.sync.get-plugin-data",
     *      methods={"GET"})
     * @Route("/api/v{version}/_action/lengow/sync/get-plugin-data",
     *      name="api.action.lengow.sync.get-plugin-data-old",
     *      methods={"GET"})
     *
     * @return JsonResponse
     */
    public function getPluginData(): JsonResponse
    {
        $pluginData = $this->lengowSync->getPluginData();
        if (!$pluginData) {
            return new JsonResponse(['success' => false]);
        }
        $response = [
            'success' => $pluginData !== null,
            'plugin_data' => $pluginData,
            'should_update' => version_compare(EnvironmentInfoProvider::PLUGIN_VERSION, $pluginData['version'], '<'),
        ];
        return new JsonResponse($response);
    }

    /**
     * Get Account data
     *
     * @Route("/api/_action/lengow/sync/get-account-status",
     *     name="api.action.lengow.sync.get-account-status",
     *     methods={"GET"})
     * @Route("/api/v{version}/_action/lengow/sync/get-account-status",
     *     name="api.action.lengow.sync.get-account-status-old",
     *     methods={"GET"})
     *
     * @param Request $request
     *
     * @return JsonResponse
     */
    public function getAccountStatus(Request $request): JsonResponse
    {
        if ($request->get('force') && $request->get('force') === 'true') {
            $accountStatus = $this->lengowSync->getAccountStatus(true);
        } else {
            $accountStatus = $this->lengowSync->getAccountStatus();
        }
        if (!$accountStatus) {
            return new JsonResponse(['success' => false]);
        }
        $response = array_merge([
                'success' => true,
            ],
            $accountStatus
        );
        return new JsonResponse($response);
    }
}
