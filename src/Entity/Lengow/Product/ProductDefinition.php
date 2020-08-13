<?php declare(strict_types=1);

namespace Lengow\Connector\Entity\Lengow\Product;

// Definition base class
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
// Field flags
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\SetNullOnDelete;
// Field types
use Shopware\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IntField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\DateField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToOneAssociationField;
// Model Return Type
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
// OneToOne association class
use Shopware\Core\Content\Product\ProductDefinition as ShopwareProductDefinition;
use Shopware\Core\System\SalesChannel\SalesChannelDefinition as ShopwareSalesChannelDefinition;
// Entity class
use Lengow\Connector\Entity\Lengow\Product\ProductEntity as LengowProductEntity;

/**
 * Class ProductDefinition
 * @package Lengow\Connector\Entity\Lengow\Product
 */
class ProductDefinition extends EntityDefinition
{
    public const ENTITY_NAME = 'lengow_product';

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
        return LengowProductEntity::class;
    }

    /**
     * @return FieldCollection
     */
    public function defineFields(): FieldCollection
    {
        return new FieldCollection(
            [
                (new OneToOneAssociationField(
                    'product',
                    'product_id',
                    'id',
                    ShopwareProductDefinition::class
                ))->addFlags(
                    new Required(),
                    new SetNullOnDelete()
                ),
                (new FkField('sales_channel_id', 'salesChannelId', ShopwareSalesChannelDefinition::class)),
                (new OneToOneAssociationField(
                    'salesChannel',
                    'sales_channel_id',
                    'id',
                    ShopwareSalesChannelDefinition::class
                ))->addFlags(
                    new Required(),
                    new setNullOnDelete()
                ),
                (new DateField('created_at', 'createdAt'))->addFlags(new Required()),
            ]
        );
    }
}
