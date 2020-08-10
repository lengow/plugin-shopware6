<?php declare(strict_types=1);

namespace Lengow\Connector\Entity\Lengow\OrderLine;

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
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToOneAssociationField;
// Model Return Type
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
// OneToOne association class
use Shopware\Core\Checkout\Order\OrderDefinition as ShopwareOrderDefinition;
use Shopware\Core\Content\Product\ProductDefinition as ShopwareProductDefinition;
// Entity class
use Lengow\Connector\Entity\Lengow\OrderLine\OrderLineEntity as LengowOrderLineEntity;

/**
 * Class OrderLineDefinition
 * @package Lengow\Connector\Entity\Lengow\OrderLine
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
        return LengowOrderLineEntity::class;
    }

    /**
     * @return FieldCollection
     */
    protected function defineFields(): FieldCollection
    {
        return new FieldCollection(
            [
                (new IdField('id', 'id'))->addFlags(new Required(), new PrimaryKey()),
                (new OneToOneAssociationField('order', 'id', 'order_id', ShopwareOrderDefinition::class))->addFlags(
                    new Required(),
                    new setNullOnDelete()
                ),
                (new OneToOneAssociationField(
                    'product',
                    'product_id',
                    'id',
                    ShopwareProductDefinition::class
                ))->addFlags(
                    new Required(),
                    new SetNullOnDelete()
                ),
                (new StringField('order_line_id', 'orderLineId'))->addFlags(new Required()),
                (new DateField('created_at', 'createdAt')),
                (new DateField('updated_at', 'updatedAt')),
            ]
        );
    }
}
