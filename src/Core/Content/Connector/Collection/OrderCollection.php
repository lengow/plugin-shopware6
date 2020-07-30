<?php declare(strict_types=1);

namespace Lengow\Connector\Core\Content\Connector\Collection;

// Entity class
use Lengow\Connector\Core\Content\Connector\Entity\OrderEntity;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

class OrderCollection extends EntityCollection
{
    /**
     * @method void              add(OrderEntity $entity)
     * @method void              set(string $key, OrderEntity $entity)
     * @method OrderEntity[]     getIterator()
     * @method OrderEntity[]     getElements()
     * @method OrderEntity|null  get(string $key)
     * @method OrderEntity|null  first()
     * @method OrderEntity|null  last()
     */
    protected function getExpectedClass(): OrderEntity
    {
        return OrderEntity::class;
    }
}
