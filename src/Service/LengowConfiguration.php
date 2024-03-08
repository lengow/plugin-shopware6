<?php

declare(strict_types=1);

namespace Lengow\Connector\Service;

use Lengow\Connector\Entity\Lengow\Settings\SettingsDefinition as LengowSettingsDefinition;
use Lengow\Connector\Entity\Lengow\Settings\SettingsEntity as LengowSettingsEntity;
use Lengow\Connector\Util\EnvironmentInfoProvider;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityDeletedEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\MultiFilter;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\SalesChannelCollection;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;
use Shopware\Core\System\SystemConfig\SystemConfigService;

/**
 * Class LengowConfiguration.
 */
class LengowConfiguration
{
    /* Settings database key */
    public const ACCOUNT_ID = 'lengowAccountId';
    public const ACCESS_TOKEN = 'lengowAccessToken';
    public const SECRET = 'lengowSecretToken';
    public const ENVIRONMENT_URL = 'lengowEnvironmentUrl';
    public const CMS_TOKEN = 'lengowGlobalToken';
    public const AUTHORIZED_IP_ENABLED = 'lengowIpEnabled';
    public const AUTHORIZED_IPS = 'lengowAuthorizedIp';
    public const TRACKING_ENABLED = 'lengowTrackingEnabled';
    public const TRACKING_ID = 'lengowTrackingId';
    public const DEBUG_MODE_ENABLED = 'lengowDebugEnabled';
    public const REPORT_MAIL_ENABLED = 'lengowReportMailEnabled';
    public const REPORT_MAILS = 'lengowReportMailAddress';
    public const TIMEZONE = 'lengowTimezone';
    public const AUTHORIZATION_TOKEN = 'lengowAuthorizationToken';
    public const PLUGIN_DATA = 'lengowPluginData';
    public const ACCOUNT_STATUS_DATA = 'lengowAccountStatus';
    public const SHOP_TOKEN = 'lengowChannelToken';
    public const SHOP_ACTIVE = 'lengowStoreEnabled';
    public const CATALOG_IDS = 'lengowCatalogId';
    public const SELECTION_ENABLED = 'lengowSelectionEnabled';
    public const INACTIVE_ENABLED = 'lengowExportDisabledProduct';
    public const DEFAULT_EXPORT_CARRIER_ID = 'lengowExportDefaultShippingMethod';
    public const SYNCHRONIZATION_DAY_INTERVAL = 'lengowImportDays';
    public const CURRENCY_CONVERSION_ENABLED = 'lengowCurrencyConversion';
    public const B2B_WITHOUT_TAX_ENABLED = 'lengowImportB2b';
    public const SHIPPED_BY_MARKETPLACE_ENABLED = 'lengowImportShipMpEnabled';
    public const ACTION_SEND_RETURN_TRACKING_NUMBER = 'lengowSendReturnTrackingNumber';
    public const SHIPPED_BY_MARKETPLACE_STOCK_ENABLED = 'lengowImportStockShipMp';
    public const SYNCHRONIZATION_IN_PROGRESS = 'lengowImportInProgress';
    public const DEFAULT_IMPORT_CARRIER_ID = 'lengowImportDefaultShippingMethod';
    public const LAST_UPDATE_EXPORT = 'lengowLastExport';
    public const LAST_UPDATE_CRON_SYNCHRONIZATION = 'lengowLastImportCron';
    public const LAST_UPDATE_MANUAL_SYNCHRONIZATION = 'lengowLastImportManual';
    public const LAST_UPDATE_ACTION_SYNCHRONIZATION = 'lengowLastActionSync';
    public const LAST_UPDATE_CATALOG = 'lengowCatalogUpdate';
    public const LAST_UPDATE_MARKETPLACE = 'lengowMarketplaceUpdate';
    public const LAST_UPDATE_ACCOUNT_STATUS_DATA = 'lengowAccountStatusUpdate';
    public const LAST_UPDATE_OPTION_CMS = 'lengowOptionCmsUpdate';
    public const LAST_UPDATE_SETTING = 'lengowLastSettingUpdate';
    public const LAST_UPDATE_PLUGIN_DATA = 'lengowPluginDataUpdate';
    public const LAST_UPDATE_AUTHORIZATION_TOKEN = 'lengowLastAuthorizationTokenUpdate';
    public const LAST_UPDATE_PLUGIN_MODAL = 'lengowLastUpdatePluginModal';

    /* Configuration parameters */
    public const PARAM_DEFAULT_VALUE = 'default_value';
    public const PARAM_EXPORT = 'export';
    public const PARAM_EXPORT_TOOLBOX = 'export_toolbox';
    public const PARAM_GLOBAL = 'global';
    public const PARAM_LOG = 'log';
    public const PARAM_RESET_TOKEN = 'reset_token';
    public const PARAM_RETURN = 'return';
    public const PARAM_SECRET = 'secret';
    public const PARAM_SHOP = 'shop';
    public const PARAM_UPDATE = 'update';

    /* Configuration value return type */
    public const RETURN_TYPE_BOOLEAN = 'boolean';
    public const RETURN_TYPE_INTEGER = 'integer';
    public const RETURN_TYPE_ARRAY = 'array';
    public const RETURN_TYPE_STRING = 'string';

    /**
     * @var string Lengow base setting path
     */
    public const LENGOW_SETTING_PATH = 'Connector.config.';

    /**
     * @var string Lengow default timezone
     */
    public const DEFAULT_TIMEZONE = 'Etc/Greenwich';

