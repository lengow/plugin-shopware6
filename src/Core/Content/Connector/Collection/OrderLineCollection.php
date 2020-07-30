<?php declare(strict_types=1);

namespace Lengow\Connector\Core\Content\Connector\Collection;

// Entity class
use Lengow\Connector\Core\Content\Connector\Entity\OrderLineEntity;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

class OrderLineCollection extends EntityCollection
{
    /**
     * @method void                 add(OrderLineEntity $entity)
     * @method void                 set(string $key, OrderLineEntity $entity)
     * @method OrderLineEntity[]    getIterator()
     * @method OrderLineEntity[]    getElements()
     * @method OrderLineEntity|null get(string $key)
     * @method OrderLineEntity|null first()
     * @method OrderLineEntity|null last()
     */
    protected function getExpectedClass(): OrderLineEntity
    {
        return OrderLineEntity::class;
    }
}
