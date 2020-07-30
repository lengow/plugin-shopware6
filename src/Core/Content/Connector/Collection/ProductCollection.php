<?php declare(strict_types=1);

namespace Lengow\Connector\Core\Content\Connector\Collection;

// Entity class
use Lengow\Connector\Core\Content\Connector\Entity\ProductEntity;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

class ProductCollection extends EntityCollection
{
    /**
     * @method void               add(ProductEntity $entity)
     * @method void               set(string $key, ProductEntity $entity)
     * @method ProductEntity[]    getIterator()
     * @method ProductEntity[]    getElements()
     * @method ProductEntity|null get(string $key)
     * @method ProductEntity|null first()
     * @method ProductEntity|null last()
     */
    protected function getExpectedClass(): ProductEntity
    {
        return ProductEntity::class;
    }
}
