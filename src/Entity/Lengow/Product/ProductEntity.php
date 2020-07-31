<?php declare(strict_types=1);

namespace Lengow\Connector\Entity\Lengow\Product;

use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityIdTrait;
// OneToOne association class
use Shopware\Core\Content\Product\ProductEntity as ShopwareProductEntity;
use Shopware\Core\System\SalesChannel\SalesChannelEntity as ShopwareSalesChannelEntity;

/**
 * Class ProductEntity
 * @package Lengow\Connector\Core\Content\Connector\Entity
 */
class ProductEntity extends Entity
{
    /**
     * @var ShopwareProductEntity|null
     */
    protected $product;

    /**
     * @var ShopwareSalesChannelEntity|null
     */
    protected $salesChannel;

    /**
     * @var \DateTime
     */
    protected $createdAt;

    /**
     * @return ShopwareProductEntity|null
     */
    public function getProduct(): ?ShopwareProductEntity
    {
        return $this->product;
    }

    /**
     * @param ShopwareProductEntity|null $product
     */
    public function setProduct(?ShopwareProductEntity $product): void
    {
        $this->product = $product;
    }

    /**
     * @return ShopwareSalesChannelEntity|null
     */
    public function getSalesChannel(): ?ShopwareSalesChannelEntity
    {
        return $this->salesChannel;
    }

    /**
     * @param ShopwareSalesChannelEntity|null $salesChannel
     */
    public function setSalesChannel(?ShopwareSalesChannelEntity $salesChannel): void
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
