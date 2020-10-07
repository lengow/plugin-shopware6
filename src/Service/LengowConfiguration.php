<?php declare(strict_types=1);

namespace Lengow\Connector\Service;

use DateTime;
use DateTimeZone;
use Exception;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\MultiFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityDeletedEvent;
use Shopware\Core\System\SalesChannel\SalesChannelCollection;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;
use Shopware\Core\Framework\Uuid\Uuid;
use Lengow\Connector\Util\EnvironmentInfoProvider;

/**
 * Class LengowConfiguration
 * @package Lengow\Connector\Service
 */
class LengowConfiguration
{
    public const LENGOW_SETTING_PATH = 'Connector.config.';

    /* Settings database key */
    public const LENGOW_GLOBAL_TOKEN = 'lengowGlobalToken';
    public const LENGOW_CHANNEL_TOKEN = 'lengowChannelToken';
    public const LENGOW_ACCOUNT_ID = 'lengowAccountId';
    public const LENGOW_ACCESS_TOKEN = 'lengowAccessToken';
    public const LENGOW_SECRET_TOKEN = 'lengowSecretToken';
    public const LENGOW_AUTH_TOKEN = 'lengowAuthorizationToken';
    public const LENGOW_LAST_AUTH_TOKEN_UPDATE = 'lengowLastAuthorizationTokenUpdate';
    public const LENGOW_SALES_CHANNEL_ENABLED = 'lengowStoreEnabled';
    public const LENGOW_CATALOG_ID = 'lengowCatalogId';
    public const LENGOW_IP_ENABLED = 'lengowIpEnabled';
    public const LENGOW_AUTHORIZED_IP = 'lengowAuthorizedIp';
    public const LENGOW_TRACKING_ENABLED = 'lengowTrackingEnabled';
    public const LENGOW_TRACKING_ID = 'lengowTrackingId';
    public const LENGOW_ACCOUNT_STATUS = 'lengowAccountStatus';
    public const LENGOW_ACCOUNT_STATUS_UPDATE = 'lengowAccountStatusUpdate';
    public const LENGOW_OPTION_CMS_UPDATE = 'lengowOptionCmsUpdate';
    public const LENGOW_CATALOG_UPDATE = 'lengowCatalogUpdate';
    public const LENGOW_EXPORT_FORMAT = 'lengowExportFormat';
    public const LENGOW_MARKETPLACE_UPDATE = 'lengowMarketplaceUpdate';
    public const LENGOW_LAST_SETTING_UPDATE = 'lengowLastSettingUpdate';
    public const LENGOW_PLUGIN_DATA = 'lengowPluginData';
    public const LENGOW_PLUGIN_DATA_UPDATE = 'lengowPluginDataUpdate';
    public const LENGOW_REPORT_MAIL_ENABLED = 'lengowReportMailEnabled';
    public const LENGOW_REPORT_MAIL_ADDRESS = 'lengowReportMailAddress';
    public const LENGOW_DEBUG_ENABLED = 'lengowDebugEnabled';
    public const LENGOW_CURRENCY_CONVERSION_ENABLED = 'lengowCurrencyConversion';
    public const LENGOW_B2B_ENABLED = 'lengowImportB2b';
    public const LENGOW_LAST_IMPORT_CRON = 'lengowLastImportCron';
    public const LENGOW_LAST_IMPORT_MANUAL = 'lengowLastImportManual';
    public const LENGOW_LAST_ACTION_SYNC = 'lengowLastActionSync';
    public const LENGOW_TIMEZONE = 'lengowTimezone';
    public const LENGOW_EXPORT_SELECTION_ENABLED = 'lengowSelectionEnabled';
    public const LENGOW_EXPORT_DISABLED_PRODUCT = 'lengowExportDisabledProduct';
    public const LENGOW_EXPORT_DEFAULT_SHIPPING_METHOD = 'lengowExportDefaultShippingMethod';
    public const LENGOW_EXPORT_OUT_OF_STOCK_ENABLED = 'lengowExportOutOfStock';
    public const LENGOW_EXPORT_VARIATION_ENABLED = 'lengowExportVariation';
    public const LENGOW_LAST_EXPORT = 'lengowLastExport';
    public const LENGOW_IMPORT_DEFAULT_SHIPPING_METHOD = 'lengowImportDefaultShippingMethod';
    public const LENGOW_IMPORT_DAYS = 'lengowImportDays';
    public const LENGOW_IMPORT_SHIPPED_BY_MKTP = 'lengowImportShipMpEnabled';
    public const LENGOW_IMPORT_MKTP_DECR_STOCK = 'lengowImportStockShipMp';
    public const LENGOW_IMPORT_IN_PROGRESS = 'lengowImportInProgress';

