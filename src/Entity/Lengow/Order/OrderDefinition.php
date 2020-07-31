<?php declare(strict_types=1);

namespace Lengow\Connector\Entity\Lengow\Order;

// Definition base class
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
// Field flags
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\SetNullOnDelete;
// Field types
use Shopware\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FloatField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IntField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\DateField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\BoolField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\JsonField;

// Model Return Type
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
// OneToOne association class
use Shopware\Core\Checkout\Order\OrderDefinition as ShopwareOrderDefinition;
use Shopware\Core\System\SalesChannel\SalesChannelDefinition as ShopwareSalesChannelDefinition;
// Entity class
use Lengow\Connector\Entity\Lengow\Order\OrderEntity as LengowOrderEntity;

/**
 * Class OrderDefinition
 * @package Lengow\Connector\Core\Content\Connector\EntityDefinition
 */
class OrderDefinition extends EntityDefinition
{
    public const ENTITY_NAME = 'lengow_order';

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
        return LengowOrderEntity::class;
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
                    new setNullOnDelete()
                ),
                (new IntField ('order_sku', 'orderSku')),
                (new OneToOneAssociationField(
                    'salesChannel',
                    'id',
                    'sales_channel_id',
                    ShopwareSalesChannelDefinition::class
                ))->addFlags(
                    new Required(),
                    new setNullOnDelete()
                ),
                (new IntField('delivery_address_id', 'deliveryAddressId')),
                (new StringField('delivery_country_iso', 'deliveryCountryIso')),
                (new StringField('marketplace_sku', 'marketplaceSku')),
                (new StringField('marketplace_name', 'marketplaceName')),
                (new StringField('marketplace_label', 'marketplaceLabel')),
                (new StringField('order_lengow_state', 'orderLengowState')),
                (new IntField('order_process_state', 'orderProcessState'))->addFlags(new Required()),
                (new DateField('order_date', 'orderDate')),
                (new IntField('order_item', 'orderItem')),
                (new JsonField('order_types', 'orderTypes')),
                (new StringField('currency', 'currency')),
                (new FloatField('total_paid', 'totalPaid')),
                (new FloatField('commission', 'commission')),
                (new StringField('customer_name', 'customerName')),
                (new StringField('customer_email', 'customerEmail')),
                (new StringField('carrier', 'carrier')),
                (new StringField('carrier_method', 'carrierMethod')),
                (new StringField('carrier_tracking', 'carrierTracking')),
                (new StringField('carrier_id_relay', 'carrierIdRelay')),
                (new BoolField('sent_marketplace', 'sentMarketplace'))->addFlags(new Required()),
                (new BoolField('is_in_error', 'isInError'))->addFlags(new Required()),
                (new BoolField('is_reimported', 'isReimported')),
                (new StringField('message', 'message')),
                (new DateField('created_at', 'createdAt')),
                (new DateField('updated_at', 'updatedAt')),
                (new DateField('imported_at', 'importedAt')),
                (new JsonField('extra', 'extra')),
            ]
        );
    }
}