    /**
     * @var array params correspondence keys for toolbox
     */
    public static $genericParamKeys = [
        self::ACCOUNT_ID => 'account_id',
        self::ACCESS_TOKEN => 'access_token',
        self::SECRET => 'secret',
        self::CMS_TOKEN => 'cms_token',
        self::ENVIRONMENT_URL => 'lengowEnvironmentUrl',
        self::AUTHORIZED_IP_ENABLED => 'authorized_ip_enabled',
        self::AUTHORIZED_IPS => 'authorized_ips',
        self::TRACKING_ENABLED => 'tracking_enabled',
        self::TRACKING_ID => 'tracking_id',
        self::DEBUG_MODE_ENABLED => 'debug_mode_enabled',
        self::REPORT_MAIL_ENABLED => 'report_mail_enabled',
        self::REPORT_MAILS => 'report_mails',
        self::TIMEZONE => 'timezone',
        self::AUTHORIZATION_TOKEN => 'authorization_token',
        self::PLUGIN_DATA => 'plugin_data',
        self::ACCOUNT_STATUS_DATA => 'account_status_data',
        self::SHOP_TOKEN => 'shop_token',
        self::SHOP_ACTIVE => 'shop_active',
        self::CATALOG_IDS => 'catalog_ids',
        self::SELECTION_ENABLED => 'selection_enabled',
        self::INACTIVE_ENABLED => 'inactive_enabled',
        self::DEFAULT_EXPORT_CARRIER_ID => 'default_export_carrier_id',
        self::SYNCHRONIZATION_DAY_INTERVAL => 'synchronization_day_interval',
        self::DEFAULT_IMPORT_CARRIER_ID => 'default_import_carrier_id',
        self::CURRENCY_CONVERSION_ENABLED => 'currency_conversion_enabled',
        self::B2B_WITHOUT_TAX_ENABLED => 'b2b_without_tax_enabled',
        self::SHIPPED_BY_MARKETPLACE_ENABLED => 'shipped_by_marketplace_enabled',
        self::ACTION_SEND_RETURN_TRACKING_NUMBER => 'action_send_return_tracking_number',
        self::SHIPPED_BY_MARKETPLACE_STOCK_ENABLED => 'shipped_by_marketplace_stock_enabled',
        self::SYNCHRONIZATION_IN_PROGRESS => 'synchronization_in_progress',
        self::LAST_UPDATE_EXPORT => 'last_update_export',
        self::LAST_UPDATE_CRON_SYNCHRONIZATION => 'last_update_cron_synchronization',
        self::LAST_UPDATE_MANUAL_SYNCHRONIZATION => 'last_update_manual_synchronization',
        self::LAST_UPDATE_ACTION_SYNCHRONIZATION => 'last_update_action_synchronization',
        self::LAST_UPDATE_CATALOG => 'last_update_catalog',
        self::LAST_UPDATE_MARKETPLACE => 'last_update_marketplace',
        self::LAST_UPDATE_ACCOUNT_STATUS_DATA => 'last_update_account_status_data',
        self::LAST_UPDATE_OPTION_CMS => 'last_update_option_cms',
        self::LAST_UPDATE_SETTING => 'last_update_setting',
        self::LAST_UPDATE_PLUGIN_DATA => 'last_update_plugin_data',
        self::LAST_UPDATE_AUTHORIZATION_TOKEN => 'last_update_authorization_token',
        self::LAST_UPDATE_PLUGIN_MODAL => 'last_update_plugin_modal',
    ];

