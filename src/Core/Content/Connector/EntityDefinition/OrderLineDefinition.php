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
use Shopware\Core\Checkout\Order\OrderDefinition as ShopwareOrderDefinition;
use Shopware\Core\Content\Product\ProductDefinition as ShopwareProductDefinition;
// Entity class
use Lengow\Connector\Core\Content\Connector\Entity\OrderLineEntity;

/**
 * Class OrderLineDefinition
 * @package Lengow\Connector\Core\Content\Connector\EntityDefinition
 */
class OrderLineDefinition extends EntityDefinition
{
    public const ENTITY_NAME = 'lengow_order_line';

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
        return OrderLineEntity::class;
    }

    /**
     * @return FieldCollection
     */
    protected function defineFields(): FieldCollection
    {
        return new FieldCollection(
            [
                (new IdField('id', 'id'))->addFlags(new Required(), new PrimaryKey()),
                (new FkField('order_id', 'orderId', ShopwareOrderDefinition::class))->addFlags(
                    new Required(),
                    new PrimaryKey(),
                    new SetNullOnDelete()
                ),
                (new FkField('product_id', 'productId', ShopwareProductDefinition::class))->addFlags(
                    new Required(),
                    new PrimaryKey(),
                    new SetNullOnDelete()
                ),
                (new StringField('order_line_id', 'orderLineId'))->addFlags(new Required()),
                (new DateField('created_at', 'createdAt')),
                (new DateField('updated_at', 'updatedAt')),
            ]
        );
    }
}
