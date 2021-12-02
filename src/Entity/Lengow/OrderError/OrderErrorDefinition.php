<?php declare(strict_types=1);

namespace Lengow\Connector\Entity\Lengow\OrderError;

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
use Shopware\Core\Framework\DataAbstractionLayer\Field\BoolField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToOneAssociationField;
// Model Return Type
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
// OneToOne association class
use Lengow\Connector\Entity\Lengow\Order\OrderDefinition as LengowOrderDefinition;
// Entity class
use Lengow\Connector\Entity\Lengow\OrderError\OrderErrorEntity as LengowOrderErrorEntity;

/**
 * Class OrderErrorDefinition
 * @package Lengow\Connector\Entity\Lengow\OrderError
 */
class OrderErrorDefinition extends EntityDefinition
{
    public const ENTITY_NAME = 'lengow_order_error';

    /* Order error fields */
    public const FIELD_ID = 'id';
    public const FIELD_LENGOW_ORDER_ID = 'lengowOrderId';
    public const FIELD_MESSAGE = 'message';
    public const FIELD_TYPE = 'type';
    public const FIELD_IS_FINISHED = 'isFinished';
    public const FIELD_MAIL = 'mail';

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
        return LengowOrderErrorEntity::class;
    }

    /**
     * @return array
     */
    public function getDefaults(): array
    {
        return [
            self::FIELD_IS_FINISHED => false,
            self::FIELD_MAIL => false,
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
                (new IdField('lengow_order_id', self::FIELD_LENGOW_ORDER_ID)),
                (new OneToOneAssociationField('order', 'lengow_order_id', 'id', LengowOrderDefinition::class))
                    ->addFlags(new setNullOnDelete()),
                (new StringField('message', self::FIELD_MESSAGE)),
                (new IntField('type', self::FIELD_TYPE))->addFlags(new Required()),
                (new BoolField('is_finished', self::FIELD_IS_FINISHED))->addFlags(new Required()),
                (new BoolField('mail', self::FIELD_MAIL))->addFlags(new Required()),
            ]
        );
    }
}
