<?php declare(strict_types=1);

namespace Lengow\Connector\Entity\Lengow\Product;

use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityIdTrait;
// OneToOne association class
use Shopware\Core\Content\Product\ProductEntity as ShopwareProductEntity;
use Shopware\Core\System\SalesChannel\SalesChannelEntity as ShopwareSalesChannelEntity;

/**
 * Class ProductEntity
 * @package Lengow\Connector\Entity\Lengow\Product
 */
class ProductEntity extends Entity
{
    /**
     * @var ShopwareProductEntity
     */
    protected $product;

    /**
     * @var ShopwareSalesChannelEntity
     */
    protected $salesChannel;

    /**
     * @var \DateTime
     */
    protected $createdAt;

    /**
     * @return ShopwareProductEntity
     */
    public function getProduct(): ShopwareProductEntity
    {
        return $this->product;
    }

    /**
     * @param ShopwareProductEntity $product
     */
    public function setProduct(ShopwareProductEntity $product): void
    {
        $this->product = $product;
    }

    /**
     * @return ShopwareSalesChannelEntity
     */
    public function getSalesChannel(): ShopwareSalesChannelEntity
    {
        return $this->salesChannel;
    }

    /**
     * @param ShopwareSalesChannelEntity $salesChannel
     */
    public function setSalesChannel(ShopwareSalesChannelEntity $salesChannel): void
    {
        $this->salesChannel = $salesChannel;
    }

    /**
     * @return \DateTime
     */
    public function getCreatedAt(): \DateTime
    {
        return $this->createdAt;
    }

    /**
     * @param \DateTime $createdAt
     */
    public function setCreatedAt(\DateTime $createdAt): void
    {
        $this->createdAt = $createdAt;
    }

}
