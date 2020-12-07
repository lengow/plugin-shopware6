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
            'isFinished' => false,
            'mail' => false,
        ];
    }

    /**
     * @return FieldCollection
     */
    protected function defineFields(): FieldCollection
    {
        return new FieldCollection(
            [
                (new IdField('id', 'id'))->addFlags(new Required(), new PrimaryKey()),
                (new IdField('lengow_order_id', 'lengowOrderId')),
                (new OneToOneAssociationField('order', 'lengow_order_id', 'id', LengowOrderDefinition::class))
                    ->addFlags(new setNullOnDelete()),
                (new StringField('message', 'message')),
                (new IntField('type', 'type'))->addFlags(new Required()),
                (new BoolField('is_finished', 'isFinished'))->addFlags(new Required()),
                (new BoolField('mail', 'mail'))->addFlags(new Required()),
            ]
        );
    }
}
