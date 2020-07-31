<?php declare(strict_types=1);

namespace Lengow\Connector\Entity\Lengow\Settings;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
// Entity class
use Lengow\Connector\Entity\Lengow\Settings\SettingsEntity as LengowSettingsEntity;

class SettingsCollection extends EntityCollection
{
    /**
     * @method void                add(LengowSettingsEntity $entity)
     * @method void                set(string $key, LengowSettingsEntity $entity)
     * @method LengowSettingsEntity[]    getIterator()
     * @method LengowSettingsEntity[]    getElements()
     * @method LengowSettingsEntity|null get(string $key)
     * @method LengowSettingsEntity|null first()
     * @method LengowSettingsEntity|null last()
     */
    protected function getExpectedClass(): LengowSettingsEntity
    {
        return LengowSettingsEntity::class;
    }
}
