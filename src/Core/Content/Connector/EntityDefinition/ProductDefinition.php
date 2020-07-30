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
use Shopware\Core\Framework\DataAbstractionLayer\Field\IntField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\DateField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
// Model Return Type
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
// Foreign key class
use Shopware\Core\Content\Product\ProductDefinition as ShopwareProductDefinition;
use Shopware\Core\System\SalesChannel\SalesChannelDefinition as ShopwareSalesChannelDefinition;
// Entity class
use Lengow\Connector\Core\Content\Connector\Entity\ProductEntity;

/**
 * Class ProductDefinition
 * @package Lengow\Connector\Core\Content\Connector\EntityDefinition
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
        return ProductEntity::class;
    }

    /**
     * @return FieldCollection
     */
    public function defineFields(): FieldCollection
    {
        return new FieldCollection(
            [
                (new FkField('product_id', 'productId', ShopwareProductDefinition::class))->addFlags(
                    new Required(),
                    new PrimaryKey(),
                    new SetNullOnDelete()
                ),
                (new FkField(
                    'sales_channel_id', 'salesChannelId', ShopwareSalesChannelDefinition::class
                ))->addFlags(new Required(), new PrimaryKey(), new SetNullOnDelete()),
                (new DateField('created_at', 'createdAt'))->addFlags(new Required()),
            ]
        );
    }
}
