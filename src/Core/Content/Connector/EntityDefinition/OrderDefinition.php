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
use Shopware\Core\Framework\DataAbstractionLayer\Field\FloatField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IntField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\DateField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
// Model Return Type
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
// Foreign key class
use Shopware\Core\Checkout\Order\OrderDefinition as ShopwareOrderDefinition;
use Shopware\Core\System\SalesChannel\SalesChannelDefinition as ShopwareSalesChannelDefinition;
// Entity class
use Lengow\Connector\Core\Content\Connector\Entity\OrderEntity;

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
        return OrderEntity::class;
    }

    /**
     * @return FieldCollection
     */
    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField        ('id',                      'id'))->addFlags(new Required(), new PrimaryKey()),
            (new FkField        ('order_id',                'orderId', ShopwareOrderDefinition::class))->addFlags(new PrimaryKey(), new SetNullOnDelete()),
            (new IntField       ('order_sku',               'orderSku')),
            (new FkField        ('sales_channel_id',        'salesChannelId', ShopwareSalesChannelDefinition::class))->addFlags(new Required(), new PrimaryKey(), new SetNullOnDelete()),
            (new IntField       ('delivery_address_id',     'deliveryAddressId')),
            (new StringField    ('delivery_country_iso',    'deliveryCountryIso')),
            (new StringField    ('marketplace_sku',         'marketplaceSku')),
            (new StringField    ('marketplace_name',        'marketplaceName')),
            (new StringField    ('marketplace_label',       'marketplaceLabel')),
            (new StringField    ('order_lengow_state',      'orderLengowState')),
            (new IntField       ('order_process_state',     'orderProcessState'))->addFlags(new Required()),
            (new DateField      ('order_date',              'orderDate')),
            (new IntField       ('order_item',              'orderItem')),
            (new StringField    ('order_types',             'orderTypes')),
            (new StringField    ('currency',                'currency')),
            (new FloatField     ('total_paid',              'totalPaid')),
            (new FloatField     ('commission',              'commission')),
            (new StringField    ('customer_name',           'customerName')),
            (new StringField    ('customer_email',          'customerEmail')),
            (new StringField    ('carrier',                 'carrier')),
            (new StringField    ('carrier_method',          'carrierMethod')),
            (new StringField    ('carrier_tracking',        'carrierTracking')),
            (new StringField    ('carrier_id_relay',        'carrierIdRelay')),
            (new IntField       ('sent_marketplace',        'sentMarketplace'))->addFlags(new Required()),
            (new IntField       ('is_in_error',             'isInError'))->addFlags(new Required()),
            (new IntField       ('is_reimported',           'isReimported')),
            (new StringField    ('message',                 'message')),
            (new DateField      ('created_at',              'createdAt')),
            (new DateField      ('updated_at',              'updatedAt')),
            (new DateField      ('imported_at',             'importedAt')),
            (new StringField    ('extra',                   'extra'))
        ]);
    }
}
