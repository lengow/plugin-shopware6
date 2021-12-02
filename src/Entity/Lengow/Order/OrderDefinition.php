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
use Shopware\Core\Framework\DataAbstractionLayer\Field\DateTimeField;
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
 * @package Lengow\Connector\Entity\Lengow\Order
 */
class OrderDefinition extends EntityDefinition
{
    public const ENTITY_NAME = 'lengow_order';

    /* Order fields */
    public const FIELD_ID = 'id';
    public const FIELD_ORDER_ID = 'orderId';
    public const FIELD_ORDER_SKU = 'orderSku';
    public const FIELD_SALES_CHANNEL_ID = 'salesChannelId';
    public const FIELD_DELIVERY_ADDRESS_ID = 'deliveryAddressId';
    public const FIELD_DELIVERY_COUNTRY_ISO = 'deliveryCountryIso';
    public const FIELD_MARKETPLACE_SKU = 'marketplaceSku';
    public const FIELD_MARKETPLACE_NAME = 'marketplaceName';
    public const FIELD_MARKETPLACE_LABEL = 'marketplaceLabel';
    public const FIELD_ORDER_LENGOW_STATE = 'orderLengowState';
    public const FIELD_ORDER_PROCESS_STATE = 'orderProcessState';
    public const FIELD_ORDER_DATE = 'orderDate';
    public const FIELD_ORDER_ITEM = 'orderItem';
    public const FIELD_ORDER_TYPES = 'orderTypes';
    public const FIELD_CURRENCY = 'currency';
    public const FIELD_TOTAL_PAID = 'totalPaid';
    public const FIELD_COMMISSION = 'commission';
    public const FIELD_CUSTOMER_NAME = 'customerName';
    public const FIELD_CUSTOMER_EMAIL = 'customerEmail';
    public const FIELD_CUSTOMER_VAT_NUMBER = 'customerVatNumber';
    public const FIELD_CARRIER = 'carrier';
    public const FIELD_CARRIER_METHOD = 'carrierMethod';
    public const FIELD_CARRIER_TRACKING = 'carrierTracking';
    public const FIELD_CARRIER_RELAY_ID = 'carrierIdRelay';
    public const FIELD_SENT_MARKETPLACE = 'sentMarketplace';
    public const FIELD_IS_IN_ERROR = 'isInError';
    public const FIELD_IS_REIMPORTED = 'isReimported';
    public const FIELD_MESSAGE = 'message';
    public const FIELD_IMPORTED_AT = 'importedAt';
    public const FIELD_EXTRA = 'extra';

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
     * @return array
     */
    public function getDefaults(): array
    {
        return [
            self::FIELD_SENT_MARKETPLACE => false,
            self::FIELD_IS_IN_ERROR => true,
            self::FIELD_IS_REIMPORTED => false,
        ];
    }

    /**
     * @return FieldCollection
     */
    protected function defineFields(): FieldCollection
    {
        return new FieldCollection(
            [
                (new IdField('id', self::FIELD_ID))->addFlags(new Required(), new PrimaryKey()),
                (new IdField('order_id', self::FIELD_ORDER_ID)),
                (new OneToOneAssociationField('order', 'order_id', 'id', ShopwareOrderDefinition::class))
                    ->addFlags(new setNullOnDelete()),
                (new StringField ('order_sku', self::FIELD_ORDER_SKU)),
                (new IdField('sales_channel_id', self::FIELD_SALES_CHANNEL_ID)),
                (new OneToOneAssociationField('salesChannel', 'sales_channel_id', 'id', ShopwareSalesChannelDefinition::class))
                    ->addFlags(new setNullOnDelete()),
                (new IntField('delivery_address_id', self::FIELD_DELIVERY_ADDRESS_ID)),
                (new StringField('delivery_country_iso', self::FIELD_DELIVERY_COUNTRY_ISO)),
                (new StringField('marketplace_sku', self::FIELD_MARKETPLACE_SKU)),
                (new StringField('marketplace_name', self::FIELD_MARKETPLACE_NAME)),
                (new StringField('marketplace_label', self::FIELD_MARKETPLACE_LABEL)),
                (new StringField('order_lengow_state', self::FIELD_ORDER_LENGOW_STATE)),
                (new IntField('order_process_state', self::FIELD_ORDER_PROCESS_STATE))->addFlags(new Required()),
                (new DateTimeField('order_date', self::FIELD_ORDER_DATE)),
                (new IntField('order_item', self::FIELD_ORDER_ITEM)),
                (new JsonField('order_types', self::FIELD_ORDER_TYPES)),
                (new StringField('currency', self::FIELD_CURRENCY)),
                (new FloatField('total_paid', self::FIELD_TOTAL_PAID)),
                (new FloatField('commission', self::FIELD_COMMISSION)),
                (new StringField('customer_name', self::FIELD_CUSTOMER_NAME)),
                (new StringField('customer_email', self::FIELD_CUSTOMER_EMAIL)),
                (new StringField('customer_vat_number', self::FIELD_CUSTOMER_VAT_NUMBER)),
                (new StringField('carrier', self::FIELD_CARRIER)),
                (new StringField('carrier_method', self::FIELD_CARRIER_METHOD)),
                (new StringField('carrier_tracking', self::FIELD_CARRIER_TRACKING)),
                (new StringField('carrier_id_relay', self::FIELD_CARRIER_RELAY_ID)),
                (new BoolField('sent_marketplace', self::FIELD_SENT_MARKETPLACE))->addFlags(new Required()),
                (new BoolField('is_in_error', self::FIELD_IS_IN_ERROR))->addFlags(new Required()),
                (new BoolField('is_reimported', self::FIELD_IS_REIMPORTED)),
                (new StringField('message', self::FIELD_MESSAGE)),
                (new DateTimeField('imported_at', self::FIELD_IMPORTED_AT)),
                (new JsonField('extra', self::FIELD_EXTRA)),
            ]
        );
    }
}
