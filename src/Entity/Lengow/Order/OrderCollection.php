<?php declare(strict_types=1);

namespace Lengow\Connector\Entity\Lengow\Order;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
// Entity class
use Lengow\Connector\Entity\Lengow\Order\OrderEntity as LengowOrderEntity;

class OrderCollection extends EntityCollection
{
    /**
     * @method void              add(LengowOrderEntity $entity)
     * @method void              set(string $key, LengowOrderEntity $entity)
     * @method LengowOrderEntity[]     getIterator()
     * @method LengowOrderEntity[]     getElements()
     * @method LengowOrderEntity|null  get(string $key)
     * @method LengowOrderEntity|null  first()
     * @method LengowOrderEntity|null  last()
     */
    protected function getExpectedClass(): LengowOrderEntity
    {
        return LengowOrderEntity::class;
    }
}
