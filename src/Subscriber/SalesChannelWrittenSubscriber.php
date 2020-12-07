<?php declare(strict_types=1);

namespace Lengow\Connector\Subscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityDeletedEvent;
use Shopware\Core\System\SalesChannel\SalesChannelEvents;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Context;
use Lengow\Connector\Util\EnvironmentInfoProvider;
use Lengow\Connector\Service\LengowConfiguration;

/**
 * Class SalesChannelWrittenSubscriber
 * @package Lengow\Connector\Subscriber
 */
class SalesChannelWrittenSubscriber implements EventSubscriberInterface
{
    /**
     * @var EntityRepositoryInterface lengow settings repository
     */
    private $lengowSettingsRepository;

    /**
     * @var LengowConfiguration Lengow configuration service
     */
    private $lengowConfiguration;

    /**
     * ProductExtensionSubscriber constructor
     *
     * @param EntityRepositoryInterface $lengowSettingsRepository Lengow settings repository
     * @param LengowConfiguration  $lengowConfiguration configuration service
     */
    public function __construct(EntityRepositoryInterface $lengowSettingsRepository, LengowConfiguration $lengowConfiguration) {
        $this->lengowConfiguration = $lengowConfiguration;
        $this->lengowSettingsRepository = $lengowSettingsRepository;
    }

    /**
     * Mandatory for subscriber
     * SALES_CHANNEL_WRITTEN is fired when a sales channel is created
     * SALES_CHANNEL_DELETED is fired when a sales channel is deleted
     *
     * @return array
     */
    public static function getSubscribedEvents(): array
    {
        return [
            SalesChannelEvents::SALES_CHANNEL_WRITTEN => 'createSalesChannelDefaultLengowConfig',
            SalesChannelEvents::SALES_CHANNEL_DELETED => 'deleteSalesChannelConfig',
        ];
    }

    /**
     * This method create the lengow_settings default configuration when a sales channel is created
     *
     * @param EntityWrittenEvent $event
     */
    public function createSalesChannelDefaultLengowConfig(EntityWrittenEvent $event) : void
    {
        $entityWriteResults = $event->getWriteResults();
        foreach ($entityWriteResults as $entityWriteResult) {
            $lengowSettingsCriteria = new Criteria();
            $lengowSettingsCriteria->addFilter(
                new EqualsFilter('salesChannelId', $entityWriteResult->getPrimaryKey())
            );
            $salesChannelConfig = $this->lengowSettingsRepository->search(
                $lengowSettingsCriteria,
                Context::createDefaultContext()
            );
            if (count($salesChannelConfig->getEntities()) > 0) {
                return ;
            }
            $defaultSalesChannelConfig = [];
            $defaultShippingMethod = EnvironmentInfoProvider::getShippingMethodDefaultValue(
                $entityWriteResult->getPrimaryKey(),
                $this->lengowSettingsRepository
            );
            foreach (LengowConfiguration::$lengowSettings as $key => $lengowSetting) {
                if (isset($lengowSetting['channel']) && $lengowSetting['channel']) {
                    if ($key === LengowConfiguration::LENGOW_IMPORT_DEFAULT_SHIPPING_METHOD
                        || $key === LengowConfiguration::LENGOW_EXPORT_DEFAULT_SHIPPING_METHOD) {
                        $defaultSalesChannelConfig[] = [
                            'salesChannelId' => $entityWriteResult->getPrimaryKey(),
                            'name' => $key,
                            'value' => $defaultShippingMethod,
                        ];
                    } else {
                        $defaultSalesChannelConfig[] = [
                            'salesChannelId' => $entityWriteResult->getPrimaryKey(),
                            'name' => $key,
                            'value' => LengowConfiguration::$lengowSettings[$key]['default_value'],
                        ];
                    }
                }
            }
            $this->lengowSettingsRepository->create($defaultSalesChannelConfig, Context::createDefaultContext());
        }
    }

    /**
     * This method delete the lengow_settings configuration related to the sales channel deleted
     *
     * @param EntityDeletedEvent $event
     */
    public function deleteSalesChannelConfig(EntityDeletedEvent $event) : void
    {
        $this->lengowConfiguration->deleteSalesChannelConfig($event);
    }
}
