<?php declare(strict_types=1);

namespace Lengow\Connector\Service;

use \Exception;
use Lengow\Connector\Factory\LengowFileFactory;
use Lengow\Connector\Util\EnvironmentInfoProvider;

/**
 * Class LengowSync
 * @package Lengow\Connector\Service
 */
class LengowSync
{
    /**
     * @var string plugin type for lengow
     */
    private const PLUGIN_TYPE = 'shopware';

    /* Sync actions */
    public const SYNC_CATALOG = 'catalog';
    public const SYNC_CMS_OPTION = 'cms_option';
    public const SYNC_STATUS_ACCOUNT = 'status_account';
    public const SYNC_MARKETPLACE = 'marketplace';
    public const SYNC_ORDER = 'order';
    public const SYNC_ACTION = 'action';
    public const SYNC_PLUGIN_DATA = 'plugin';

    /* Plugin link types */
    public const LINK_TYPE_HELP_CENTER = 'help_center';
    public const LINK_TYPE_CHANGELOG = 'changelog';
    public const LINK_TYPE_UPDATE_GUIDE = 'update_guide';
    public const LINK_TYPE_SUPPORT = 'support';

    /* Default plugin links */
    private const LINK_HELP_CENTER = 'https://support.lengow.com/kb/guide/en/shopware-6-1KEHaAuucG/Steps/98507';
    private const LINK_CHANGELOG = 'https://support.lengow.com/kb/guide/en/shopware-6-1KEHaAuucG/Steps/98507,113511,180222';
    private const LINK_UPDATE_GUIDE = 'https://support.lengow.com/kb/guide/en/shopware-6-1KEHaAuucG/Steps/98507,123308';
    private const LINK_SUPPORT = 'https://help-support.lengow.com/hc/en-us/requests/new';

    /* Api iso codes */
    private const API_ISO_CODE_EN = 'en';
    private const API_ISO_CODE_FR = 'fr';
    private const API_ISO_CODE_DE = 'de';

    /**
     * @var string name of logs folder
     */
    private const MARKETPLACE_FILE = 'marketplaces.json';

    /**
     * @var string plugin data type for lengow
     */
    private const PLUGIN_DATA_TYPE = 'shopware6';

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
     * @var array cache time for catalog, account status, cms options and marketplace synchronisation
     */
    private $cacheTimes = [
        self::SYNC_CATALOG => 21600,
        self::SYNC_CMS_OPTION => 86400,
        self::SYNC_STATUS_ACCOUNT => 86400,
        self::SYNC_MARKETPLACE => 43200,
        self::SYNC_PLUGIN_DATA => 86400,
    ];

    /**
     * @var array valid sync actions
     */
    private $syncActions = [
        self::SYNC_ORDER,
        self::SYNC_CMS_OPTION,
        self::SYNC_STATUS_ACCOUNT,
        self::SYNC_MARKETPLACE,
        self::SYNC_ACTION,
        self::SYNC_CATALOG,
        self::SYNC_PLUGIN_DATA,
    ];

    /**
     * @var array iso code correspondence for plugin links
     */
    private $genericIsoCodes = [
        self::API_ISO_CODE_EN => LengowTranslation::ISO_CODE_EN,
        self::API_ISO_CODE_FR => LengowTranslation::ISO_CODE_FR,
        self::API_ISO_CODE_DE => LengowTranslation::ISO_CODE_DE,
    ];

    /**
     * @var array default plugin links when the API is not available
     */
    private $defaultPluginLinks = [
        self::LINK_TYPE_HELP_CENTER => self::LINK_HELP_CENTER,
        self::LINK_TYPE_CHANGELOG => self::LINK_CHANGELOG,
        self::LINK_TYPE_UPDATE_GUIDE => self::LINK_UPDATE_GUIDE,
        self::LINK_TYPE_SUPPORT => self::LINK_SUPPORT,
    ];

