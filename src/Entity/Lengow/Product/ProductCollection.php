<?php declare(strict_types=1);

namespace Lengow\Connector\Entity\Lengow\Product;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
// Entity class
use Lengow\Connector\Entity\Lengow\Product\ProductEntity as LengowProductEntity;

class ProductCollection extends EntityCollection
{
    /**
     * @method void               add(LengowProductEntity $entity)
     * @method void               set(string $key, LengowProductEntity $entity)
     * @method LengowProductEntity[]    getIterator()
     * @method LengowProductEntity[]    getElements()
     * @method LengowProductEntity|null get(string $key)
     * @method LengowProductEntity|null first()
     * @method LengowProductEntity|null last()
     */
    protected function getExpectedClass(): LengowProductEntity
    {
        return LengowProductEntity::class;
    }
}
