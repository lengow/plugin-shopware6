<?php declare(strict_types=1);

namespace Lengow\Connector\Subscriber;

use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Lengow\Connector\Entity\Lengow\Settings\SettingsDefinition as LengowSettingsDefinition;
use Lengow\Connector\Service\LengowConfiguration;
use Lengow\Connector\Service\LengowLog;

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
        if ($event->getEventByEntityName(LengowSettingsDefinition::ENTITY_NAME) !== null) {
            if ($event->getEvents() === null) {
                return;
            }
            /** @var EntityWrittenEvent $entityEvent */
            $entityEvent = $event->getEvents()->first();
            $payLoads = $entityEvent->getPayloads();
            if (!empty($payLoads) && isset($payLoads[0]['id'], $payLoads[0]['value'])) {
                $id = $payLoads[0]['id'];
                $value = $payLoads[0]['value'];
                $this->checkAndLog($id, $value);
            }
        }
    }

    /**
     * Check value and create a log if necessary
     *
     * @param string $id Lengow setting id
     * @param string $value setting value
     */
    private function checkAndLog(string $id, string $value): void
    {
        // TODO retrieving the old value for comparison
        $setting = $this->lengowConfiguration->getSettingById($id);
        if ($setting === null || !array_key_exists($setting->getName(), LengowConfiguration::$lengowSettings)) {
            return;
        }
        $settingData = LengowConfiguration::$lengowSettings[$setting->getName()];
        if (isset($settingData['export']) && !$settingData['export']) {
            return;
        }
        if (isset($settingData['secret'])) {
            $value = preg_replace("/[a-zA-Z0-9]/", '*', $value);
        }
        if (isset($settingData['global']) && $setting->getSalesChannel() === null) {
            $this->lengowLog->write(
                LengowLog::CODE_SETTING,
                $this->lengowLog->encodeMessage('log.setting.setting_change', [
                    'key' => $setting->getName(),
                    'value' => $value,
                ])
            );
        }
        if (isset($settingData['channel']) && $setting->getSalesChannel()) {
            $this->lengowLog->write(
                LengowLog::CODE_SETTING,
                $this->lengowLog->encodeMessage('log.setting.setting_change_for_sales_channel', [
                    'key' => $setting->getName(),
                    'value' => $value,
                    'sales_channel_name' => $setting->getSalesChannel()->getName(),
                    'sales_channel_id' => $setting->getSalesChannel()->getId(),
                ])
            );
        }
        // save last update date for a specific settings (change synchronisation interval time)
        if (isset($settingData['update'])) {
            $this->lengowConfiguration->set(LengowConfiguration::LENGOW_LAST_SETTING_UPDATE, (string)time());
        }
    }
}
