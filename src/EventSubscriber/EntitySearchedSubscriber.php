<?php
declare(strict_types=1);

namespace Lengow\Connector\EventSubscriber;

use Lengow\Connector\Entity\Lengow\Settings\SettingsDefinition;
use Lengow\Connector\Service\LengowConfiguration;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntitySearchedEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class EntitySearchedSubscriber implements EventSubscriberInterface
{
    public function __construct(protected EntityRepository $repository)
    {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            EntitySearchedEvent::class => 'onEntitySearched',
        ];
    }

    public function onEntitySearched(EntitySearchedEvent $event): void
    {
        if (Context::CRUD_API_SCOPE !== $event->getContext()->getScope()) {
            return;
        }

        $entityDefinition = $event->getDefinition();
        if (!$entityDefinition instanceof SettingsDefinition) {
            return;
        }

        static $processed = false;
        if ($processed) {
            return;
        }

        $processed = true;
        $criteria = new Criteria();
        $collection = $this->repository->search($criteria, $event->getContext());
        $items = array_map(fn($item) => $item->getName(), $collection->getElements());

        $requiredSettings = array_keys(LengowConfiguration::$lengowSettings);
        $missingSettings = array_diff($requiredSettings, $items);
        if (empty($missingSettings)) {
            return;
        }

        $upsert = [];
        foreach ($missingSettings as $setting) {
            $upsert[] = [
                'name' => $setting,
                'value' => LengowConfiguration::$lengowSettings[$setting][LengowConfiguration::PARAM_DEFAULT_VALUE],
            ];
        }

        $this->repository->upsert($upsert, $event->getContext());
    }
}
