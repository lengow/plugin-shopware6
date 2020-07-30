<?php declare(strict_types=1);

namespace Lengow\Connector\Core\Content\Connector\EntityDefinition;

// Definition base class
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
// Field flags
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\SetNullOnDelete;
// Field types
use Shopware\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\DateField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
// Model Return Type
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
// Foreign key class
use Shopware\Core\System\SalesChannel\SalesChannelDefinition as ShopwareSalesChannelDefinition;
// Entity class
use Lengow\Connector\Core\Content\Connector\Entity\SettingsEntity;

/**
 * Class SettingsDefinition
 * @package Lengow\Connector\Core\Content\Connector\EntityDefinition
 */
class SettingsDefinition extends EntityDefinition
{

    public const ENTITY_NAME = 'lengow_settings';

    /**
     * @return string
     */
    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    /**
     * @return string
     */
    public function getEntityClass(): string
    {
        return SettingsEntity::class;
    }

    /**
     * @return FieldCollection
     */
    protected function defineFields(): FieldCollection
    {
        return new FieldCollection(
            [
                (new IdField('id', 'id'))->addFlags(new Required(), new PrimaryKey()),
                (new FkField(
                    'sales_channel_id', 'salesChannelId', ShopwareSalesChannelDefinition::class
                ))->addFlags(new Required(), new PrimaryKey(), new SetNullOnDelete()),
                (new StringField('name', 'name'))->addFlags(new Required()),
                (new StringField('value', 'value')),
                (new DateField('created_at', 'createdAt'))->addFlags(new Required()),
                (new DateField('updated_at', 'updatedAt'))->addFlags(new Required()),
            ]
        );
    }
}