    /**
     * LengowImportOrder Construct
     *
     * @param LengowConnector $lengowConnector Lengow connector service
     * @param LengowConfiguration $lengowConfiguration Lengow configuration service
     * @param LengowLog $lengowLog Lengow log service
     * @param LengowFileFactory $lengowFileFactory Lengow file factory
     * @param EnvironmentInfoProvider $environmentInfoProvider Environment info provider utility
     * @param LengowExport $lengowExport Lengow export service
     */
    public function __construct(
        LengowConnector $lengowConnector,
        LengowConfiguration $lengowConfiguration,
        LengowLog $lengowLog,
        LengowFileFactory $lengowFileFactory,
        EnvironmentInfoProvider $environmentInfoProvider,
        LengowExport $lengowExport
    )
    {
        $this->lengowConnector = $lengowConnector;
        $this->lengowConfiguration = $lengowConfiguration;
        $this->lengowLog = $lengowLog;
        $this->lengowFileFactory = $lengowFileFactory;
        $this->environmentInfoProvider = $environmentInfoProvider;
        $this->lengowExport = $lengowExport;
    }

    /**
     * Is sync action
     *
     * @param string $action sync action
     *
     * @return bool
     */
    public function isSyncAction(string $action): bool
    {
        return in_array($action, $this->syncActions, true);
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
            'token' => $this->lengowConfiguration->getToken(),
            'type' => self::PLUGIN_TYPE,
            'version' => $this->environmentInfoProvider->getVersion(),
            'plugin_version' => $this->environmentInfoProvider->getPluginVersion(),
            'email' => $this->lengowConfiguration->get('core.basicInformation.email'),
            'cron_url' => $this->lengowConfiguration->getCronUrl(),
            'toolbox_url' => $this->lengowConfiguration->getToolboxUrl(),
            'shops' => [],
        ];
        $salesChannels = $this->environmentInfoProvider->getActiveSalesChannels();
        if ($salesChannels->count() !== 0) {
            foreach ($salesChannels as $salesChannel) {
                $salesChannelId = $salesChannel->getId();
                $this->lengowExport->init([
                    LengowExport::PARAM_SALES_CHANNEL_ID => $salesChannelId,
                ]);
                $syncData['shops'][] = [
                    'token' => $this->lengowConfiguration->getToken($salesChannelId),
                    'shop_name' => $salesChannel->getName(),
                    'domain_url' => $this->environmentInfoProvider->getBaseUrl($salesChannelId),
                    'feed_url' => $this->lengowConfiguration->getFeedUrl($salesChannelId),
                    'total_product_number' => $this->lengowExport->getTotalProduct(),
                    'exported_product_number' => $this->lengowExport->getTotalExportProduct(),
                    'enabled' => $this->lengowConfiguration->salesChannelIsActive($salesChannelId),
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
        $success = false;
        $settingUpdated = false;
        if ($this->lengowConfiguration->isNewMerchant()) {
            return $success;
        }
        if (!$force) {
            $updatedAt = $this->lengowConfiguration->get(LengowConfiguration::LAST_UPDATE_CATALOG);
            if ($updatedAt !== null && (time() - (int) $updatedAt) < $this->cacheTimes[self::SYNC_CATALOG]) {
                return $success;
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
                    if ($salesChannel === null || !isset($cmsShop->catalog_ids)) {
                        continue;
                    }
                    $salesChannelId = $salesChannel->getId();
                    $idsChange = $this->lengowConfiguration->setCatalogIds($cmsShop->catalog_ids, $salesChannelId);
                    $salesChannelChange = $this->lengowConfiguration->setActiveSalesChannel($salesChannelId);
                    if (!$settingUpdated && ($idsChange || $salesChannelChange)) {
                        $settingUpdated = true;
                    }
                }
                $success = true;
                break;
            }
        }
        // save last update date for a specific settings (change synchronisation interval time)
        if ($settingUpdated) {
            $this->lengowConfiguration->set(LengowConfiguration::LAST_UPDATE_SETTING, (string) time());
        }
        $this->lengowConfiguration->set(LengowConfiguration::LAST_UPDATE_CATALOG, (string) time());
        return $success;
    }

    /**
     * Get options for all store
     *
     * @return array
     */
    public function getOptionData(): array
    {
        $data = [
            'token' => $this->lengowConfiguration->getToken(),
            'version' => $this->environmentInfoProvider->getVersion(),
            'plugin_version' => $this->environmentInfoProvider->getPluginVersion(),
            'options' => $this->lengowConfiguration->getAllValues(),
            'shops' => [],
        ];
        $salesChannels = $this->environmentInfoProvider->getActiveSalesChannels();
        if ($salesChannels->count() !== 0) {
            foreach ($salesChannels as $salesChannel) {
                $salesChannelId = $salesChannel->getId();
                $this->lengowExport->init([
                    LengowExport::PARAM_SALES_CHANNEL_ID => $salesChannelId,
                ]);
                $data['shops'][] = [
                    'token' => $this->lengowConfiguration->getToken($salesChannelId),
                    'enabled' => $this->lengowConfiguration->salesChannelIsActive($salesChannelId),
                    'total_product_number' => $this->lengowExport->getTotalProduct(),
                    'exported_product_number' =>$this->lengowExport->getTotalExportProduct(),
                    'options' => $this->lengowConfiguration->getAllValues($salesChannelId),
                ];
            }
        }
        return $data;
    }

    /**
     * Set CMS options
     *
     * @param bool $force force cache update
     * @param bool $logOutput see log or not
     *
     * @return bool
     */
    public function setCmsOption(bool $force = false, bool $logOutput = false): bool
    {
        if ($this->lengowConfiguration->isNewMerchant() || $this->lengowConfiguration->debugModeIsActive()) {
            return false;
        }
        if (!$force) {
            $updatedAt = $this->lengowConfiguration->get(LengowConfiguration::LAST_UPDATE_OPTION_CMS);
            if ($updatedAt !== null && (time() - (int) $updatedAt) < $this->cacheTimes[self::SYNC_CMS_OPTION]) {
                return false;
            }
        }
        $options = json_encode($this->getOptionData());
        $this->lengowConnector->queryApi(LengowConnector::PUT, LengowConnector::API_CMS, [], $options, $logOutput);
        $this->lengowConfiguration->set(LengowConfiguration::LAST_UPDATE_OPTION_CMS, (string) time());
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
            . $sep . EnvironmentInfoProvider::FOLDER_CONFIG . $sep . self::MARKETPLACE_FILE;
        if (!$force) {
            $updatedAt = $this->lengowConfiguration->get(LengowConfiguration::LAST_UPDATE_MARKETPLACE);
            if ($updatedAt !== null
                && (time() - (int) $updatedAt) < $this->cacheTimes[self::SYNC_MARKETPLACE]
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
                    EnvironmentInfoProvider::FOLDER_CONFIG,
                    self::MARKETPLACE_FILE,
                    'w+'
                );
                $marketplaceFile->write(json_encode($result));
                $marketplaceFile->close();
                $this->lengowConfiguration->set(LengowConfiguration::LAST_UPDATE_MARKETPLACE, (string) time());
            } catch (Exception $e) {
                $decodedMessage = $this->lengowLog->decodeMessage(
                    $e->getMessage(),
                    LengowTranslation::DEFAULT_ISO_CODE
                );
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

    /**
     * Get plugin data from api once a day
     *
     * @param bool $force force call if it has been less than a day
     * @param false $logOutput should log output
     *
     * @return array|null
     */
    public function getPluginData(bool $force = false, bool $logOutput = false): ?array
    {
        if (!$force) {
            $updatedAt = $this->lengowConfiguration->get(LengowConfiguration::LAST_UPDATE_PLUGIN_DATA);
            if ($updatedAt !== null
                && (time() - (int) $updatedAt) < $this->cacheTimes[self::SYNC_PLUGIN_DATA]
                && $this->lengowConfiguration->get(LengowConfiguration::PLUGIN_DATA)
            ) {
                return json_decode($this->lengowConfiguration->get(LengowConfiguration::PLUGIN_DATA), true);
            }
        }
        $plugins = $this->lengowConnector->queryApi(
            LengowConnector::GET,
            LengowConnector::API_PLUGIN,
            [],
            '',
            $logOutput
        );
        if (!$plugins) {
            $pluginData = $this->lengowConfiguration->get(LengowConfiguration::PLUGIN_DATA);
            return $pluginData ? json_decode($pluginData, true) : null;
        }
        foreach ($plugins as $plugin) {
            if ($plugin->type === self::PLUGIN_DATA_TYPE) {
                $pluginLinks = [];
                if (!empty($plugin->links)) {
                    foreach ($plugin->links as $link) {
                        if (array_key_exists($link->language->iso_a2, $this->genericIsoCodes)) {
                            $genericIsoCode = $this->genericIsoCodes[$link->language->iso_a2];
                            $pluginLinks[$genericIsoCode][$link->link_type] = $link->link;
                        }
                    }
                }
                $pluginData = [
                    'version' => $plugin->version,
                    'download_link' => $plugin->archive,
                    'cms_min_version' => '6.2',
                    'cms_max_version' => '6.4',
                    'links' => $pluginLinks,
                    'extensions' => $plugin->extensions,
                ];
                $this->lengowConfiguration->set(LengowConfiguration::PLUGIN_DATA, json_encode($pluginData));
                $this->lengowConfiguration->set(LengowConfiguration::LAST_UPDATE_PLUGIN_DATA, (string) time());
                return $pluginData;
            }
        }
        return null;
    }

    /**
     * Get account status from api once a day
     *
     * @param bool $force force call if it has been less than a day
     * @param bool $logOutput should log output
     *
     * @return array|null
     */
    public function getAccountStatus(bool $force = false, bool $logOutput = false): ?array
    {
        if (!$force) {
            $updatedAt = $this->lengowConfiguration->get(LengowConfiguration::LAST_UPDATE_ACCOUNT_STATUS_DATA);
            if ($updatedAt !== null
                && (time() - (int) $updatedAt) < $this->cacheTimes[self::SYNC_PLUGIN_DATA]
                && $this->lengowConfiguration->get(LengowConfiguration::ACCOUNT_STATUS_DATA)
            ) {
                return json_decode($this->lengowConfiguration->get(LengowConfiguration::ACCOUNT_STATUS_DATA), true);
            }
        }
        $status = null;
        $accountData = $this->lengowConnector->queryApi(
            LengowConnector::GET,
            LengowConnector::API_PLAN,
            [],
            '',
            $logOutput
        );
        if ($accountData && isset($accountData->isFreeTrial)) {
            $status = [
                'type' => $accountData->isFreeTrial ? 'free_trial' : '',
                'day' => (int) $accountData->leftDaysBeforeExpired < 0 ? 0 : (int) $accountData->leftDaysBeforeExpired,
                'expired' => (bool) $accountData->isExpired,
            ];
            $this->lengowConfiguration->set(LengowConfiguration::ACCOUNT_STATUS_DATA, json_encode($status));
            $this->lengowConfiguration->set(LengowConfiguration::LAST_UPDATE_ACCOUNT_STATUS_DATA, (string) time());
        } else if ($this->lengowConfiguration->get(LengowConfiguration::ACCOUNT_STATUS_DATA)) {
            $status = json_decode($this->lengowConfiguration->get(LengowConfiguration::ACCOUNT_STATUS_DATA), true);
        }
        return $status;
    }

    /**
     * Get an array of plugin links for a specific iso code
     *
     * @param string|null $isoCode
     *
     * @return array
     */
    public function getPluginLinks(string $isoCode = null): array
    {
        $pluginData = $this->getPluginData();
        if (!$pluginData) {
            return $this->defaultPluginLinks;
        }
        // check if the links are available in the locale
        $isoCode = $isoCode ?: LengowTranslation::DEFAULT_ISO_CODE;
        $localeLinks = $pluginData['links'][$isoCode] ?? false;
        $defaultLocaleLinks = $pluginData['links'][LengowTranslation::DEFAULT_ISO_CODE] ?? false;
        // for each type of link, we check if the link is translated
        $pluginLinks = [];
        foreach ($this->defaultPluginLinks as $linkType => $defaultLink) {
            if ($localeLinks && isset($localeLinks[$linkType])) {
                $pluginLinks[$linkType] = $localeLinks[$linkType];
            } elseif ($defaultLocaleLinks && isset($defaultLocaleLinks[$linkType])) {
                $pluginLinks[$linkType] = $defaultLocaleLinks[$linkType];
            } else {
                $pluginLinks[$linkType] = $defaultLink;
            }
        }
        return $pluginLinks;
    }
}
