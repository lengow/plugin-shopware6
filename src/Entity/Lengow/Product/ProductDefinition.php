<?php declare(strict_types=1);

namespace Lengow\Connector\Entity\Lengow\Product;

// Definition base class
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
// Field flags
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
// Field types
use Shopware\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
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

    /* Setting fields */
    public const FIELD_ID = 'id';
    public const FIELD_PRODUCT_ID = 'productId';
    public const FIELD_SALES_CHANNEL_ID = 'salesChannelId';

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
                (new IdField('id', self::FIELD_ID))->addFlags(new Required(), new PrimaryKey()),
                (new FkField('product_id', self::FIELD_PRODUCT_ID, ShopwareProductDefinition::class)),
                (new FkField('sales_channel_id', self::FIELD_SALES_CHANNEL_ID, ShopwareSalesChannelDefinition::class)),
            ]
        );
    }
}
