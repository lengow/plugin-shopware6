<?php declare(strict_types=1);

namespace Lengow\Connector\Subscriber;

use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityLoadedEvent;
use Shopware\Core\Content\Product\ProductEvents;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Shopware\Core\Framework\Context;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Lengow\Connector\Entity\Lengow\Product\ProductDefinition as LengowProductDefinition;
use Lengow\Connector\Entity\Lengow\Product\ProductEntity as LengowProductEntity;
use Lengow\Connector\EntityExtension\ExtensionStructure\ProductExtensionStructure;

/**
 * Class ProductExtensionSubscriber
 * @package Lengow\Connector\Subscriber
 */
class ProductExtensionSubscriber implements EventSubscriberInterface
{
    /**
     * @var string name of the product extension
     */
    private const EXTENSION_NAME = 'activeInLengow';

    /**
     * @var EntityRepository $lengowProductRepository shopware product repository
     */
    private $lengowProductRepository;

    /**
     * ProductExtensionSubscriber constructor
     *
     * @param EntityRepository $lengowProductRepository Lengow product repository
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
        $productEntities = [];
        foreach ($event->getEntities() as $productEntity) {
            // a partially loaded product asked for a defined set of fields, the extension is none of them
            if ($productEntity instanceof ProductEntity && !$productEntity->hasExtension(self::EXTENSION_NAME)) {
                $productEntities[$productEntity->getId()] = $productEntity;
            }
        }
        if (empty($productEntities)) {
            return;
        }
        $lengowProducts = $this->getLengowProductsByProductId(array_keys($productEntities));
        foreach ($productEntities as $productId => $productEntity) {
            $productEntity->addExtension(
                self::EXTENSION_NAME,
                new ProductExtensionStructure(false, $lengowProducts[$productId] ?? [])
            );
        }
    }

    /**
     * @param ProductLoaderCriteriaEvent $event criteria event
     */
    public function onProductCriteriaLoaded(ProductLoaderCriteriaEvent $event): void
    {
        $event->getCriteria()->addAssociation('active_in_lengow');
    }

    /**
     * Get the Lengow products of all given Shopware products with a single query
     *
     * @param array $productIds Shopware product ids
     *
     * @return array Lengow products grouped by Shopware product id
     */
    private function getLengowProductsByProductId(array $productIds): array
    {
        $lengowProductCriteria = new Criteria();
        $lengowProductCriteria->addFilter(
            new EqualsAnyFilter(LengowProductDefinition::FIELD_PRODUCT_ID, $productIds)
        );
        $result = $this->lengowProductRepository->search($lengowProductCriteria, Context::createDefaultContext());
        $lengowProducts = [];
        /** @var LengowProductEntity $lengowProduct */
        foreach ($result->getEntities() as $lengowProduct) {
            $lengowProducts[$lengowProduct->getProductId()][] = $lengowProduct;
        }

        return $lengowProducts;
    }
}