    /**
     * @var array specific Lengow settings in lengow_settings table
     */
    public static $lengowSettings = [
        self::ACCOUNT_ID => [
            self::PARAM_GLOBAL => true,
            self::PARAM_EXPORT => false,
            self::PARAM_DEFAULT_VALUE => '',
        ],
        self::ACCESS_TOKEN => [
            self::PARAM_GLOBAL => true,
            self::PARAM_EXPORT => false,
            self::PARAM_SECRET => true,
            self::PARAM_DEFAULT_VALUE => '',
            self::PARAM_RESET_TOKEN => true,
        ],
        self::SECRET => [
            self::PARAM_GLOBAL => true,
            self::PARAM_EXPORT => false,
            self::PARAM_SECRET => true,
            self::PARAM_DEFAULT_VALUE => '',
            self::PARAM_RESET_TOKEN => true,
        ],
        self::CMS_TOKEN => [
            self::PARAM_GLOBAL => true,
            self::PARAM_EXPORT_TOOLBOX => false,
            self::PARAM_DEFAULT_VALUE => '',
        ],
        self::ENVIRONMENT_URL => [
            self::PARAM_GLOBAL => true,
            self::PARAM_EXPORT_TOOLBOX => false,
            self::PARAM_RETURN => self::RETURN_TYPE_STRING,
            self::PARAM_DEFAULT_VALUE => '.io',
        ],
        self::AUTHORIZED_IP_ENABLED => [
            self::PARAM_GLOBAL => true,
            self::PARAM_EXPORT_TOOLBOX => false,
            self::PARAM_RETURN => self::RETURN_TYPE_BOOLEAN,
            self::PARAM_DEFAULT_VALUE => '0',
        ],
        self::AUTHORIZED_IPS => [
            self::PARAM_GLOBAL => true,
            self::PARAM_EXPORT_TOOLBOX => false,
            self::PARAM_RETURN => self::RETURN_TYPE_ARRAY,
            self::PARAM_DEFAULT_VALUE => '',
        ],
        self::TRACKING_ENABLED => [
            self::PARAM_GLOBAL => true,
            self::PARAM_RETURN => self::RETURN_TYPE_BOOLEAN,
            self::PARAM_DEFAULT_VALUE => '0',
        ],
        self::TRACKING_ID => [
            self::PARAM_GLOBAL => true,
            self::PARAM_DEFAULT_VALUE => 'productId',
        ],
        self::DEBUG_MODE_ENABLED => [
            self::PARAM_GLOBAL => true,
            self::PARAM_EXPORT_TOOLBOX => false,
            self::PARAM_RETURN => self::RETURN_TYPE_BOOLEAN,
            self::PARAM_DEFAULT_VALUE => '0',
        ],
        self::REPORT_MAIL_ENABLED => [
            self::PARAM_GLOBAL => true,
            self::PARAM_RETURN => self::RETURN_TYPE_BOOLEAN,
            self::PARAM_DEFAULT_VALUE => '1',
        ],
        self::REPORT_MAILS => [
            self::PARAM_GLOBAL => true,
            self::PARAM_RETURN => self::RETURN_TYPE_ARRAY,
            self::PARAM_DEFAULT_VALUE => '',
        ],
        self::TIMEZONE => [
            self::PARAM_GLOBAL => true,
            self::PARAM_DEFAULT_VALUE => self::DEFAULT_TIMEZONE,
        ],
        self::AUTHORIZATION_TOKEN => [
            self::PARAM_GLOBAL => true,
            self::PARAM_EXPORT => false,
            self::PARAM_LOG => false,
            self::PARAM_DEFAULT_VALUE => '',
        ],
        self::PLUGIN_DATA => [
            self::PARAM_GLOBAL => true,
            self::PARAM_EXPORT => false,
            self::PARAM_LOG => false,
            self::PARAM_DEFAULT_VALUE => '',
        ],
        self::ACCOUNT_STATUS_DATA => [
            self::PARAM_GLOBAL => true,
            self::PARAM_EXPORT => false,
            self::PARAM_LOG => false,
            self::PARAM_DEFAULT_VALUE => '',
        ],
        self::SHOP_TOKEN => [
            self::PARAM_SHOP => true,
            self::PARAM_EXPORT_TOOLBOX => false,
            self::PARAM_DEFAULT_VALUE => '',
        ],
        self::SHOP_ACTIVE => [
            self::PARAM_SHOP => true,
            self::PARAM_EXPORT_TOOLBOX => false,
            self::PARAM_RETURN => self::RETURN_TYPE_BOOLEAN,
            self::PARAM_DEFAULT_VALUE => '0',
        ],
        self::CATALOG_IDS => [
            self::PARAM_SHOP => true,
            self::PARAM_EXPORT_TOOLBOX => false,
            self::PARAM_UPDATE => true,
            self::PARAM_RETURN => self::RETURN_TYPE_ARRAY,
            self::PARAM_DEFAULT_VALUE => '',
        ],
        self::SELECTION_ENABLED => [
            self::PARAM_SHOP => true,
            self::PARAM_RETURN => self::RETURN_TYPE_BOOLEAN,
            self::PARAM_DEFAULT_VALUE => '0',
        ],
        self::INACTIVE_ENABLED => [
            self::PARAM_SHOP => true,
            self::PARAM_RETURN => self::RETURN_TYPE_BOOLEAN,
            self::PARAM_DEFAULT_VALUE => '0',
        ],
        self::DEFAULT_EXPORT_CARRIER_ID => [
            self::PARAM_SHOP => true,
            self::PARAM_DEFAULT_VALUE => '',
        ],
        self::SYNCHRONIZATION_DAY_INTERVAL => [
            self::PARAM_GLOBAL => true,
            self::PARAM_UPDATE => true,
            self::PARAM_RETURN => self::RETURN_TYPE_INTEGER,
            self::PARAM_DEFAULT_VALUE => '3',
        ],
        self::DEFAULT_IMPORT_CARRIER_ID => [
            self::PARAM_SHOP => true,
            self::PARAM_DEFAULT_VALUE => '',
        ],
        self::CURRENCY_CONVERSION_ENABLED => [
            self::PARAM_GLOBAL => true,
            self::PARAM_RETURN => self::RETURN_TYPE_BOOLEAN,
            self::PARAM_DEFAULT_VALUE => '1',
        ],
        self::B2B_WITHOUT_TAX_ENABLED => [
            self::PARAM_GLOBAL => true,
            self::PARAM_RETURN => self::RETURN_TYPE_BOOLEAN,
            self::PARAM_DEFAULT_VALUE => '0',
        ],
        self::SHIPPED_BY_MARKETPLACE_ENABLED => [
            self::PARAM_GLOBAL => true,
            self::PARAM_RETURN => self::RETURN_TYPE_BOOLEAN,
            self::PARAM_DEFAULT_VALUE => '0',
        ],
        self::ACTION_SEND_RETURN_TRACKING_NUMBER => [
            self::PARAM_GLOBAL => true,
            self::PARAM_RETURN => self::RETURN_TYPE_BOOLEAN,
            self::PARAM_DEFAULT_VALUE => '0',
        ],
        self::SHIPPED_BY_MARKETPLACE_STOCK_ENABLED => [
            self::PARAM_GLOBAL => true,
            self::PARAM_RETURN => self::RETURN_TYPE_BOOLEAN,
            self::PARAM_DEFAULT_VALUE => '0',
        ],
        self::SYNCHRONIZATION_IN_PROGRESS => [
            self::PARAM_GLOBAL => true,
            self::PARAM_EXPORT => false,
            self::PARAM_LOG => false,
            self::PARAM_DEFAULT_VALUE => '',
        ],
        self::LAST_UPDATE_EXPORT => [
            self::PARAM_SHOP => true,
            self::PARAM_EXPORT_TOOLBOX => false,
            self::PARAM_RETURN => self::RETURN_TYPE_INTEGER,
            self::PARAM_LOG => false,
            self::PARAM_DEFAULT_VALUE => '',
        ],
        self::LAST_UPDATE_CRON_SYNCHRONIZATION => [
            self::PARAM_GLOBAL => true,
            self::PARAM_EXPORT_TOOLBOX => false,
            self::PARAM_RETURN => self::RETURN_TYPE_INTEGER,
            self::PARAM_LOG => false,
            self::PARAM_DEFAULT_VALUE => '',
        ],
        self::LAST_UPDATE_MANUAL_SYNCHRONIZATION => [
            self::PARAM_GLOBAL => true,
            self::PARAM_EXPORT_TOOLBOX => false,
            self::PARAM_RETURN => self::RETURN_TYPE_INTEGER,
            self::PARAM_LOG => false,
            self::PARAM_DEFAULT_VALUE => '',
        ],
        self::LAST_UPDATE_ACTION_SYNCHRONIZATION => [
            self::PARAM_GLOBAL => true,
            self::PARAM_RETURN => self::RETURN_TYPE_INTEGER,
            self::PARAM_LOG => false,
            self::PARAM_DEFAULT_VALUE => '',
        ],
        self::LAST_UPDATE_CATALOG => [
            self::PARAM_GLOBAL => true,
            self::PARAM_RETURN => self::RETURN_TYPE_INTEGER,
            self::PARAM_LOG => false,
            self::PARAM_DEFAULT_VALUE => '',
        ],
        self::LAST_UPDATE_MARKETPLACE => [
            self::PARAM_GLOBAL => true,
            self::PARAM_RETURN => self::RETURN_TYPE_INTEGER,
            self::PARAM_LOG => false,
            self::PARAM_DEFAULT_VALUE => '',
        ],
        self::LAST_UPDATE_ACCOUNT_STATUS_DATA => [
            self::PARAM_GLOBAL => true,
            self::PARAM_RETURN => self::RETURN_TYPE_INTEGER,
            self::PARAM_LOG => false,
            self::PARAM_DEFAULT_VALUE => '',
        ],
        self::LAST_UPDATE_OPTION_CMS => [
            self::PARAM_GLOBAL => true,
            self::PARAM_RETURN => self::RETURN_TYPE_INTEGER,
            self::PARAM_LOG => false,
            self::PARAM_DEFAULT_VALUE => '',
        ],
        self::LAST_UPDATE_SETTING => [
            self::PARAM_GLOBAL => true,
            self::PARAM_RETURN => self::RETURN_TYPE_INTEGER,
            self::PARAM_LOG => false,
            self::PARAM_DEFAULT_VALUE => '',
        ],
        self::LAST_UPDATE_PLUGIN_DATA => [
            self::PARAM_GLOBAL => true,
            self::PARAM_RETURN => self::RETURN_TYPE_INTEGER,
            self::PARAM_LOG => false,
            self::PARAM_DEFAULT_VALUE => '',
        ],
        self::LAST_UPDATE_AUTHORIZATION_TOKEN => [
            self::PARAM_GLOBAL => true,
            self::PARAM_RETURN => self::RETURN_TYPE_INTEGER,
            self::PARAM_LOG => false,
            self::PARAM_DEFAULT_VALUE => '',
        ],
        self::LAST_UPDATE_PLUGIN_MODAL => [
            self::PARAM_GLOBAL => true,
            self::PARAM_RETURN => self::RETURN_TYPE_INTEGER,
            self::PARAM_LOG => false,
            self::PARAM_DEFAULT_VALUE => '',
        ],
    ];

