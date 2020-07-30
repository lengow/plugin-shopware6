<?php declare(strict_types=1);

namespace Lengow\Connector\Core\Content\Connector\Collection;

// Entity class
use Lengow\Connector\Core\Content\Connector\Entity\OrderErrorEntity;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

class OrderErrorCollection extends EntityCollection
{
    /**
     * @method void                  add(OrderErrorEntity $entity)
     * @method void                  set(string $key, OrderErrorEntity $entity)
     * @method OrderErrorEntity[]    getIterator()
     * @method OrderErrorEntity[]    getElements()
     * @method OrderErrorEntity|null get(string $key)
     * @method OrderErrorEntity|null first()
     * @method OrderErrorEntity|null last()
     */
    protected function getExpectedClass(): OrderErrorEntity
    {
        return OrderErrorEntity::class;
    }
}
