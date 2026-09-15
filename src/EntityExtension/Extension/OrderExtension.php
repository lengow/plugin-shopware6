<?php declare(strict_types=1);

namespace Lengow\Connector\EntityExtension\Extension;

use Lengow\Connector\Entity\Lengow\Action\ActionDefinition as LengowActionDefinition;
use Lengow\Connector\Entity\Lengow\Order\OrderDefinition as LengowOrderDefinition;
use Lengow\Connector\Entity\Lengow\OrderLine\OrderLineDefinition as LengowOrderLineDefinition;
use Shopware\Core\Checkout\Order\OrderDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityExtension;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\SetNullOnDelete;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToManyAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;

class OrderExtension extends EntityExtension
{
    public function extendFields(FieldCollection $collection): void
    {
        // Shopware resolves the delete policy from the parent side, so the
        // setNullOnDelete flags carried by the child ManyToOne fields never apply.
        // lengow_order.order_id is nullable, so the intent those flags express is
        // honoured here: the Lengow record survives a deleted Shopware order, which
        // is what keeps marketplace traceability and re-import detection working.
        $collection->add(
            (new OneToManyAssociationField('lengowOrders', LengowOrderDefinition::class, 'order_id'))
                ->addFlags(new SetNullOnDelete())
        );

        // lengow_action.order_id and lengow_order_line.order_id are Required, so
        // set-null cannot apply to them and no policy is declared yet: choosing
        // between cascading the deletion and restricting it is a data retention
        // decision, not a technical one.
        $collection->add(new OneToManyAssociationField('lengowActions', LengowActionDefinition::class, 'order_id'));
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