    /**
     * @var EntityRepository Lengow settings access
     */
    private $settingsRepository;

    /**
     * @var SystemConfigService shopware settings access
     */
    private $systemConfigService;

    /**
     * @var EntityRepository shopware settings repository
     */
    private $systemConfigRepository;

    /**
     * @var EnvironmentInfoProvider Environment info provider utility
     */
    private $environmentInfoProvider;

    /**
     * @var string Lengow timezone for date creation
     */
    private $lengowTimezone;

    /**
     * @var string base url of the Lengow API
     */
    private const LENGOW_BASE_API_URL = 'https://api.lengow';

    /**
     * LengowConfiguration constructor.
     *
     * @param EntityRepository        $settingsRepository      Lengow settings access
     * @param SystemConfigService     $systemConfigService     Shopware settings access
     * @param EntityRepository        $systemConfigRepository  shopware settings repository
     * @param EnvironmentInfoProvider $environmentInfoProvider Environment info provider utility
     */
    public function __construct(
        EntityRepository $settingsRepository,
        SystemConfigService $systemConfigService,
        EntityRepository $systemConfigRepository,
        EnvironmentInfoProvider $environmentInfoProvider
    ) {
        $this->settingsRepository = $settingsRepository;
        $this->systemConfigService = $systemConfigService;
        $this->systemConfigRepository = $systemConfigRepository;
        $this->environmentInfoProvider = $environmentInfoProvider;
    }

