<?php declare(strict_types=1);

namespace Lengow\Connector\Core\Content\Connector\Collection;

// Entity class
use Lengow\Connector\Core\Content\Connector\Entity\SettingsEntity;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

class SettingsCollection extends EntityCollection
{
    /**
     * @method void                add(SettingsEntity $entity)
     * @method void                set(string $key, SettingsEntity $entity)
     * @method SettingsEntity[]    getIterator()
     * @method SettingsEntity[]    getElements()
     * @method SettingsEntity|null get(string $key)
     * @method SettingsEntity|null first()
     * @method SettingsEntity|null last()
     */
    protected function getExpectedClass(): SettingsEntity
    {
        return SettingsEntity::class;
    }
}
