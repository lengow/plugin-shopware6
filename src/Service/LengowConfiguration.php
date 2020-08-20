<?php declare(strict_types=1);

namespace Lengow\Connector\Service;

use Exception;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\MultiFilter;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * Class LengowConfiguration
 * @package Lengow\Connector\Service
 */
class LengowConfiguration
{
    public const LENGOW_SETTING_PATH = 'Connector.config.';

    /**
     * @var array $lengowSettings specific Lengow settings in lengow_settings table
     */
    public static $lengowSettings = [
        'lengowGlobalToken' => [
            'lengow_settings' => true,
            'global' => true,
        ],
        'lengowChannelToken' => [
            'channel' => true,
            'lengow_settings' => true,
        ],
        'lengowAccountId' => [
            'global' => true,
        ],
        'lengowAccessToken' => [
            'global' => true,
            'secret' => true,
        ],
        'lengowSecretToken' => [
            'global' => true,
            'secret' => true,
        ],
        'lengowAuthorizationToken' => [
            'lengow_settings' => true,
            'global' => true,
            'export' => false,
        ],
        'lengowLastAuthorizationTokenUpdate' => [
            'lengow_settings' => true,
            'global' => true,
            'export' => false,
        ],
        'lengowChannelActive' => [
            'channel' => true,
            'type' => 'boolean',
        ],
        'lengowCatalogId' => [
            'channel' => true,
            'update' => true,
            'type' => 'array',
        ],
        'lengowIpEnabled' => [
            'global' => true,
            'type' => 'boolean',
        ],
        'lengowAuthorizedIp' => [
            'global' => true,
            'type' => 'array',
        ],
        'lengowTrackingEnable' => [
            'global' => true,
            'type' => 'boolean',
        ],
        'lengowTrackingId' => [
            'global' => true,
        ],
        'lengowAccountStatus' => [
            'lengow_settings' => true,
            'global' => true,
            'export' => false,
        ],
        'lengowAccountStatusUpdate' => [
            'lengow_settings' => true,
            'global' => true,
        ],
        'lengowOptionCmsUpdate' => [
            'lengow_settings' => true,
            'global' => true,
        ],
        'lengowCatalogUpdate' => [
            'lengow_settings' => true,
            'global' => true,
        ],
        'lengowMarketplaceUpdate' => [
            'lengow_settings' => true,
            'global' => true,
        ],
        'lengowLastSettingUpdate' => [
            'lengow_settings' => true,
            'global' => true,
        ],
        'lengowPluginData' => [
            'lengow_settings' => true,
            'global' => true,
            'export' => false,
        ],
        'lengowPluginDataUpdate' => [
            'lengow_settings' => true,
            'global' => true,
            'export' => false,
        ],
        'lengowExportSelectionEnabled' => [
            'channel' => true,
            'type' => 'boolean',
        ],
        'lengowExportDisabledProduct' => [
            'channel' => true,
            'type' => 'boolean',
        ],
        'lengowDefaultDispatcher' => [
            'channel' => true,
        ],
        'lengowLastExport' => [
            'lengow_settings' => true,
            'channel' => true,
        ],
        'lengowImportDays' => [
            'global' => true,
            'update' => true,
        ],
        'lengowImportDefaultDispatcher' => [
            'channel' => true,
        ],
        'lengowImportReportMailEnabled' => [
            'global' => true,
            'type' => 'boolean',
        ],
        'lengowImportReportMailAddress' => [
            'global' => true,
            'type' => 'array',
        ],
        'lengowImportShipMpEnabled' => [
            'global' => true,
            'type' => 'boolean',
        ],
        'lengowImportStockMpEnabled' => [
            'global' => true,
            'type' => 'boolean',
        ],
        'lengowImportDebugEnabled' => [
            'global' => true,
            'type' => 'boolean',
        ],
        'lengowCurrencyConversion' => [
            'global' => true,
            'type' => 'boolean',
        ],
        'lengowImportB2b' => [
            'global' => true,
            'type'   => 'boolean',
        ],
        'lengowImportInProgress' => [
            'lengow_settings' => true,
            'global' => true,
        ],
        'lengowLastImportCron' => [
            'lengow_settings' => true,
            'global' => true,
        ],
        'lengowLastImportManual' => [
            'lengow_settings' => true,
            'global' => true,
        ],
        'lengowIdWaitingShipment' => [
            'global' => true,
        ],
        'lengowIdShipped' => [
            'global' => true,
        ],
        'lengowIdCanceled' => [
            'global' => true,
        ],
        'lengowIdShippedByMp' => [
            'global' => true,
        ],
        'lengowLastActionSync' => [
            'lengow_settings' => true,
            'global' => true,
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
     * @var LengowLog $lengowLog Lengow log service
     */
    private $lengowLog;

    /**
     * LengowConfiguration constructor
     *
     * @param EntityRepositoryInterface $settingsRepository Lengow settings access
     * @param SystemConfigService $systemConfigService Shopware settings access
     * @param EntityRepositoryInterface $systemConfigRepository shopware settings repository
     * @param LengowLog $lengowLog Lengow log service
     */
    public function __construct(
        EntityRepositoryInterface $settingsRepository,
        SystemConfigService $systemConfigService,
        EntityRepositoryInterface $systemConfigRepository,
        LengowLog $lengowLog
    )
    {
        $this->settingsRepository = $settingsRepository;
        $this->systemConfigService = $systemConfigService;
        $this->systemConfigRepository = $systemConfigRepository;
        $this->lengowLog = $lengowLog;
    }

    /**
     * @param string $key config name
     * @param string|null $salesChannelId sales channel
     *
     * @return mixed
     */
    public function get(string $key, ?string $salesChannelId = null)
    {
        // not a lengow configuration
        if (!array_key_exists($key, self::$lengowSettings)) {
            return null;
        }
        $setting = self::$lengowSettings[$key];
        if ($setting['lengow_settings'] ?? false) {
            return $this->getInLengowConfig($key, $salesChannelId);
        }
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
        // not a lengow configuration
        if (!array_key_exists($key, self::$lengowSettings)) {
            return null;
        }
        $setting = self::$lengowSettings[$key];
        if ($setting['lengow_settings'] ?? false) {
            return $this->setInLengowConfig($key, $value, $salesChannelId);
        }
        return $this->setInShopwareConfig($key, $value, $salesChannelId);
    }

    /**
     * Get valid account id / access token / secret token
     *
     * @return array
     */
    public function getAccessIds(): array
    {
        $accountId = (int)$this->get('lengowAccountId');
        $accessToken = $this->get('lengowAccessToken');
        $secretToken = $this->get('lengowSecretToken');
        if ($accountId !== 0 && !empty($accessToken) && !empty($secretToken)) {
            return [$accountId, $accessToken, $secretToken];
        }
        return [null, null, null];
    }

    /**
     * Check value and create a log if necessary
     *
     * @param string $key name of Lengow setting
     * @param mixed $value setting value
     * @param string|null $salesChannelId Shopware sales channel id
     */
    public function checkAndLog(string $key, $value, string $salesChannelId = null): void
    {
        if (!array_key_exists($key, self::$lengowSettings)) {
            return;
        }
        $setting = self::$lengowSettings[$key];
        $oldValue = $this->get($key, $salesChannelId);
        if (isset($setting['type']) && $setting['type'] === 'boolean') {
            $value = (int)$value;
            $oldValue = (int)$oldValue;
        } elseif (isset($setting['type']) && $setting['type'] === 'array') {
            $value = implode(',', $value);
            $oldValue = implode(',', $oldValue);
        }
        if ($oldValue !== $value) {
            if (isset($setting['secret']) && $setting['secret']) {
                $value = preg_replace("/[a-zA-Z0-9]/", '*', $value);
                $oldValue = preg_replace("/[a-zA-Z0-9]/", '*', $oldValue);
            }
            if ($salesChannelId === null && isset($setting['global']) && $setting['global']) {
                $this->lengowLog->write(
                    LengowLog::CODE_SETTING,
                    $this->lengowLog->encodeMessage(
                        'log.setting.setting_change',
                        [
                            'key' => $key,
                            'old_value' => $oldValue,
                            'value' => $value,
                        ]
                    )
                );
            }
            if ($salesChannelId && isset($setting['channel']) && $setting['channel']) {
                $this->lengowLog->write(
                    LengowLog::CODE_SETTING,
                    $this->lengowLog->encodeMessage(
                        'log.setting.setting_change_for_sales_channel',
                        [
                            'key' => $key,
                            'old_value' => $oldValue,
                            'value' => $value,
                            'sales_channel_id' => $salesChannelId,
                        ]
                    )
                );
            }
            // save last update date for a specific settings (change synchronisation interval time)
            if (isset($setting['update']) && $setting['update']) {
                $this->set('lengowLastSettingUpdate', (string)time());
            }
        }
    }

    /**
     * @param string $key config name
     * @param string|null $salesChannelId sales channel
     *
     * @return mixed
     */
    private function getInShopwareConfig(string $key, ?string $salesChannelId = null)
    {
        return $this->systemConfigService->get(self::LENGOW_SETTING_PATH . $key, $salesChannelId);
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
        $criteria->addFilter(
            new MultiFilter(
                MultiFilter::CONNECTION_AND,
                [
                    new EqualsFilter('salesChannelId', $salesChannelId),
                    new EqualsFilter('name', $key),
                ]
            )
        );
        $result = $this->settingsRepository->search(
            $criteria,
            Context::createDefaultContext()
        );
        $value = (array) $result->getEntities()->getElements();
        if ($value) {
            return $value[array_key_first($value)]->getValue();
        }
        return null;
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
            'updated_at' => new \DateTime()
        ];

        try {
            return $this->settingsRepository->upsert(
                [
                    $data,
                ],
                Context::createDefaultContext()
            );
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
        $this->systemConfigService->set(self::LENGOW_SETTING_PATH . $key, $value, $salesChannelId);
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
}
