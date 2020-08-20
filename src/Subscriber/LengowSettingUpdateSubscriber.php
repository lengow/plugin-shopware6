<?php declare(strict_types=1);

namespace Lengow\Connector\Subscriber;

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
     * LengowSettingUpdateSubscriber constructor
     * @param LengowConfiguration $lengowConfiguration
     */
    public function __construct(LengowConfiguration $lengowConfiguration)
    {
        $this->lengowConfiguration = $lengowConfiguration;
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
                    $this->lengowConfiguration->checkAndLog($key, $value, $salesChannelId);
                }
            }
        }
    }
}