    /**
     * @var string Lengow default timezone
     */
    public const DEFAULT_TIMEZONE = 'Etc/Greenwich';

    /**
     * @var string log date time format
     */
    public const DEFAULT_DATE_TIME_FORMAT = 'Y-m-d H:i:s';

    /**
     * @var string api date time format
     */
    public const API_DATE_TIME_FORMAT = 'c';

    /**
     * @var array specific Lengow settings in lengow_settings table
     */
    public static $lengowSettings = [
        self::LENGOW_GLOBAL_TOKEN => [
            'lengow_settings' => true,
            'global' => true,
            'default_value' => '',
        ],
        self::LENGOW_CHANNEL_TOKEN => [
            'channel' => true,
            'lengow_settings' => true,
            'default_value' => '',
        ],
        self::LENGOW_ACCOUNT_ID => [
            'global' => true,
            'lengow_settings' => true,
            'default_value' => '',
        ],
        self::LENGOW_ACCESS_TOKEN => [
            'global' => true,
            'secret' => true,
            'lengow_settings' => true,
            'default_value' => '',
        ],
        self::LENGOW_SECRET_TOKEN => [
            'global' => true,
            'secret' => true,
            'lengow_settings' => true,
            'default_value' => '',
        ],
        self::LENGOW_AUTH_TOKEN => [
            'lengow_settings' => true,
            'global' => true,
            'export' => false,
            'default_value' => '',
        ],
        self::LENGOW_LAST_AUTH_TOKEN_UPDATE => [
            'lengow_settings' => true,
            'global' => true,
            'export' => false,
            'default_value' => '',
        ],
        self::LENGOW_SALES_CHANNEL_ENABLED => [
            'lengow_settings' => true,
            'channel' => true,
            'type' => 'boolean',
            'default_value' => '0',
        ],
        self::LENGOW_CATALOG_ID => [
            'lengow_settings' => true,
            'channel' => true,
            'update' => true,
            'type' => 'array',
            'default_value' => '',
        ],
        self::LENGOW_IP_ENABLED => [
            'lengow_settings' => true,
            'global' => true,
            'type' => 'boolean',
            'default_value' => '0',
        ],
        self::LENGOW_AUTHORIZED_IP => [
            'lengow_settings' => true,
            'global' => true,
            'type' => 'array',
            'default_value' => '',
        ],
        self::LENGOW_TRACKING_ENABLED => [
            'lengow_settings' => true,
            'global' => true,
            'type' => 'boolean',
            'default_value' => '0',
        ],
        self::LENGOW_TRACKING_ID => [
            'lengow_settings' => true,
            'global' => true,
            'default_value' => 'id',
        ],
        self::LENGOW_ACCOUNT_STATUS => [
            'lengow_settings' => true,
            'global' => true,
            'export' => false,
            'default_value' => '',
        ],
        self::LENGOW_ACCOUNT_STATUS_UPDATE => [
            'lengow_settings' => true,
            'global' => true,
            'default_value' => '',
        ],
        self::LENGOW_OPTION_CMS_UPDATE => [
            'lengow_settings' => true,
            'global' => true,
            'default_value' => '',
        ],
        self::LENGOW_CATALOG_UPDATE => [
            'lengow_settings' => true,
            'global' => true,
            'default_value' => '',
        ],
        self::LENGOW_EXPORT_FORMAT => [
            'lengow_settings' => true,
            'global' => true,
            'default_value' => 'csv',
        ],
        self::LENGOW_EXPORT_VARIATION_ENABLED => [
            'lengow_settings' => true,
            'global' => true,
            'type' => 'boolean',
            'default_value' => '0',
        ],
        self::LENGOW_MARKETPLACE_UPDATE => [
            'lengow_settings' => true,
            'global' => true,
            'default_value' => '',
        ],
        self::LENGOW_LAST_SETTING_UPDATE => [
            'lengow_settings' => true,
            'global' => true,
            'default_value' => '',
        ],
        self::LENGOW_PLUGIN_DATA => [
            'lengow_settings' => true,
            'global' => true,
            'export' => false,
            'default_value' => '',
        ],
        self::LENGOW_PLUGIN_DATA_UPDATE => [
            'lengow_settings' => true,
            'global' => true,
            'export' => false,
            'default_value' => '',
        ],
        self::LENGOW_EXPORT_SELECTION_ENABLED => [
            'lengow_settings' => true,
            'channel' => true,
            'type' => 'boolean',
            'default_value' => '0',
        ],
        self::LENGOW_EXPORT_DISABLED_PRODUCT => [
            'lengow_settings' => true,
            'channel' => true,
            'type' => 'boolean',
            'default_value' => '0',
        ],
        self::LENGOW_EXPORT_DEFAULT_SHIPPING_METHOD => [
            'lengow_settings' => true,
            'channel' => true,
            'default_value' => '',
        ],
        self::LENGOW_LAST_EXPORT => [
            'lengow_settings' => true,
            'channel' => true,
            'default_value' => '',
        ],
        self::LENGOW_IMPORT_DAYS => [
            'lengow_settings' => true,
            'global' => true,
            'update' => true,
            'default_value' => '3',
        ],
        self::LENGOW_IMPORT_DEFAULT_SHIPPING_METHOD => [
            'lengow_settings' => true,
            'channel' => true,
            'default_value' => '',
        ],
        self::LENGOW_REPORT_MAIL_ENABLED => [
            'lengow_settings' => true,
            'global' => true,
            'type' => 'boolean',
            'default_value' => '1',
        ],
        self::LENGOW_REPORT_MAIL_ADDRESS => [
            'lengow_settings' => true,
            'global' => true,
            'type' => 'array',
            'default_value' => '',
        ],
        self::LENGOW_IMPORT_SHIPPED_BY_MKTP => [
            'lengow_settings' => true,
            'global' => true,
            'type' => 'boolean',
            'default_value' => '0',
        ],
        self::LENGOW_IMPORT_MKTP_DECR_STOCK => [
            'lengow_settings' => true,
            'global' => true,
            'type' => 'boolean',
            'default_value' => '0',
        ],
        self::LENGOW_DEBUG_ENABLED => [
            'lengow_settings' => true,
            'global' => true,
            'type' => 'boolean',
            'default_value' => '0',
        ],
        self::LENGOW_EXPORT_OUT_OF_STOCK_ENABLED => [
            'lengow_settings' => true,
            'global' => true,
            'type' => 'boolean',
            'default_value' => '0',
        ],
        self::LENGOW_CURRENCY_CONVERSION_ENABLED => [
            'global' => true,
            'type' => 'boolean',
            'default_value' => '1',
        ],
        self::LENGOW_B2B_ENABLED => [
            'lengow_settings' => true,
            'global' => true,
            'type'   => 'boolean',
            'default_value' => '0',
        ],
        self::LENGOW_IMPORT_IN_PROGRESS => [
            'lengow_settings' => true,
            'global' => true,
            'default_value' => '',
        ],
        self::LENGOW_LAST_IMPORT_CRON => [
            'lengow_settings' => true,
            'global' => true,
            'default_value' => '',
        ],
        self::LENGOW_LAST_IMPORT_MANUAL => [
            'lengow_settings' => true,
            'global' => true,
            'default_value' => '',
        ],
        self::LENGOW_LAST_ACTION_SYNC => [
            'lengow_settings' => true,
            'global' => true,
            'default_value' => '',
        ],
        self::LENGOW_TIMEZONE => [
            'lengow_settings' => true,
            'global' => true,
            'default_value' => self::DEFAULT_TIMEZONE,
        ],
    ];

