<?php declare(strict_types=1);

namespace Lengow\Connector\Entity\Lengow\OrderLine;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
// Entity class
use Lengow\Connector\Entity\Lengow\OrderLine\OrderLineEntity as LengowOrderLineEntity;

/**
 * Class OrderLineCollection
 * @package Lengow\Connector\Entity\Lengow\OrderLine
 */
class OrderLineCollection extends EntityCollection
{
    /**
     * @method void                       add(LengowOrderLineEntity $entity)
     * @method void                       set(string $key, LengowOrderLineEntity $entity)
     * @method LengowOrderLineEntity[]    getIterator()
     * @method LengowOrderLineEntity[]    getElements()
     * @method LengowOrderLineEntity|null get(string $key)
     * @method LengowOrderLineEntity|null first()
     * @method LengowOrderLineEntity|null last()
     */
    protected function getExpectedClass(): LengowOrderLineEntity
    {
        return LengowOrderLineEntity::class;
    }
}