    /**
     * @param string      $key            config name
     * @param string|null $salesChannelId sales channel
     *
     * @return array|bool|float|int|string|null
     */
    public function get(string $key, string $salesChannelId = null)
    {
        // get a Lengow configuration
        if (array_key_exists($key, self::$lengowSettings)) {
            return $this->getInLengowConfig($key, $salesChannelId);
        }

        // get a Shopware configuration
        return $this->getInShopwareConfig($key, $salesChannelId);
    }

    /**
     * @param string      $key            config name
     * @param string      $value          new value for config
     * @param string|null $salesChannelId sales channel
     *
     * @return EntityWrittenContainerEvent|void|null
     */
    public function set(string $key, string $value, string $salesChannelId = null)
    {
        // set a Lengow configuration
        if (array_key_exists($key, self::$lengowSettings)) {
            return $this->setInLengowConfig($key, $value, $salesChannelId);
        }
        // set a Shopware configuration
        $this->setInShopwareConfig($key, $value, $salesChannelId);
    }

    /**
     * Get global token or channel token.
     *
     * @param string|null $salesChannelId Shopware sales channel id
     */
    public function getToken(string $salesChannelId = null): string
    {
        if ($salesChannelId) {
            $token = $this->get(self::SHOP_TOKEN, $salesChannelId);
        } else {
            $token = $this->get(self::CMS_TOKEN);
        }
        if ($token && '' !== $token) {
            return $token;
        }

        return $this->generateToken($salesChannelId);
    }

    /**
     * Generate new token.
     *
     * @param string|null $salesChannelId Shopware sales channel id
     */
    public function generateToken(string $salesChannelId = null): string
    {
        $token = bin2hex(openssl_random_pseudo_bytes(16));
        if ($salesChannelId) {
            $this->set(self::SHOP_TOKEN, $token, $salesChannelId);
        } else {
            $this->set(self::CMS_TOKEN, $token);
        }

        return $token;
    }

    /**
     * Get a sales channel with a given token.
     *
     * @param string $token sales channel token
     */
    public function getSalesChannelByToken(string $token): ?SalesChannelEntity
    {
        /** @var SalesChannelCollection $salesChannelCollection */
        $salesChannelCollection = $this->environmentInfoProvider->getActiveSalesChannels();
        foreach ($salesChannelCollection as $salesChannel) {
            if ($this->getToken($salesChannel->getId()) === $token) {
                return $salesChannel;
            }
        }

        return null;
    }

    /**
     * Get valid account id / access token / secret token.
     */
    public function getAccessIds(): array
    {
        $accountId = (int) $this->get(self::ACCOUNT_ID);
        $accessToken = $this->get(self::ACCESS_TOKEN);
        $secretToken = $this->get(self::SECRET);
        if (0 !== $accountId && !empty($accessToken) && !empty($secretToken)) {
            return [$accountId, $accessToken, $secretToken];
        }

        return [null, null, null];
    }

    /**
     * Set Valid Account id / Access token / Secret token.
     *
     * @param array $accessIds Account id / Access token / Secret token
     */
    public function setAccessIds(array $accessIds): bool
    {
        $count = 0;
        $listKey = [self::ACCOUNT_ID, self::ACCESS_TOKEN, self::SECRET];
        foreach ($accessIds as $key => $value) {
            if (!in_array($key, $listKey, true)) {
                continue;
            }
            if ('' !== $value) {
                ++$count;
                $this->set($key, (string) $value);
            }
        }

        return $count === count($listKey);
    }

    /**
     * Reset Account id / Access token / Secret token.
     */
    public function resetAccessIds(): void
    {
        $accessIds = [self::ACCOUNT_ID, self::ACCESS_TOKEN, self::SECRET];
        foreach ($accessIds as $key) {
            $this->set($key, '');
        }
    }

    /**
     * Reset authorization token.
     */
    public function resetAuthorizationToken(): void
    {
        $this->set(self::AUTHORIZATION_TOKEN, '');
        $this->set(self::LAST_UPDATE_AUTHORIZATION_TOKEN, '');
    }

    /**
     * Check if new merchant.
     */
    public function isNewMerchant(): bool
    {
        [$accountId, $accessToken, $secretToken] = $this->getAccessIds();

        return !(null !== $accountId && null !== $accessToken && null !== $secretToken);
    }

    /**
     * Get the value of URL_ENVIRONMENT from the form.
     *
     * @return string The URL suffix (e.g., ".io", ".net")
     */
    public function getUrlEnvironment()
    {
        return $this->get(self::ENVIRONMENT_URL);
    }

    /**
     * Get the URL of the API Lengow solution.
     *
     * @return string Returns the URL of the API Lengow solution
     */
    public function getApiLengowUrl(): string
    {
        return self::LENGOW_BASE_API_URL.$this->getUrlEnvironment();
    }

    /**
     * Get catalog ids for a specific shop.
     *
     * @param string $salesChannelId Shopware sales channel id
     */
    public function getCatalogIds(string $salesChannelId): array
    {
        $catalogIds = [];
        $salesChannelCatalogIds = $this->get(self::CATALOG_IDS, $salesChannelId);
        if (!empty($salesChannelCatalogIds)) {
            foreach ($salesChannelCatalogIds as $catalogId) {
                $catalogId = trim(str_replace(["\r\n", ',', '-', '|', ' ', '/'], ';', $catalogId), ';');
                if (is_numeric($catalogId) && (int) $catalogId > 0) {
                    $catalogIds[] = (int) $catalogId;
                }
            }
        }

        return $catalogIds;
    }

