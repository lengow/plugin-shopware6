<?php declare(strict_types=1);

namespace Lengow\Connector\Entity\Lengow\Action;

// Definition base class
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
// Field flags
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\SetNullOnDelete;
// Field types
use Shopware\Core\Framework\DataAbstractionLayer\Field\IntField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\JsonField;
// Model Return Type
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
// Foreign key class
use Shopware\Core\Checkout\Order\OrderDefinition as ShopwareOrderDefinition;
// Entity class
use Lengow\Connector\Entity\Lengow\Action\ActionEntity as LengowActionEntity;

/**
 * Class ActionDefinition
 * @package Lengow\Connector\Entity\Lengow\Action
 */
class ActionDefinition extends EntityDefinition
{
    public const ENTITY_NAME = 'lengow_action';

    /* Action fields */
    public const FIELD_ID = 'id';
    public const FIELD_ORDER_ID = 'orderId';
    public const FIELD_ACTION_ID = 'actionId';
    public const FIELD_ORDER_LINE_SKU = 'orderLineSku';
    public const FIELD_ACTION_TYPE = 'actionType';
    public const FIELD_RETRY = 'retry';
    public const FIELD_PARAMETERS = 'parameters';
    public const FIELD_STATE = 'state';

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
    public function getEntityClass() : string
    {
        return LengowActionEntity::class;
    }

    /**
     * @return array
     */
    public function getDefaults(): array
    {
        return [
            self::FIELD_RETRY => 0,
        ];
    }

    /**
     * @return FieldCollection
     */
    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', self::FIELD_ID))->addFlags(new Required(), new PrimaryKey()),
            (new IdField('order_id', self::FIELD_ORDER_ID)),
            (new OneToOneAssociationField('order', 'order_id', 'id', ShopwareOrderDefinition::class))
                ->addFlags(new setNullOnDelete()),
            (new IntField('action_id', self::FIELD_ACTION_ID))->addFlags(new Required()),
            (new StringField('order_line_sku', self::FIELD_ORDER_LINE_SKU)),
            (new StringField('action_type', self::FIELD_ACTION_TYPE))->addFlags(new Required()),
            (new IntField('retry', self::FIELD_RETRY))->addFlags(new Required()),
            (new JsonField('parameters', self::FIELD_PARAMETERS))->addFlags(new Required()),
            (new IntField('state', self::FIELD_STATE))->addFlags(new Required()),
        ]);
    }
}