    /**
     * @var EntityRepositoryInterface $settingsRepository Lengow settings access
     */
    private $settingsRepository;

    /**
     * @var SystemConfigService $systemConfigService shopware settings access
     */
    private $systemConfigService;

    /**
     * @var EntityRepositoryInterface $systemConfigRepository shopware settings repository
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
     * LengowConfiguration constructor
     *
     * @param EntityRepositoryInterface $settingsRepository Lengow settings access
     * @param SystemConfigService $systemConfigService Shopware settings access
     * @param EntityRepositoryInterface $systemConfigRepository shopware settings repository
     * @param EnvironmentInfoProvider $environmentInfoProvider Environment info provider utility
     */
    public function __construct(
        EntityRepositoryInterface $settingsRepository,
        SystemConfigService $systemConfigService,
        EntityRepositoryInterface $systemConfigRepository,
        EnvironmentInfoProvider $environmentInfoProvider
    )
    {
        $this->settingsRepository = $settingsRepository;
        $this->systemConfigService = $systemConfigService;
        $this->systemConfigRepository = $systemConfigRepository;
        $this->environmentInfoProvider = $environmentInfoProvider;
    }

    /**
     * @param string $key config name
     * @param string|null $salesChannelId sales channel
     *
     * @return mixed
     */
    public function get(string $key, ?string $salesChannelId = null)
    {
        // get a Lengow configuration
        if (array_key_exists($key, self::$lengowSettings)) {
            $setting = self::$lengowSettings[$key];
            if ($setting['lengow_settings'] ?? false) {
                return $this->getInLengowConfig($key, $salesChannelId);
            }
            return null;
        }
        // get a Shopware configuration
        return $this->getInShopwareConfig($key, $salesChannelId);
    }

