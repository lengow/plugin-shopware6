<?php declare(strict_types=1);

namespace Lengow\Connector\Service;

use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Context;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Lengow\Connector\Exception\LengowException;
use Lengow\Connector\Factory\LengowFileFactory;
use Lengow\Connector\Util\EnvironmentInfoProvider;

/**
 * Class LengowSync
 * @package Lengow\Connector\Service
 */
class LengowSync
{
    /**
     * @var string sync catalog action
     */
    public const SYNC_CATALOG = 'catalog';

    /**
     * @var string sync cms option action
     */
    public const SYNC_CMS_OPTION = 'cms_option';

    /**
     * @var string sync status account action
     */
    public const SYNC_STATUS_ACCOUNT = 'status_account';

    /**
     * @var string sync marketplace action
     */
    public const SYNC_MARKETPLACE = 'marketplace';

    /**
     * @var string sync order action
     */
    public const SYNC_ORDER = 'order';

    /**
     * @var string sync action action
     */
    public const SYNC_ACTION = 'action';

    /**
     * @var string sync plugin version action
     */
    public const SYNC_PLUGIN_DATA = 'plugin';

    /**
     * @var string name of logs folder
     */
    private const CONFIG_FOLDER_NAME = 'Config';

    /**
     * @var string name of logs folder
     */
    private const MARKETPLACE_FILE = 'marketplaces.json';

    /**
     * @var string plugin type for lengow
     */
    private const PLUGIN_TYPE = 'shopware';

    /**
     * @var LengowConnector Lengow connector service
     */
    private $lengowConnector;

    /**
     * @var LengowConfiguration Lengow configuration service
     */
    private $lengowConfiguration;

    /**
     * @var LengowLog Lengow log service
     */
    private $lengowLog;

    /**
     * @var LengowFileFactory Lengow file factory
     */
    private $lengowFileFactory;

    /**
     * @var EnvironmentInfoProvider Environment info provider utility
     */
    private $environmentInfoProvider;

    /**
     * @var LengowExport Lengow export service
     */
    private $lengowExport;

    /**
     * @var EntityRepositoryInterface $salesChannelRepository sales channel repository
     */
    private $salesChannelRepository;

    /**
     * @var array cache time for catalog, account status, cms options and marketplace synchronisation
     */
    private $cacheTimes = array(
        self::SYNC_CATALOG => 21600,
        self::SYNC_CMS_OPTION => 86400,
        self::SYNC_STATUS_ACCOUNT => 86400,
        self::SYNC_MARKETPLACE => 43200,
        self::SYNC_PLUGIN_DATA => 86400,
    );

    /**
     * LengowImportOrder Construct
     *
     * @param LengowConnector $lengowConnector Lengow connector service
     * @param LengowConfiguration $lengowConfiguration Lengow configuration service
     * @param LengowLog $lengowLog Lengow log service
     * @param LengowFileFactory $lengowFileFactory Lengow file factory
     * @param EnvironmentInfoProvider $environmentInfoProvider Environment info provider utility
     * @param LengowExport $lengowExport Lengow export service
     * @param EntityRepositoryInterface $salesChannelRepository shopware sales channel repository
     */
    public function __construct(
        LengowConnector $lengowConnector,
        LengowConfiguration $lengowConfiguration,
        LengowLog $lengowLog,
        LengowFileFactory $lengowFileFactory,
        EnvironmentInfoProvider $environmentInfoProvider,
        LengowExport $lengowExport,
        EntityRepositoryInterface $salesChannelRepository
    )
    {
        $this->lengowConnector = $lengowConnector;
        $this->lengowConfiguration = $lengowConfiguration;
        $this->lengowLog = $lengowLog;
        $this->lengowFileFactory = $lengowFileFactory;
        $this->environmentInfoProvider = $environmentInfoProvider;
        $this->lengowExport = $lengowExport;
        $this->salesChannelRepository = $salesChannelRepository;
    }

    /**
     * Get lengow sync data
     *
     * @return array
     */
    public function getSyncData() : array
    {
        $syncData = [
            'domain_name' => $this->environmentInfoProvider->getBaseUrl(),
            'token' => $this->lengowConfiguration->get('lengowGlobalToken'),
            'type' => self::PLUGIN_TYPE,
            'version' => $this->environmentInfoProvider->getVersion(),
            'plugin_version' => $this->environmentInfoProvider::getPluginVersion(),
            'email' => $this->lengowConfiguration->get('core.basicInformation.email'),
            'cron_url' => $this->lengowConfiguration->getCronUrl(),
            'return_url' => $this->environmentInfoProvider->getBaseUrl() . '/admin/lengow/connector/dashboard',
            'shops' => [],
        ];
        $salesChannels = $this->environmentInfoProvider->getActiveSalesChannels();
        if ($salesChannels->count() !== 0 ) {
            foreach ($salesChannels as $salesChannel) {
                $salesChannelId = $salesChannel->getId();
                $salesChannelToken = $this->lengowConfiguration->get('lengowChannelToken', $salesChannelId);
                $domainUrl = $this->environmentInfoProvider->getBaseUrl($salesChannelId);
                $this->lengowExport->init($salesChannelId);
                $syncData['shops'][$salesChannelId] = [
                    'token' => $salesChannelToken,
                    'shop_name' => $salesChannel->getName(),
                    'domain_url' => $domainUrl,
                    'feed_url' => $this->lengowConfiguration->getFeedUrl($salesChannelId),
                    'total_product_number' => $this->lengowExport->getTotalProduct(),
                    'exported_product_number' => $this->lengowExport->getTotalExportedProduct(),
                    'enabled' => $this->lengowConfiguration->get('lengowStoreEnabled', $salesChannelId),
                ];
            }
        }
        return $syncData;
    }

