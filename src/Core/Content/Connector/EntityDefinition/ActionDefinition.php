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
use Shopware\Core\Checkout\Order\OrderDefinition as ShopwareOrderDefinition;
// Entity class
use namespace Lengow\Connector\Core\Content\Connector\Entity\ActionEntity;

/**
 * Class ActionDefinition
 * @package Lengow\Connector\Core\Content\Connector\EntityDefinition
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
     * @return ActionEntity
     */
    public function getEntityClass() : ActionEntity
    {
        return ActionEntity::class;
    }

    /**
     * @return FieldCollection
     */
    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
             (new IdField     ('id',             'id'))->addFlags(new Required(), new PrimaryKey()),
             (new FkField     ('order_id',       'orderId', ShopwareOrderDefinition::class))->addFlags(new PrimaryKey(), new SetNullOnDelete()),
             (new IntField    ('action_id',      'actionId'))->addFlags(new Required()),
             (new StringField ('order_line_sku', 'orderLineSku')),
             (new StringField ('action_type',    'actionType'))->addFlags(new Required()),
             (new IntField    ('retry',          'retry'))->addFlags(new Required()),
             (new StringField ('parameters',     'parameters'))->addFlags(new Required()),
             (new IntField    ('state',          'state'))->addFlags(new Required()),
             (new DateField   ('created_at',     'createdAt')),
             (new DateField   ('updated_at',     'updatedAt')),
        ]);
    }
}
