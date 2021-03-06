<?php declare(strict_types=1);

namespace Lengow\Connector\Entity\Lengow\Settings;

// Definition base class
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
// Field flags
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\SetNullOnDelete;
// Field types
use Shopware\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToOneAssociationField;
// Model Return Type
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
// OneToOne association class
use Shopware\Core\System\SalesChannel\SalesChannelDefinition as ShopwareSalesChannelDefinition;
// Entity class
use Lengow\Connector\Entity\Lengow\Settings\SettingsEntity as LengowSettingsEntity;

/**
 * Class SettingsDefinition
 * @package Lengow\Connector\Entity\Lengow\Settings
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
        return LengowSettingsEntity::class;
    }

    /**
     * @return array
     */
    public function getDefaults(): array
    {
        return [];
    }

    /**
     * @return FieldCollection
     */
    protected function defineFields(): FieldCollection
    {
        return new FieldCollection(
            [
                (new IdField('id', 'id'))->addFlags(new Required(), new PrimaryKey()),
                (new FkField('sales_channel_id', 'salesChannelId', ShopwareSalesChannelDefinition::class)),
                (new OneToOneAssociationField('salesChannel', 'sales_channel_id', 'id', ShopwareSalesChannelDefinition::class))
                    ->addFlags(new setNullOnDelete()),
                (new StringField('name', 'name'))->addFlags(new Required()),
                (new StringField('value', 'value', 20000)),
            ]
        );
    }
}
