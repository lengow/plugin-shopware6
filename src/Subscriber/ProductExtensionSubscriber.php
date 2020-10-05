<?php declare(strict_types=1);

namespace Lengow\Connector\Subscriber;

use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityLoadedEvent;
use Shopware\Core\Content\Product\ProductEvents;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Context;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Lengow\Connector\EntityExtension\ExtensionStructure\ProductExtensionStructure;


/**
 * Class ProductExtensionSubscriber
 * @package Lengow\Connector\Subscriber
 */
class ProductExtensionSubscriber implements EventSubscriberInterface
{

    /**
     * @var EntityRepositoryInterface $lengowProductRepository shopware product repository
     */
    private $lengowProductRepository;

    /**
     * ProductExtensionSubscriber constructor
     *
     * @param EntityRepositoryInterface $lengowProductRepository Lengow product repository
     */
    public function __construct($lengowProductRepository) {
        $this->lengowProductRepository = $lengowProductRepository;
    }

    /**
     * Mandatory for subscriber
     *
     * @return array
     */
    public static function getSubscribedEvents(): array
    {
        return [
            ProductEvents::PRODUCT_LOADED_EVENT => 'onProductsLoaded',
            ProductLoaderCriteriaEvent::class => 'onProductCriteriaLoaded',
        ];
    }

    /**
     * @param EntityLoadedEvent $event entity event
     */
    public function onProductsLoaded(EntityLoadedEvent $event) : void
    {
        /** @var ProductEntity $productEntity */
        foreach ($event->getEntities() as $productEntity) {
            if (!$productEntity->hasExtension('activeInLengow')) {
                $lengowProductCriteria = new Criteria();
                $lengowProductCriteria->addFilter(
                    new EqualsFilter('productId', $productEntity->getId())
                );
                $result = $this->lengowProductRepository->search($lengowProductCriteria, Context::createDefaultContext());
                $value = (array) $result->getEntities()->getElements();
                if ($value) {
                    $productEntity->addExtension('activeInLengow',  new ProductExtensionStructure(false, $value));
                } else {
                    $productEntity->addExtension('activeInLengow',  new ProductExtensionStructure());
                }
            }
        }
    }

    /**
     * @param ProductLoaderCriteriaEvent $event criteria event
     */
    public function onProductCriteriaLoaded(ProductLoaderCriteriaEvent $event): void
    {
        $event->getCriteria()->addAssociation('active_in_lengow');
    }
}