    /**
     * Set catalog ids for a specific sales channel.
     *
     * @param array  $catalogIds     Lengow catalog ids
     * @param string $salesChannelId Shopware sales channel id
     */
    public function setCatalogIds(array $catalogIds, string $salesChannelId): bool
    {
        $valueChange = false;
        $salesChannelCatalogIds = $this->getCatalogIds($salesChannelId);
        foreach ($catalogIds as $catalogId) {
            if ($catalogId > 0 && is_numeric($catalogId) && !in_array($catalogId, $salesChannelCatalogIds, true)) {
                $salesChannelCatalogIds[] = (int) $catalogId;
                $valueChange = true;
            }
        }
        $this->set(self::CATALOG_IDS, implode(';', $salesChannelCatalogIds), $salesChannelId);

        return $valueChange;
    }

    /**
     * Recovers if a sales channel is active or not.
     *
     * @param string $salesChannelId Shopware sales channel id
     */
    public function salesChannelIsActive(string $salesChannelId): bool
    {
        return $this->get(self::SHOP_ACTIVE, $salesChannelId);
    }

    /**
     * Set active sales channel or not.
     *
     * @param string $salesChannelId Shopware sales channel id
     */
    public function setActiveSalesChannel(string $salesChannelId): bool
    {
        $shopIsActive = $this->salesChannelIsActive($salesChannelId);
        $catalogIds = $this->getCatalogIds($salesChannelId);
        $salesChannelHasCatalog = !empty($catalogIds) ? '1' : '0';
        $this->set(self::SHOP_ACTIVE, $salesChannelHasCatalog, $salesChannelId);

        return $shopIsActive !== (bool) $salesChannelHasCatalog;
    }

    /**
     * Get all report mails.
     */
    public function getReportEmailAddress(): array
    {
        $reportEmailAddress = [];
        $emails = $this->get(self::REPORT_MAILS);
        foreach ($emails as $email) {
            if ('' !== $email && (bool) preg_match('/^\S+\@\S+\.\S+$/', $email)) {
                $reportEmailAddress[] = $email;
            }
        }
        if (empty($reportEmailAddress)) {
            $reportEmailAddress[] = $this->get('core.basicInformation.email');
        }

        return $reportEmailAddress;
    }

    /**
     * Recovers if a store is active or not.
     */
    public function debugModeIsActive(): bool
    {
        return $this->get(self::DEBUG_MODE_ENABLED);
    }

    /**
     * Get Lengow timezone for datetime.
     */
    public function getLengowTimezone(): string
    {
        if (null === $this->lengowTimezone) {
            $timezone = $this->get(self::TIMEZONE);
            $this->lengowTimezone = $timezone ?? self::DEFAULT_TIMEZONE;
        }

        return $this->lengowTimezone;
    }

    /**
     * Get date with correct timezone.
     *
     * @param int|null $timestamp gmt timestamp
     * @param string   $format    date format
     */
    public function date(int $timestamp = null, string $format = EnvironmentInfoProvider::DATE_FULL): string
    {
        $timestamp = $timestamp ?? time();
        $timezone = $this->getLengowTimezone();
        $dateTime = new \DateTime();

        return $dateTime->setTimestamp($timestamp)->setTimezone(new \DateTimeZone($timezone))->format($format);
    }

    /**
     * Get GMT date.
     *
     * @param int|null $timestamp gmt timestamp
     * @param string   $format    date format
     */
    public function gmtDate(int $timestamp = null, string $format = EnvironmentInfoProvider::DATE_FULL): string
    {
        $timestamp = $timestamp ?? time();
        $dateTime = new \DateTime();

        return $dateTime->setTimestamp($timestamp)->format($format);
    }

    /**
     * Get list of Shopware sales channel that have been activated in Lengow.
     *
     * @param string|null $salesChannelId Shopware sales channel id
     */
    public function getLengowActiveSalesChannels(string $salesChannelId = null): array
    {
        $result = [];
        /** @var SalesChannelCollection $salesChannelCollection */
        $salesChannelCollection = $this->environmentInfoProvider->getActiveSalesChannels();
        foreach ($salesChannelCollection as $salesChannel) {
            /** @var SalesChannelEntity $salesChannel */
            if ($salesChannelId && $salesChannel->getId() !== $salesChannelId) {
                continue;
            }
            // get Lengow config for this sales channel
            if ($this->get(self::SHOP_ACTIVE, $salesChannel->getId())) {
                $result[] = $salesChannel;
            }
        }

        return $result;
    }

    /**
     * Get Lengow setting by id.
     *
     * @param string $settingId Lengow setting id
     */
    public function getSettingById(string $settingId): ?LengowSettingsEntity
    {
        $criteria = new Criteria();
        $criteria->setIds([$settingId]);
        $criteria->addAssociation('salesChannel');
        $settingsCollection = $this->settingsRepository
            ->search($criteria, Context::createDefaultContext())
            ->getEntities();

        return 0 !== $settingsCollection->count() ? $settingsCollection->first() : null;
    }