    /**
     * @param string $key config name
     * @param string $value new value for config
     * @param string|null $salesChannelId sales channel
     *
     * @return EntityWrittenContainerEvent|void|null
     */
    public function set(string $key, string $value, ?string $salesChannelId = null)
    {
        // set a Lengow configuration
        if (array_key_exists($key, self::$lengowSettings)) {
            $setting = self::$lengowSettings[$key];
            if ($setting['lengow_settings'] ?? false) {
                return $this->setInLengowConfig($key, $value, $salesChannelId);
            }
            return null;
        }
        // set a Shopware configuration
        return $this->setInShopwareConfig($key, $value, $salesChannelId);
    }

    /**
     * Get global token or channel token
     *
     * @param string|null $salesChannelId Shopware sales channel id
     *
     * @return string
     */
    public function getToken(string $salesChannelId = null): string
    {
        if ($salesChannelId) {
            $token = $this->get(self::LENGOW_CHANNEL_TOKEN, $salesChannelId);
        } else {
            $token = $this->get(self::LENGOW_GLOBAL_TOKEN);
        }
        if ($token && $token !== '') {
            return $token;
        }
        return $this->generateToken($salesChannelId);
    }

    /**
     * Generate new token
     *
     * @param string|null $salesChannelId Shopware sales channel id
     *
     * @return string
     */
    public function generateToken(string $salesChannelId = null): string
    {
        $token = bin2hex(openssl_random_pseudo_bytes(16));
        if ($salesChannelId) {
            $this->set(self::LENGOW_CHANNEL_TOKEN, $token, $salesChannelId);
        } else {
            $this->set(self::LENGOW_GLOBAL_TOKEN, $token);
        }
        return $token;
    }

