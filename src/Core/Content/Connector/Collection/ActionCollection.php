<?php declare(strict_types=1);

namespace Lengow\Connector\Core\Content\Connector\Collection;

// Entity class
use Lengow\Connector\Core\Content\Connector\Entity\ActionEntity;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

class ActionCollection extends EntityCollection
{
    /**
     * @method void              add(ActionEntity $entity)
     * @method void              set(string $key, ActionEntity $entity)
     * @method ActionEntity[]    getIterator()
     * @method ActionEntity[]    getElements()
     * @method ActionEntity|null get(string $key)
     * @method ActionEntity|null first()
     * @method ActionEntity|null last()
     */
    protected function getExpectedClass(): ActionEntity
    {
        return ActionEntity::class;
    }
}
