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
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ReferenceVersionField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
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

    /* Order line fields */
    public const FIELD_ID = 'id';
    public const FIELD_ORDER_ID = 'orderId';
    public const FIELD_PRODUCT_ID = 'productId';
    public const FIELD_ORDER_LINE_ID = 'orderLineId';

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
                (new IdField('id', self::FIELD_ID))->addFlags(new Required(), new PrimaryKey()),
                (new FkField('order_id', self::FIELD_ORDER_ID, ShopwareOrderDefinition::class))->addFlags(new Required()),
                (new ReferenceVersionField(ShopwareOrderDefinition::class, 'order_version_id'))->addFlags(new Required()),
                (new ManyToOneAssociationField('order', 'order_id', ShopwareOrderDefinition::class, 'id'))
                    ->addFlags(new setNullOnDelete()),
                (new FkField('product_id', self::FIELD_PRODUCT_ID, ShopwareProductDefinition::class))->addFlags(new Required()),
                (new ReferenceVersionField(ShopwareProductDefinition::class, 'product_version_id'))->addFlags(new Required()),
                (new ManyToOneAssociationField('product', 'product_id', ShopwareProductDefinition::class, 'id'))
                    ->addFlags(new SetNullOnDelete()),
                (new StringField('order_line_id', self::FIELD_ORDER_LINE_ID))->addFlags(new Required()),
            ]
        );
    }
}
