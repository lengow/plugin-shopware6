<?php declare(strict_types=1);

namespace Lengow\Connector\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Lengow\Connector\Service\LengowCatalog;
use Lengow\Connector\Service\LengowConfiguration;
use Lengow\Connector\Service\LengowConnector;
use Lengow\Connector\Service\LengowLog;
use Lengow\Connector\Service\LengowSync;

#[Route(defaults: ['_routeScope' => ['api']])]
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

    //Check API credentials and save them in Database
    #[Route('/api/_action/lengow/connection/check-api-credentials', name: 'api.action.lengow.connection.check-api-credentials', methods: ['POST'])]
    #[Route('/api/v{version}/_action/lengow/connection/FF', name: 'api.action.lengow.connection.check-api-credentials-old', methods: ['POST'])]
    public function checkApiCredentials(Request $request): JsonResponse
    {
        $accessIdsSaved = false;
        $accessToken = $request->get('accessToken');
        $secret = $request->get('secret');
        $accountId = $this->lengowConnector->getAccountIdByCredentials($accessToken, $secret);
        if ($accountId) {
            $accessIdsSaved = $this->lengowConfiguration->setAccessIds([
                LengowConfiguration::ACCOUNT_ID => $accountId,
                LengowConfiguration::ACCESS_TOKEN => $accessToken,
                LengowConfiguration::SECRET => $secret,
            ]);
        }
        return new JsonResponse([
            'success' => $accessIdsSaved,
        ]);
    }

    //Connect cms with Lengow
    #[Route('/api/_action/lengow/connection/connect-cms', name: 'api.action.lengow.connection.connect-cms', methods: ['GET'])]
    #[Route('/api/v{version}/_action/lengow/connection/connect-cms', name: 'api.action.lengow.connection.connect-cms-old', methods: ['GET'])]
    public function connectCms(): JsonResponse
    {
        $cmsConnected = false;
        $cmsToken = $this->lengowConfiguration->getToken();
        $cmsExist = $this->lengowSync->syncCatalog(true);
        if (!$cmsExist) {
            $syncData = json_encode($this->lengowSync->getSyncData());
            $result = $this->lengowConnector->queryApi(LengowConnector::POST, LengowConnector::API_CMS, [], $syncData);
            if ($result === null) {
                // Some API versions return an empty body on successful CMS creation.
                $cmsConnected = true;
                $this->waitForCmsVisibility();
                $messageKey = 'log.connection.cms_creation_success';
            } elseif ($this->isCmsCreationSuccessful($result, $cmsToken)) {
                $cmsConnected = true;
                $messageKey = 'log.connection.cms_creation_success';
            } else {
                $this->lengowLog->write(
                    LengowLog::CODE_CONNECTION,
                    'connect-cms POST response (attempt 1): ' . $this->stringifyApiResult($result)
                );
                // Some API versions may return a different POST payload while still creating the CMS.
                // Re-check via GET /cms before considering the connection as failed.
                $cmsConnected = $this->waitForCmsVisibility();
                if (!$cmsConnected) {
                    $this->lengowLog->write(
                        LengowLog::CODE_CONNECTION,
                        'connect-cms GET /cms fallback after attempt 1 did not find token ' . $cmsToken
                    );
                    // Token collisions can happen when a previously created token exists remotely.
                    // Regenerate the CMS token and retry once with a fresh payload.
                    $cmsToken = $this->lengowConfiguration->generateToken();
                    $syncData = json_encode($this->lengowSync->getSyncData());
                    $result = $this->lengowConnector->queryApi(LengowConnector::POST, LengowConnector::API_CMS, [], $syncData);
                    if ($this->isCmsCreationSuccessful($result, $cmsToken)) {
                        $cmsConnected = true;
                    } else {
                        $this->lengowLog->write(
                            LengowLog::CODE_CONNECTION,
                            'connect-cms POST response (attempt 2): ' . $this->stringifyApiResult($result)
                        );
                        // If the API returns null here too, we also treat it as successful creation.
                        if ($result === null) {
                            $cmsConnected = true;
                            $this->waitForCmsVisibility();
                        } else {
                            $cmsConnected = $this->waitForCmsVisibility();
                        }
                        if (!$cmsConnected) {
                            $this->lengowLog->write(
                                LengowLog::CODE_CONNECTION,
                                'connect-cms GET /cms fallback after attempt 2 did not find token ' . $cmsToken
                            );
                        }
                    }
                }
                $messageKey = $cmsConnected
                    ? 'log.connection.cms_creation_success'
                    : 'log.connection.cms_creation_failed';
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
        // Keep API credentials even if CMS creation fails so the merchant can retry
        // without re-entering credentials. Only reset temporary authorization token.
        if (!$cmsExist && !$cmsConnected) {
            $this->lengowConfiguration->resetAuthorizationToken();
        }
        return new JsonResponse([
            'success' => $cmsExist || $cmsConnected,
        ]);
    }

    //Get all catalogs available in Lengow
    #[Route('/api/_action/lengow/connection/get-catalog-list', name: 'api.action.lengow.connection.get-catalog-list', methods: ['GET'])]
    #[Route('/api/v{version}/_action/lengow/connection/get-catalog-list', name: 'api.action.lengow.connection.get-catalog-list-old', methods: ['GET'])]
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

    //Save catalogs linked in database and send data to Lengow with call API
    #[Route('/api/_action/lengow/connection/save-catalogs-linked', name: 'api.action.lengow.connection.save-catalogs-linked', methods: ['POST'])]
    #[Route('/api/v{version}/_action/lengow/connection/save-catalogs-linked', name: 'api.action.lengow.connection.save-catalogs-linked-old', methods: ['POST'])]
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
            $this->lengowConfiguration->set(LengowConfiguration::LAST_UPDATE_SETTING, (string) time());
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

    /**
     * A CMS creation response is considered successful when it contains either a known account field
     * or a token matching the CMS token sent by the plugin.
     *
     * @param mixed $result
     * @param string $cmsToken
     *
     * @return bool
     */
    private function isCmsCreationSuccessful($result, string $cmsToken): bool
    {
        if (!is_object($result) && !is_array($result)) {
            return false;
        }

        if ($this->hasResultField($result, 'error')) {
            return false;
        }

        if ($this->hasResultField($result, 'common_account')
            || $this->hasResultField($result, 'commonAccount')
            || $this->hasResultField($result, 'account_id')
            || $this->hasResultField($result, 'accountId')
            || $this->hasResultField($result, 'id')
        ) {
            return true;
        }

        $resultToken = $this->getResultField($result, 'token');
        if (is_string($resultToken) && $resultToken === $cmsToken) {
            return true;
        }

        // Defensive fallback for future API payload variants: a non-error object
        // that looks like a CMS payload should be treated as a successful creation.
        return $this->hasResultField($result, 'shops')
            && $this->hasResultField($result, 'domain_name');
    }

    /**
     * @param mixed $result
     * @param string $field
     */
    private function hasResultField($result, string $field): bool
    {
        if (is_array($result)) {
            return array_key_exists($field, $result);
        }

        return is_object($result) && property_exists($result, $field);
    }

    /**
     * @param mixed $result
     * @param string $field
     *
     * @return mixed|null
     */
    private function getResultField($result, string $field)
    {
        if (is_array($result) && array_key_exists($field, $result)) {
            return $result[$field];
        }

        if (is_object($result) && property_exists($result, $field)) {
            return $result->$field;
        }

        return null;
    }

    /**
     * @param mixed $result
     */
    private function stringifyApiResult($result): string
    {
        if ($result === false) {
            return 'false';
        }

        if ($result === null) {
            return 'null';
        }

        if (is_string($result)) {
            return $result;
        }

        if (is_array($result) || is_object($result)) {
            $encoded = json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

            return $encoded !== false ? $encoded : '[json_encode_failed]';
        }

        return (string) $result;
    }

    /**
     * API write/read can be eventually consistent for a short duration.
     * We retry visibility checks to avoid false negatives right after POST.
     */
    private function waitForCmsVisibility(): bool
    {
        $maxAttempts = 3;
        $sleepMicroseconds = 400000;
        for ($attempt = 0; $attempt < $maxAttempts; $attempt++) {
            if ($this->lengowSync->syncCatalog(true)) {
                return true;
            }
            usleep($sleepMicroseconds);
        }

        return false;
    }
}