    /**
     * Get Values by sales channel or global.
     *
     * @param string|null $salesChannelId Shopware sales channel id
     * @param bool        $toolbox        get all values for toolbox or not
     */
    public function getAllValues(string $salesChannelId = null, bool $toolbox = false): array
    {
        $rows = [];
        foreach (self::$lengowSettings as $key => $keyParams) {
            if ((isset($keyParams[self::PARAM_EXPORT]) && !$keyParams[self::PARAM_EXPORT])
                || ($toolbox
                    && isset($keyParams[self::PARAM_EXPORT_TOOLBOX])
                    && !$keyParams[self::PARAM_EXPORT_TOOLBOX]
                )
            ) {
                continue;
            }
            if ($salesChannelId) {
                if (isset($keyParams[self::PARAM_SHOP]) && $keyParams[self::PARAM_SHOP]) {
                    $rows[self::$genericParamKeys[$key]] = $this->get($key, $salesChannelId);
                }
            } elseif (isset($keyParams[self::PARAM_GLOBAL]) && $keyParams[self::PARAM_GLOBAL]) {
                $rows[self::$genericParamKeys[$key]] = $this->get($key);
            }
        }

        return $rows;
    }

    /**
     * @param string      $key            config name
     * @param string|null $salesChannelId sales channel
     *
     * @return array|bool|float|int|string|null
     */
    private function getInShopwareConfig(string $key, string $salesChannelId = null)
    {
        return $this->systemConfigService->get($key, $salesChannelId);
    }

    /**
     * @param string      $key            config name
     * @param string|null $salesChannelId sales channel
     *
     * @return array|bool|int|string|null
     */
    private function getInLengowConfig(string $key, string $salesChannelId = null)
    {
        $criteria = new Criteria();
        $criteria->addFilter(new MultiFilter(MultiFilter::CONNECTION_AND, [
            new EqualsFilter(LengowSettingsDefinition::FIELD_SALES_CHANNEL_ID, $salesChannelId),
            new EqualsFilter(LengowSettingsDefinition::FIELD_NAME, $key),
        ]));
        $result = $this->settingsRepository->search($criteria, Context::createDefaultContext())
            ->getEntities()
            ->getElements();
        if (empty($result)) {
            return null;
        }
        $value = $result[array_key_first($result)]->getValue();
        if (isset(self::$lengowSettings[$key][self::PARAM_RETURN])) {
            switch (self::$lengowSettings[$key][self::PARAM_RETURN]) {
                case self::RETURN_TYPE_BOOLEAN:
                    return (bool) $value;
                case self::RETURN_TYPE_INTEGER:
                    return (int) $value;
                case self::RETURN_TYPE_ARRAY:
                    return $value ? explode(';', trim(str_replace(["\r\n", ',', ' '], ';', $value), ';')) : [];
                case self::RETURN_TYPE_STRING:
                    return (string) $value;
            }
        }

        return $value;
    }

