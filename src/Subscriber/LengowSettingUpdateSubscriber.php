<?php declare(strict_types=1);

namespace Lengow\Connector\Subscriber;

use Lengow\Connector\Service\LengowLog;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Shopware\Core\System\SystemConfig\SystemConfigDefinition;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Lengow\Connector\Service\LengowConfiguration;

/**
 * Class LengowSettingUpdateSubscriber
 * @package Lengow\Connector\Subcriber
 */
class LengowSettingUpdateSubscriber implements EventSubscriberInterface
{
    /**
     * @var LengowConfiguration Lengow configuration service
     */
    private $lengowConfiguration;

    /**
     * @var LengowLog Lengow log service
     */
    private $lengowLog;

    /**
     * LengowSettingUpdateSubscriber constructor
     * @param LengowConfiguration $lengowConfiguration
     * @param LengowLog $lengowLog
     */
    public function __construct(LengowConfiguration $lengowConfiguration, LengowLog $lengowLog)
    {
        $this->lengowConfiguration = $lengowConfiguration;
        $this->lengowLog = $lengowLog;
    }

    /**
     * Get Subscribed events
     *
     * @return array
     */
    public static function getSubscribedEvents(): array
    {
        return [
            EntityWrittenContainerEvent::class => 'detectLengowSettingEntryPoints',
        ];
    }

    /**
     * Detects when Lengow configuration is changed
     *
     * @param EntityWrittenContainerEvent $event Shopware entity written container event
     */
    public function detectLengowSettingEntryPoints(EntityWrittenContainerEvent $event): void
    {
        if ($event->getEventByEntityName(SystemConfigDefinition::ENTITY_NAME) !== null) {
            /** @var EntityWrittenEvent $entityEvent */
            $entityEvent = $event->getEvents()->first();
            $payLoads = $entityEvent->getPayloads();
            if (!empty($payLoads)) {
                $key = $payLoads[0]['configurationKey'];
                $value = $payLoads[0]['configurationValue'];
                $salesChannelId = $payLoads[0]['salesChannelId'];
                if (stristr($key, LengowConfiguration::LENGOW_SETTING_PATH)) {
                    $key = str_replace(LengowConfiguration::LENGOW_SETTING_PATH, '', $key);
                    $this->checkAndLog($key, $value, $salesChannelId);
                }
            }
        }
    }

    /**
     * Check value and create a log if necessary
     *
     * @param string $key name of Lengow setting
     * @param mixed $value setting value
     * @param string|null $salesChannelId Shopware sales channel id
     */
    private function checkAndLog(string $key, $value, string $salesChannelId = null): void
    {
        if (!array_key_exists($key, LengowConfiguration::$lengowSettings)) {
            return;
        }
        $setting = LengowConfiguration::$lengowSettings[$key];
        $oldValue = $this->lengowConfiguration->get($key, $salesChannelId);
        if (isset($setting['type']) && $setting['type'] === 'boolean') {
            $value = (int)$value;
            $oldValue = (int)$oldValue;
        } elseif (isset($setting['type']) && $setting['type'] === 'array') {
            $value = implode(',', $value);
            $oldValue = implode(',', $oldValue);
        }
        if ($oldValue === $value) {
            return;
        }
        if (isset($setting['secret'])) {
            $value = preg_replace("/[a-zA-Z0-9]/", '*', $value);
            $oldValue = preg_replace("/[a-zA-Z0-9]/", '*', $oldValue);
        }
        if ($salesChannelId === null && isset($setting['global'])) {
            $this->lengowLog->write(
                LengowLog::CODE_SETTING,
                $this->lengowLog->encodeMessage('log.setting.setting_change', [
                    'key' => $key,
                    'old_value' => $oldValue,
                    'value' => $value,
                ])
            );
        }
        if ($salesChannelId && isset($setting['channel'])) {
            $this->lengowLog->write(
                LengowLog::CODE_SETTING,
                $this->lengowLog->encodeMessage('log.setting.setting_change_for_sales_channel', [
                    'key' => $key,
                    'old_value' => $oldValue,
                    'value' => $value,
                    'sales_channel_id' => $salesChannelId,
                ])
            );
        }
        // save last update date for a specific settings (change synchronisation interval time)
        if (isset($setting['update'])) {
            $this->lengowConfiguration->set(LengowConfiguration::LENGOW_LAST_SETTING_UPDATE, (string)time());
        }
    }
}