    /**
     * Sync Lengow catalogs for order synchronisation
     *
     * @param bool $force force cache Update
     * @param bool $logOutput see log or not
     *
     * @return bool
     */
    public function syncCatalog(bool $force = false, bool $logOutput = false): bool
    {
        $settingUpdated = false;
        if ($this->lengowConfiguration->isNewMerchant()) {
            return false;
        }
        if (!$force) {
            $updatedAt = $this->lengowConfiguration->get(LengowConfiguration::LENGOW_CATALOG_UPDATE);
            if ($updatedAt !== null && (time() - (int) $updatedAt) < $this->cacheTimes[self::SYNC_CATALOG]) {
                return false;
            }
        }
        $result = $this->lengowConnector->queryApi(LengowConnector::GET, LengowConnector::API_CMS, [], '', $logOutput);
        if (isset($result->cms)) {
            $cmsToken = $this->lengowConfiguration->getToken();
            foreach ($result->cms as $cms) {
                if ($cms->token !== $cmsToken) {
                    continue;
                }
                foreach ($cms->shops as $cmsShop) {
                    $salesChannel = $this->lengowConfiguration->getSalesChannelByToken($cmsShop->token);
                    if ($salesChannel === null) {
                        continue;
                    }
                    $salesChannelId = $salesChannel->getId();
                    $idsChange = $this->lengowConfiguration->setCatalogIds($cmsShop->catalog_ids, $salesChannelId);
                    $salesChannelChange = $this->lengowConfiguration->setActiveSalesChannel($salesChannelId);
                    if (!$settingUpdated && ($idsChange || $salesChannelChange)) {
                        $settingUpdated = true;
                    }
                }
                break;
            }
        }
        // save last update date for a specific settings (change synchronisation interval time)
        if ($settingUpdated) {
            $this->lengowConfiguration->set(LengowConfiguration::LENGOW_LAST_SETTING_UPDATE, (string) time());
        }
        $this->lengowConfiguration->set(LengowConfiguration::LENGOW_CATALOG_UPDATE, (string) time());
        return true;
    }

    /**
     * Get marketplace data
     *
     * @param bool $force force cache update
     * @param bool $logOutput see log or not
     *
     * @return mixed|false
     */
    public function getMarketplaces(bool $force = false, bool $logOutput = false)
    {
        $sep = DIRECTORY_SEPARATOR;
        $filePath = $this->environmentInfoProvider->getPluginPath()
            . $sep . self::CONFIG_FOLDER_NAME . $sep . self::MARKETPLACE_FILE;
        if (!$force) {
            $updatedAt = $this->lengowConfiguration->get(LengowConfiguration::LENGOW_MARKETPLACE_UPDATE);
            if ($updatedAt !== null
                && (time() - (int)$updatedAt) < $this->cacheTimes[self::SYNC_MARKETPLACE]
                && file_exists($filePath)
            ) {
                // recovering data with the marketplaces.json file
                $marketplacesData = file_get_contents($filePath);
                if ($marketplacesData) {
                    return json_decode($marketplacesData, false);
                }
            }
        }
        // recovering data with the API
        $result = $this->lengowConnector->queryApi(
            LengowConnector::GET,
            LengowConnector::API_MARKETPLACE,
            [],
            '',
            $logOutput
        );
        if ($result && is_object($result) && !isset($result->error)) {
            // updated marketplaces.json file
            try {
                $marketplaceFile = $this->lengowFileFactory->create(
                    self::CONFIG_FOLDER_NAME,
                    self::MARKETPLACE_FILE,
                    'w+'
                );
                $marketplaceFile->write(json_encode($result));
                $marketplaceFile->close();
                $this->lengowConfiguration->set(LengowConfiguration::LENGOW_MARKETPLACE_UPDATE, (string)time());
            } catch (LengowException $e) {
                $decodedMessage = $this->lengowLog->decodeMessage($e->getMessage());
                $this->lengowLog->write(
                    LengowLog::CODE_IMPORT,
                    $this->lengowLog->encodeMessage('log.import.marketplace_update_failed', [
                        'decoded_message' => $decodedMessage,
                    ]),
                    $logOutput
                );
            }
            return $result;
        }
        // if the API does not respond, use marketplaces.json if it exists
        if (file_exists($filePath)) {
            $marketplacesData = file_get_contents($filePath);
            if ($marketplacesData) {
                return json_decode($marketplacesData, false);
            }
        }
        return false;
    }
}