    /**
     * Get a sales channel with a given token
     *
     * @param string $token sales channel token
     *
     * @return SalesChannelEntity|null
     */
    public function getSalesChannelByToken(string $token): ?SalesChannelEntity
    {
        /** @var SalesChannelCollection $salesChannelCollection */
        $salesChannelCollection = $this->environmentInfoProvider->getActiveSalesChannels();
        foreach ($salesChannelCollection as $salesChannel) {
            if ($this->get(self::LENGOW_CHANNEL_TOKEN, $salesChannel->getId()) === $token) {
                return $salesChannel;
            }
        }
        return null;
    }

    /**
     * Get valid account id / access token / secret token
     *
     * @return array
     */
    public function getAccessIds(): array
    {
        $accountId = (int)$this->get(self::LENGOW_ACCOUNT_ID);
        $accessToken = $this->get(self::LENGOW_ACCESS_TOKEN);
        $secretToken = $this->get(self::LENGOW_SECRET_TOKEN);
        if ($accountId !== 0 && !empty($accessToken) && !empty($secretToken)) {
            return [$accountId, $accessToken, $secretToken];
        }
        return [null, null, null];
    }

    /**
     * Check if new merchant
     *
     * @return bool
     */
    public function isNewMerchant(): bool
    {
        list($accountId, $accessToken, $secretToken) = $this->getAccessIds();
        return !($accountId !== null && $accessToken !== null && $secretToken !== null);
    }

    /**
     * Get catalog ids for a specific shop
     *
     * @param string $salesChannelId Shopware sales channel id
     *
     * @return array
     */
    public function getCatalogIds(string $salesChannelId): array
    {
        $catalogIds = [];
        $salesChannelCatalogIds = $this->get(self::LENGOW_CATALOG_ID, $salesChannelId);
        if (!empty($salesChannelCatalogIds)) {
            foreach ($salesChannelCatalogIds as $catalogId) {
                $catalogId = trim(str_replace(["\r\n", ',', '-', '|', ' ', '/'], ';', $catalogId), ';');
                if (is_numeric($catalogId) && (int)$catalogId > 0) {
                    $catalogIds[] = (int)$catalogId;
                }
            }
        }
        return $catalogIds;
    }

    /**
     * Set catalog ids for a specific sales channel
     *
     * @param array $catalogIds Lengow catalog ids
     * @param string $salesChannelId Shopware sales channel id
     *
     * @return bool
     */
    public function setCatalogIds(array $catalogIds, string $salesChannelId): bool
    {
        $valueChange = false;
        $salesChannelCatalogIds = $this->getCatalogIds($salesChannelId);
        foreach ($catalogIds as $catalogId) {
            if ($catalogId > 0 && is_numeric($catalogId) && !in_array($catalogId, $salesChannelCatalogIds, true)) {
                $salesChannelCatalogIds[] = (int)$catalogId;
                $valueChange = true;
            }
        }
        $this->set(self::LENGOW_CATALOG_ID, implode(';', $salesChannelCatalogIds), $salesChannelId);
        return $valueChange;
    }

    /**
     * Recovers if a sales channel is active or not
     *
     * @param string $salesChannelId Shopware sales channel id
     *
     * @return bool
     */
    public function salesChannelIsActive(string $salesChannelId): bool
    {
        return $this->get(self::LENGOW_SALES_CHANNEL_ENABLED, $salesChannelId);
    }

    /**
     * Set active sales channel or not
     *
     * @param string $salesChannelId Shopware sales channel id
     *
     * @return bool
     */
    public function setActiveSalesChannel(string $salesChannelId): bool
    {
        $shopIsActive = $this->salesChannelIsActive($salesChannelId);
        $catalogIds = $this->getCatalogIds($salesChannelId);
        $salesChannelHasCatalog = !empty($catalogIds) ? '1' : '0';
        $this->set(self::LENGOW_SALES_CHANNEL_ENABLED, $salesChannelHasCatalog, $salesChannelId);
        return $shopIsActive !== $salesChannelHasCatalog;
    }

