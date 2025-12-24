<?php declare(strict_types=1);

namespace Lengow\Connector\Entity\Lengow\Product;

use DateTimeInterface;
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
     * @var string
     */
    protected $productId;

    /**
     * @var ShopwareSalesChannelEntity
     */
    protected $salesChannel;

    /**
     * @var \DateTimeInterface|null
     */
    protected ?\DateTimeInterface $createdAt = null;

    /**
     * @return string
     */
    public function getProductId(): string
    {
        return $this->productId;
    }

    /**
     * @param string $productId
     */
    public function setProductId(string $productId): void
    {
        $this->productId = $productId;
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
     * @return \DateTimeInterface|null
     */
    public function getCreatedAt(): ?DateTimeInterface
    {
        return $this->createdAt;
    }

    /**
     * @param \DateTimeInterface|null $createdAt
     */
    public function setCreatedAt(?DateTimeInterface $createdAt): void
    {
        $this->createdAt = $createdAt;
    }

}
