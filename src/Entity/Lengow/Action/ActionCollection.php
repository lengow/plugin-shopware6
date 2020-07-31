<?php declare(strict_types=1);

namespace Lengow\Connector\Entity\Lengow\Action;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
// Entity class
use Lengow\Connector\Entity\Lengow\Action\ActionEntity as LengowActionEntity;

/**
 * Class ActionCollection
 * @package Lengow\Connector\Entity\Lengow\Action
 */
class ActionCollection extends EntityCollection
{
    /**
     * @method void                    add(LengowActionEntity $entity)
     * @method void                    set(string $key, LengowActionEntity $entity)
     * @method LengowActionEntity[]    getIterator()
     * @method LengowActionEntity[]    getElements()
     * @method LengowActionEntity|null get(string $key)
     * @method LengowActionEntity|null first()
     * @method LengowActionEntity|null last()
     */
    protected function getExpectedClass(): LengowActionEntity
    {
        return LengowActionEntity::class;
    }
}