    /**
     * Get all report mails
     *
     * @return array
     */
    public function getReportEmailAddress(): array
    {
        $reportEmailAddress = [];
        $emails = $this->get(self::LENGOW_REPORT_MAIL_ADDRESS);
        foreach ($emails as $email) {
            if ($email !== '' && (bool)preg_match('/^\S+\@\S+\.\S+$/', $email)) {
                $reportEmailAddress[] = $email;
            }
        }
        if (empty($reportEmailAddress)) {
            $reportEmailAddress[] = $this->get('core.basicInformation.email');
        }
        return $reportEmailAddress;
    }

    /**
     * Recovers if a store is active or not
     *
     * @return bool
     */
    public function debugModeIsActive(): bool
    {
        return $this->get(self::LENGOW_DEBUG_ENABLED);
    }

    /**
     * Get Lengow timezone for datetime
     *
     * @return string
     */
    public function getLengowTimezone(): string
    {
        if ($this->lengowTimezone === null) {
            $timezone = $this->get(self::LENGOW_TIMEZONE);
            $this->lengowTimezone = $timezone ?? self::DEFAULT_TIMEZONE;
        }
        return  $this->lengowTimezone;
    }

    /**
     * Get date with correct timezone
     *
     * @param int|null $timestamp gmt timestamp
     * @param string $format date format
     *
     * @return string
     */
    public function date(int $timestamp = null, string $format = self::DEFAULT_DATE_TIME_FORMAT): string
    {
        $timestamp = $timestamp ?? time();
        $timezone = $this->getLengowTimezone();
        $dateTime = new DateTime();
        return $dateTime->setTimestamp($timestamp)->setTimezone(new DateTimeZone($timezone))->format($format);
    }

    /**
     * Get GMT date
     *
     * @param int|null $timestamp gmt timestamp
     * @param string $format date format
     *
     * @return string
     */
    public function gmtDate(int $timestamp = null, string $format = self::DEFAULT_DATE_TIME_FORMAT): string
    {
        $timestamp = $timestamp ?? time();
        $dateTime = new DateTime();
        return $dateTime->setTimestamp($timestamp)->format($format);
    }

    /**
     * Get list of Shopware sales channel that have been activated in Lengow
     *
     * @return array
     */
    public function getLengowActiveSalesChannels(): array
    {
        $result = [];
        /** @var SalesChannelCollection $salesChannelCollection */
        $salesChannelCollection = $this->environmentInfoProvider->getActiveSalesChannels();
        foreach ($salesChannelCollection as $salesChannel) {
            /** @var SalesChannelEntity $salesChannel */
            // get Lengow config for this sales channel
            if ($this->get(self::LENGOW_SALES_CHANNEL_ENABLED, $salesChannel->getId())) {
                $result[] = $salesChannel;
            }
        }
        return $result;
    }

    /**
     * @param string $key config name
     * @param string|null $salesChannelId sales channel
     *
     * @return mixed
     */
    private function getInShopwareConfig(string $key, ?string $salesChannelId = null)
    {
        return $this->systemConfigService->get($key, $salesChannelId);
    }

    /**
     * @param string $key config name
     * @param string|null $salesChannelId sales channel
     *
     * @return mixed
     */
    private function getInLengowConfig(string $key, ?string $salesChannelId = null)
    {
        $criteria = new Criteria();
        $criteria->addFilter(new MultiFilter(MultiFilter::CONNECTION_AND, [
            new EqualsFilter('salesChannelId', $salesChannelId),
            new EqualsFilter('name', $key),
        ]));
        $result = $this->settingsRepository->search($criteria, Context::createDefaultContext())
            ->getEntities()
            ->getElements();
        if (empty($result)) {
            return null;
        }
        $value = $result[array_key_first($result)]->getValue();
        if (isset(self::$lengowSettings[$key]['type'])) {
            switch (self::$lengowSettings[$key]['type']) {
                case 'boolean':
                    return (bool)$value;
                case 'array':
                    return explode(';', trim(str_replace(["\r\n", ',', ' '], ';', $value), ';'));
            }
        }
        return $value;
    }

