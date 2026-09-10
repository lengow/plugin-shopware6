<?php declare(strict_types=1);

namespace Lengow\Connector\EntityExtension\Extension;

use Lengow\Connector\Entity\Lengow\Order\OrderDefinition as LengowOrderDefinition;
use Lengow\Connector\Entity\Lengow\Product\ProductDefinition as LengowProductDefinition;
use Lengow\Connector\Entity\Lengow\Settings\SettingsDefinition as LengowSettingsDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityExtension;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToManyAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopware\Core\System\SalesChannel\SalesChannelDefinition;

class SalesChannelExtension extends EntityExtension
{
    public function extendFields(FieldCollection $collection): void
    {
        $collection->add(new OneToManyAssociationField('lengowOrders', LengowOrderDefinition::class, 'sales_channel_id'));
        $collection->add(new OneToManyAssociationField('lengowProducts', LengowProductDefinition::class, 'sales_channel_id'));
        $collection->add(new OneToManyAssociationField('lengowSettings', LengowSettingsDefinition::class, 'sales_channel_id'));
    }

    public function getDefinitionClass(): string
    {
        return SalesChannelDefinition::class;
    }

    public function getEntityName(): string
    {
        return 'sales_channel';
    }
}
