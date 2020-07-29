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
use Lengow\Connector\Core\Content\Connector\OrderDefinition as LengowOrderDefinition;
// Entity class
use Lengow\Connector\Core\Content\Connector\Entity\OrderErrorEntity;

/**
 * Class OrderErrorDefinition
 * @package Lengow\Connector\Core\Content\Connector\EntityDefinition
 */
class OrderErrorDefinition extends EntityDefinition
{
    public const ENTITY_NAME = 'lengow_order_error';

    /**
     * @return string
     */
    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    /**
     * @return OrderErrorEntity
     */
    public function getEntityClass() : OrderErrorEntity
    {
        return OrderErrorEntity::class;
    }

    /**
     * @return FieldCollection
     */
    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField    ('id',              'id'))->addFlags(new Required(), new PrimaryKey()),
            (new FkField    ('lengow_order_id', 'lengowOrderId', LengowOrderDefinition::class))->addFlags(new PrimaryKey(), new SetNullOnDelete()),
            (new StringField('message',         'message')),
            (new IntField   ('type',            'type'))->addFlags(new Required()),
            (new IntField   ('is_finished',     'isFinished'))->addFlags(new Required()),
            (new IntField   ('mail',            'mail'))->addFlags(new Required()),
            (new DateField  ('createdAt',       'createdAt')),
            (new DateField  ('updatedAt',       'updatedAt')),
        ]);
    }
}