    /**
     * @param string $key config name
     * @param mixed $value new config value
     * @param string|null $salesChannelId sales channel
     *
     * @return EntityWrittenContainerEvent|null
     */
    private function setInLengowConfig(
        string $key,
        string $value,
        ?string $salesChannelId = null
    ): ?EntityWrittenContainerEvent
    {
        $id = $this->getId($key, $salesChannelId, true);
        $data = [
            'id' => $id ?? Uuid::randomHex(),
            'sales_channel_id' => $salesChannelId,
            'name' => $key,
            'value' => $value,
        ];
        try {
            return $this->settingsRepository->upsert([$data], Context::createDefaultContext());
        } catch (Exception $e) {
            return null;
        }
    }

    /**
     * @param string $key config name
     * @param string $value new config value
     * @param string|null $salesChannelId sales channel
     */
    private function setInShopwareConfig(string $key, string $value, ?string $salesChannelId = null): void
    {
        $this->systemConfigService->set($key, $value, $salesChannelId);
    }

    /**
     * @param string $key config name
     * @param string|null $salesChannelId sales channel
     * @param bool $lengowSetting
     *
     * @return string|null
     */
    private function getId(string $key, ?string $salesChannelId = null, $lengowSetting = false): ?string
    {
        $criteria = new Criteria();
        if ($lengowSetting) {
            $criteria->addFilter(
                new EqualsFilter('name', $key),
                new EqualsFilter('salesChannelId', $salesChannelId)
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
     * Since this method is static, you have to pass the repository as arguments
     *
     * @param EntityRepositoryInterface $salesChannelRepository shopware sales channel repository
     * @param EntityRepositoryInterface $shippingMethodRepository shopware shipping method repository
     * @param EntityRepositoryInterface $settingsRepository lengow settings repository
     */
    public static function createDefaultSalesChannelConfig(
        EntityRepositoryInterface $salesChannelRepository,
        EntityRepositoryInterface $shippingMethodRepository,
        EntityRepositoryInterface $settingsRepository
    ): void
    {
        $salesChannels = $salesChannelRepository->search(new Criteria(), Context::createDefaultContext());
        $config = [];
        foreach(self::$lengowSettings as $key => $lengowSetting) {
            if (isset($lengowSetting['channel'])) {
                foreach ($salesChannels as $salesChannel) {
                    // special case
                    if ($key === 'lengowImportDefaultShippingMethod'
                        || $key === 'lengowExportDefaultShippingMethod') {
                        $config[] = [
                            'salesChannelId' => $salesChannel->getId(),
                            'name' => $key,
                            'value' => EnvironmentInfoProvider::getShippingMethodDefaultValue(
                                $salesChannel->getId(),
                                $shippingMethodRepository
                            )
                        ];
                        continue;
                    }
                    $config[] = [
                        'salesChannelId' => $salesChannel->getId(),
                        'name' => $key,
                        'value' => $lengowSetting['default_value'],
                    ];
                }
            } else {
                $config[] = [
                    'salesChannelId' => null,
                    'name' => $key,
                    'value' => $lengowSetting['default_value'],
                ];
            }
        }
        $settingsRepository->create($config, Context::createDefaultContext());
    }

    /**
     * This method delete the lengow_settings configuration related to the sales channel deleted
     *
     * @param EntityDeletedEvent $event Shopware entity deleted event
     */
    public function deleteSalesChannelConfig(EntityDeletedEvent $event) : void
    {
        $entityWriteResults = $event->getWriteResults();
        foreach ($entityWriteResults as $entityWriteResult) {
            $lengowSettingsCriteria = new Criteria();
            $lengowSettingsCriteria->addFilter(
                new EqualsFilter('salesChannelId', $entityWriteResult->getPrimaryKey())
            );
            $salesChannelConfig = $this->settingsRepository->search(
                $lengowSettingsCriteria,
                Context::createDefaultContext()
            );
            foreach ($salesChannelConfig->getEntities() as $lengowSettingEntity) {
                $this->settingsRepository->delete(
                    [
                        ['id' => $lengowSettingEntity->id],
                    ],
                    Context::createDefaultContext()
                );
            }
        }
    }
}
