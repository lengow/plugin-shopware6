<?php declare(strict_types=1);

namespace Lengow\Connector\EntityExtension\Extension;

use Lengow\Connector\Entity\Lengow\Action\ActionDefinition as LengowActionDefinition;
use Lengow\Connector\Entity\Lengow\Order\OrderDefinition as LengowOrderDefinition;
use Lengow\Connector\Entity\Lengow\OrderLine\OrderLineDefinition as LengowOrderLineDefinition;
use Shopware\Core\Checkout\Order\OrderDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityExtension;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToManyAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;

class OrderExtension extends EntityExtension
{
    public function extendFields(FieldCollection $collection): void
    {
        $collection->add(new OneToManyAssociationField('lengowActions', LengowActionDefinition::class, 'order_id'));
        $collection->add(new OneToManyAssociationField('lengowOrders', LengowOrderDefinition::class, 'order_id'));
        $collection->add(new OneToManyAssociationField('lengowOrderLines', LengowOrderLineDefinition::class, 'order_id'));
    }

    public function getDefinitionClass(): string
    {
        return OrderDefinition::class;
    }

    public function getEntityName(): string
    {
        return 'order';
    }
}
