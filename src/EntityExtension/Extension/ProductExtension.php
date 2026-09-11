<?php declare(strict_types=1);

namespace Lengow\Connector\EntityExtension\Extension;

use Lengow\Connector\Entity\Lengow\OrderLine\OrderLineDefinition as LengowOrderLineDefinition;
use Lengow\Connector\Entity\Lengow\Product\ProductDefinition as LengowProductDefinition;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityExtension;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Runtime;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ObjectField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToManyAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;

/**
 * Class ProductExtension
 * @package Lengow\Connector\EntityExtension\Extension
 */
class ProductExtension extends EntityExtension
{
    /**
     * @param FieldCollection $collection
     */
    public function extendFields(FieldCollection $collection): void
    {
        $collection->add(
            (new ObjectField('active_in_lengow', 'activeInLengow'))->addFlags(new Runtime())
        );
        $collection->add(new OneToManyAssociationField('lengowOrderLines', LengowOrderLineDefinition::class, 'product_id'));
        $collection->add(new OneToManyAssociationField('lengowProducts', LengowProductDefinition::class, 'product_id'));
    }

    /**
     * @return string
     */
    public function getDefinitionClass(): string
    {
        return ProductDefinition::class;
    }

    /**
     * @return string
     */
    public function getEntityName(): string
    {
        return 'product';
    }
}