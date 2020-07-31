<?php declare(strict_types=1);

namespace Lengow\Connector\Entity\Lengow\OrderError;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
// Entity class
use Lengow\Connector\Entity\Lengow\OrderError\OrderErrorEntity as LengowOrderErrorEntity;

class OrderErrorCollection extends EntityCollection
{
    /**
     * @method void                  add(LengowOrderErrorEntity $entity)
     * @method void                  set(string $key, LengowOrderErrorEntity $entity)
     * @method LengowOrderErrorEntity[]    getIterator()
     * @method LengowOrderErrorEntity[]    getElements()
     * @method LengowOrderErrorEntity|null get(string $key)
     * @method LengowOrderErrorEntity|null first()
     * @method LengowOrderErrorEntity|null last()
     */
    protected function getExpectedClass(): LengowOrderErrorEntity
    {
        return LengowOrderErrorEntity::class;
    }
}
