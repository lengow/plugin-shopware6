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
            'retry' => 0,
        ];
    }

    /**
     * @return FieldCollection
     */
    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new Required(), new PrimaryKey()),
            (new IdField('order_id', 'orderId')),
            (new OneToOneAssociationField('order', 'order_id', 'id', ShopwareOrderDefinition::class))
                ->addFlags(new setNullOnDelete()),
            (new IntField('action_id', 'actionId'))->addFlags(new Required()),
            (new StringField('order_line_sku', 'orderLineSku')),
            (new StringField('action_type', 'actionType'))->addFlags(new Required()),
            (new IntField('retry', 'retry'))->addFlags(new Required()),
            (new JsonField('parameters', 'parameters'))->addFlags(new Required()),
            (new IntField('state', 'state'))->addFlags(new Required()),
        ]);
    }
}
