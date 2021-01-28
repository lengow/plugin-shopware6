<?php declare(strict_types=1);

namespace Lengow\Connector\Controller;

use Shopware\Core\Framework\Routing\Annotation\RouteScope;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Lengow\Connector\Service\LengowCatalog;
use Lengow\Connector\Service\LengowConfiguration;
use Lengow\Connector\Service\LengowConnector;
use Lengow\Connector\Service\LengowLog;
use Lengow\Connector\Service\LengowSync;

/**
 * Class LengowConnectionController
 * @package Lengow\Connector\Controller
 * @RouteScope(scopes={"api"})
 */
class LengowConnectionController extends AbstractController
{
    /**
     * @var LengowCatalog Lengow catalog service
     */
    private $lengowCatalog;

    /**
     * @var LengowConfiguration Lengow configuration service
     */
    private $lengowConfiguration;

    /**
     * @var LengowConnector Lengow connector service
     */
    private $lengowConnector;

    /**
     * @var LengowLog Lengow log service
     */
    private $lengowLog;

    /**
     * @var LengowSync Lengow connector service
     */
    private $lengowSync;

    /**
     * LengowConnectionController constructor
     *
     * @param LengowCatalog $lengowCatalog Lengow catalog service
     * @param LengowConfiguration $lengowConfiguration Lengow configuration service
     * @param LengowConnector $lengowConnector Lengow connector service
     * @param LengowLog $lengowLog Lengow log service
     * @param LengowSync $lengowSync Lengow sync service
     */
    public function __construct(
        LengowCatalog $lengowCatalog,
        LengowConfiguration $lengowConfiguration,
        LengowConnector $lengowConnector,
        LengowLog $lengowLog,
        LengowSync $lengowSync
    )
    {
        $this->lengowCatalog = $lengowCatalog;
        $this->lengowConfiguration = $lengowConfiguration;
        $this->lengowConnector = $lengowConnector;
        $this->lengowLog = $lengowLog;
        $this->lengowSync = $lengowSync;
    }

    /**
     * Check API credentials and save them in Database
     *
     * @Route("/api/v{version}/_action/lengow/connection/check-api-credentials",
     *     name="api.action.lengow.connection.check-api-credentials",
     *     methods={"POST"})
     *
     * @param Request $request
     *
     * @return JsonResponse
     */
    public function checkApiCredentials(Request $request): JsonResponse
    {
        $accessIdsSaved = false;
        $accessToken = $request->get('accessToken');
        $secret = $request->get('secret');
        $accountId = $this->lengowConnector->getAccountIdByCredentials($accessToken, $secret);
        if ($accountId) {
            $accessIdsSaved = $this->lengowConfiguration->setAccessIds([
                LengowConfiguration::LENGOW_ACCOUNT_ID => $accountId,
                LengowConfiguration::LENGOW_ACCESS_TOKEN => $accessToken,
                LengowConfiguration::LENGOW_SECRET_TOKEN => $secret,
            ]);
        }
        return new JsonResponse([
            'success' => $accessIdsSaved,
        ]);
    }

    /**
     * Connect cms with Lengow
     *
     * @Route("/api/v{version}/_action/lengow/connection/connect-cms",
     *     name="api.action.lengow.connection.connect-cms",
     *     methods={"GET"})
     *
     * @return JsonResponse
     */
    public function connectCms(): JsonResponse
    {
        $cmsConnected = false;
        $cmsToken = $this->lengowConfiguration->getToken();
        $cmsExist = $this->lengowSync->syncCatalog(true);
        if (!$cmsExist) {
            $syncData = json_encode($this->lengowSync->getSyncData());
            $result = $this->lengowConnector->queryApi(LengowConnector::POST, LengowConnector::API_CMS, [], $syncData);
            if (isset($result->common_account)) {
                $cmsConnected = true;
                $messageKey = 'log.connection.cms_creation_success';
            } else {
                $messageKey = 'log.connection.cms_creation_failed';
            }
        } else {
            $messageKey = 'log.connection.cms_already_exist';
        }
        $this->lengowLog->write(
            LengowLog::CODE_CONNECTION,
            $this->lengowLog->encodeMessage($messageKey, [
                'cms_token' => $cmsToken,
            ])
        );
        // reset access ids if cms creation failed
        if (!$cmsExist && !$cmsConnected) {
            $this->lengowConfiguration->resetAccessIds();
        }
        return new JsonResponse([
            'success' => $cmsExist || $cmsConnected,
        ]);
    }

    /**
     * Get all catalogs available in Lengow
     *
     * @Route("/api/v{version}/_action/lengow/connection/get-catalog-list",
     *     name="api.action.lengow.connection.get-catalog-list",
     *     methods={"GET"})
     *
     * @return JsonResponse
     */
    public function getCatalogList(): JsonResponse
    {
        $LengowActiveSalesChannels = $this->lengowConfiguration->getLengowActiveSalesChannels();
        if (empty($LengowActiveSalesChannels)) {
            $catalogList = $this->lengowCatalog->getCatalogList();
        } else {
            // if cms already has one or more linked catalogs, nothing is done
            $catalogList = [];
        }
        return new JsonResponse($catalogList);
    }

    /**
     * Save catalogs linked in database and send data to Lengow with call API
     *
     * @Route("/api/v{version}/_action/lengow/connection/save-catalogs-linked",
     *     name="api.action.lengow.connection.save-catalogs-linked",
     *     methods={"POST"})
     *
     * @param Request $request
     *
     * @return JsonResponse
     */
    public function saveCatalogsLinked(Request $request): JsonResponse
    {
        $catalogsLinked = true;
        $catalogsBySalesChannels = [];
        $catalogSelected = $request->get('catalogSelected');
        if (!empty($catalogSelected)) {
            foreach ($catalogSelected as $catalog) {
                $catalogsBySalesChannels[$catalog['salesChannelId']][] = $catalog['catalogId'];
            }
        }
        if (!empty($catalogsBySalesChannels)) {
            // save catalogs ids and active sales channels in lengow configuration
            foreach ($catalogsBySalesChannels as $salesChannelId => $catalogIds) {
                $this->lengowConfiguration->setCatalogIds($catalogIds, $salesChannelId);
                $this->lengowConfiguration->setActiveSalesChannel($salesChannelId);
            }
            // save last update date for a specific settings (change synchronisation interval time)
            $this->lengowConfiguration->set(LengowConfiguration::LENGOW_LAST_SETTING_UPDATE, (string) time());
            // link all catalogs selected by API
            $catalogsLinked = $this->lengowCatalog->linkCatalogs($catalogsBySalesChannels);
            $messageKey = $catalogsLinked
                ? 'log.connection.link_catalog_success'
                : 'log.connection.link_catalog_failed';
            $this->lengowLog->write(LengowLog::CODE_CONNECTION, $this->lengowLog->encodeMessage($messageKey));
        }
        return new JsonResponse([
            'success' => $catalogsLinked,
        ]);
    }
}