    /**
     * @param string      $key            config name
     * @param mixed       $value          new config value
     * @param string|null $salesChannelId sales channel
     */
    private function setInLengowConfig(
        string $key,
        string $value,
        string $salesChannelId = null
    ): ?EntityWrittenContainerEvent {
        $id = $this->getId($key, $salesChannelId, true);
        $data = [
            LengowSettingsDefinition::FIELD_ID => $id ?? Uuid::randomHex(),
            LengowSettingsDefinition::FIELD_SALES_CHANNEL_ID => $salesChannelId,
            LengowSettingsDefinition::FIELD_NAME => $key,
            LengowSettingsDefinition::FIELD_VALUE => $value,
        ];
        try {
            return $this->settingsRepository->upsert([$data], Context::createDefaultContext());
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * @param string      $key            config name
     * @param string      $value          new config value
     * @param string|null $salesChannelId sales channel
     */
    private function setInShopwareConfig(string $key, string $value, string $salesChannelId = null): void
    {
        $this->systemConfigService->set($key, $value, $salesChannelId);
    }

    /**
     * @param string      $key            config name
     * @param string|null $salesChannelId sales channel
     */
    private function getId(string $key, string $salesChannelId = null, bool $lengowSetting = false): ?string
    {
        $criteria = new Criteria();
        if ($lengowSetting) {
            $criteria->addFilter(
                new EqualsFilter(LengowSettingsDefinition::FIELD_NAME, $key),
                new EqualsFilter(LengowSettingsDefinition::FIELD_SALES_CHANNEL_ID, $salesChannelId)
            );
            $ids = $this->settingsRepository->searchIds($criteria, Context::createDefaultContext())->getIds();
        } else {
            $criteria->addFilter(
                new EqualsFilter('configurationKey', $key),
                new EqualsFilter('salesChannelId', $salesChannelId)
            );
            $ids = $this->systemConfigRepository->searchIds($criteria, Context::createDefaultContext())->getIds();
        }

        return array_shift($ids);
    }

    /**
     * create default config linked to salesChannel in database
     * Since this method is static, you have to pass the repository as arguments.
     *
     * @param EntityRepository $salesChannelRepository   shopware sales channel repository
     * @param EntityRepository $shippingMethodRepository shopware shipping method repository
     * @param EntityRepository $settingsRepository       lengow settings repository
     */
    public static function createDefaultSalesChannelConfig(
        EntityRepository $salesChannelRepository,
        EntityRepository $shippingMethodRepository,
        EntityRepository $settingsRepository
    ): void {
        $salesChannels = $salesChannelRepository->search(new Criteria(), Context::createDefaultContext());
        $config = [];
        foreach (self::$lengowSettings as $key => $lengowSetting) {
            if (isset($lengowSetting[self::PARAM_SHOP])) {
                foreach ($salesChannels as $salesChannel) {
                    if (self::lengowSettingExist($settingsRepository, $key, $salesChannel->getId())) {
                        continue;
                    }
                    // special case
                    if (self::DEFAULT_IMPORT_CARRIER_ID === $key
                        || self::DEFAULT_EXPORT_CARRIER_ID === $key) {
                        $config[] = [
                            LengowSettingsDefinition::FIELD_SALES_CHANNEL_ID => $salesChannel->getId(),
                            LengowSettingsDefinition::FIELD_NAME => $key,
                            LengowSettingsDefinition::FIELD_VALUE => $shippingMethodRepository
                                ->search(new Criteria([$salesChannel->getId()]), Context::createDefaultContext())
                                ->first(),
                        ];
                        continue;
                    }
                    $config[] = [
                        LengowSettingsDefinition::FIELD_SALES_CHANNEL_ID => $salesChannel->getId(),
                        LengowSettingsDefinition::FIELD_NAME => $key,
                        LengowSettingsDefinition::FIELD_VALUE => $lengowSetting[self::PARAM_DEFAULT_VALUE],
                    ];
                }
            } else {
                if (self::lengowSettingExist($settingsRepository, $key)) {
                    continue;
                }
                $config[] = [
                    LengowSettingsDefinition::FIELD_SALES_CHANNEL_ID => null,
                    LengowSettingsDefinition::FIELD_NAME => $key,
                    LengowSettingsDefinition::FIELD_VALUE => $lengowSetting[self::PARAM_DEFAULT_VALUE],
                ];
            }
        }
        if (!empty($config)) {
            $settingsRepository->create($config, Context::createDefaultContext());
        }
    }

    /**
     * Check if lengow setting already created.
     *
     * @param EntityRepository $settingsRepository lengow settings repository
     * @param string           $key                config name
     * @param string|null      $salesChannelId     sales channel
     */
    public static function lengowSettingExist(
        EntityRepository $settingsRepository,
        string $key,
        string $salesChannelId = null
    ): bool {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter(LengowSettingsDefinition::FIELD_NAME, $key));
        if ($salesChannelId) {
            $criteria->addFilter(new EqualsFilter(LengowSettingsDefinition::FIELD_SALES_CHANNEL_ID, $salesChannelId));
        }
        $settingsCollection = $settingsRepository
            ->search($criteria, Context::createDefaultContext())
            ->getEntities();

        return 0 !== $settingsCollection->count();
    }

    /**
     * This method delete the lengow_settings configuration related to the sales channel deleted.
     *
     * @param EntityDeletedEvent $event Shopware entity deleted event
     */
    public function deleteSalesChannelConfig(EntityDeletedEvent $event): void
    {
        $entityWriteResults = $event->getWriteResults();
        foreach ($entityWriteResults as $entityWriteResult) {
            $lengowSettingsCriteria = new Criteria();
            $lengowSettingsCriteria->addFilter(
                new EqualsFilter(LengowSettingsDefinition::FIELD_SALES_CHANNEL_ID, $entityWriteResult->getPrimaryKey())
            );
            $salesChannelConfig = $this->settingsRepository->search(
                $lengowSettingsCriteria,
                Context::createDefaultContext()
            );
            foreach ($salesChannelConfig->getEntities() as $lengowSettingEntity) {
                $this->settingsRepository->delete(
                    [
                        [LengowSettingsDefinition::FIELD_ID => $lengowSettingEntity->id],
                    ],
                    Context::createDefaultContext()
                );
            }
        }
    }

    /**
     * Get export webservice links.
     *
     * @param string $salesChannelId the sales channel id needed to construct url
     */
    public function getFeedUrl(string $salesChannelId): string
    {
        $sep = DIRECTORY_SEPARATOR;

        return $this->environmentInfoProvider->getBaseUrl($salesChannelId)
            .$sep.EnvironmentInfoProvider::LENGOW_CONTROLLER.$sep.EnvironmentInfoProvider::ACTION_EXPORT.'?'
            .LengowExport::PARAM_SALES_CHANNEL_ID.'='.$salesChannelId.'&'
            .LengowExport::PARAM_TOKEN.'='.$this->getToken($salesChannelId);
    }

    /**
     * Get cron webservice links.
     */
    public function getCronUrl(): string
    {
        $sep = DIRECTORY_SEPARATOR;

        return $this->environmentInfoProvider->getBaseUrl()
            .$sep.EnvironmentInfoProvider::LENGOW_CONTROLLER.$sep.EnvironmentInfoProvider::ACTION_CRON.'?'
            .LengowImport::PARAM_TOKEN.'='.$this->getToken();
    }

    /**
     * Get toolbox webservice links.
     */
    public function getToolboxUrl(): string
    {
        $sep = DIRECTORY_SEPARATOR;

        return $this->environmentInfoProvider->getBaseUrl()
            .$sep.EnvironmentInfoProvider::LENGOW_CONTROLLER.$sep.EnvironmentInfoProvider::ACTION_TOOLBOX.'?'
            .LengowToolbox::PARAM_TOKEN.'='.$this->getToken();
    }

    /**
     * Check if send return tracking number is enabled.
     */
    public function isSendReturnTrackingNumberEnabled(): bool
    {
        return (bool) $this->get(self::ACTION_SEND_RETURN_TRACKING_NUMBER);
    }
}
