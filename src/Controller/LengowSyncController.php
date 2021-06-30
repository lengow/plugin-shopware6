<?php declare(strict_types=1);

namespace Lengow\Connector\Controller;

use Shopware\Core\Framework\Routing\Annotation\RouteScope;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\JsonResponse;
use Lengow\Connector\Service\LengowConfiguration;
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
     * @var LengowConfiguration $lengowConfiguration Lengow configuration service
     */
    private $lengowConfiguration;

    /**
     * @var LengowSync $lengowSync Lengow synchronisation service
     */
    private $lengowSync;

    /**
     * @var EnvironmentInfoProvider Environment info provider utility
     */
    private $environmentInfoProvider;

    /**
     * LengowSyncController constructor
     *
     * @param LengowConfiguration $lengowConfiguration Lengow configuration service
     * @param LengowSync $lengowSync lengow sync service
     * @param EnvironmentInfoProvider $environmentInfoProvider Environment info provider utility
     */
    public function __construct(
        LengowConfiguration $lengowConfiguration,
        LengowSync $lengowSync,
        EnvironmentInfoProvider $environmentInfoProvider
    )
    {
        $this->lengowConfiguration = $lengowConfiguration;
        $this->lengowSync = $lengowSync;
        $this->environmentInfoProvider = $environmentInfoProvider;
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
        $newVersionIsAvailable = version_compare(EnvironmentInfoProvider::PLUGIN_VERSION, $pluginData['version'], '<');
        $pluginData['new_version_is_available'] = $newVersionIsAvailable;
        $pluginData['show_update_modal'] = $newVersionIsAvailable && $this->showUpdateModal();
        $pluginData['links'] = $this->lengowSync->getPluginLinks($this->environmentInfoProvider->getLocaleCode());
        return new JsonResponse([
            'success' => true,
            'plugin_data' => $pluginData,
        ]);
    }

    /**
     * Get plugin links
     *
     * @Route("/api/_action/lengow/sync/get-plugin-links",
     *      name="api.action.lengow.sync.get-plugin-links",
     *      methods={"GET"})
     * @Route("/api/v{version}/_action/lengow/sync/get-plugin-links",
     *      name="api.action.lengow.sync.get-plugin-links-old",
     *      methods={"GET"})
     *
     * @return JsonResponse
     */
    public function getPluginLinks(): JsonResponse
    {
        return new JsonResponse([
            'success' => true,
            'links' => $this->lengowSync->getPluginLinks($this->environmentInfoProvider->getLocaleCode()),
        ]);
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
        $accountStatus['success'] = true;
        return new JsonResponse($accountStatus);
    }

    /**
     * Set back the display date of the update modal by 7 days
     *
     * @Route("/api/v{version}/_action/lengow/sync/remind-me-later",
     *     name="api.action.lengow.sync.remind-me-later",
     *     methods={"GET"})
     * @Route("/api/_action/lengow/sync/remind-me-later",
     *     name="api.action.lengow.sync.remind-me-later-old",
     *     methods={"GET"})
     *
     * @return JsonResponse
     */
    public function remindMeLater(): JsonResponse
    {
        $timestamp = time() + (7 * 86400);
        $this->lengowConfiguration->set(LengowConfiguration::LAST_UPDATE_PLUGIN_MODAL, (string) $timestamp);
        return new JsonResponse(['success' => true]);
    }

    /**
     * Checks if the plugin update modal should be displayed or not
     *
     * @return bool
     */
    private function showUpdateModal(): bool
    {
        $updatedAt = $this->lengowConfiguration->get(LengowConfiguration::LAST_UPDATE_PLUGIN_MODAL);
        if ($updatedAt !== null && (time() - (int) $updatedAt) < 86400) {
            return false;
        }
        $this->lengowConfiguration->set(LengowConfiguration::LAST_UPDATE_PLUGIN_MODAL, (string) time());
        return true;
    }
}
