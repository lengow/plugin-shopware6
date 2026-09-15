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
    use EntityIdTrait;

    /**
     * @var string
     */
    protected $productId;

    /**
     * @var string|null
     */
    protected $productVersionId;

    /**
     * @var ShopwareProductEntity|null
     */
    protected $product;

    /**
     * @var string
     */
    protected $salesChannelId;

    /**
     * @var ShopwareSalesChannelEntity|null
     */
    protected $salesChannel;


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
     * @return string|null
     */
    public function getProductVersionId(): ?string
    {
        return $this->productVersionId;
    }

    /**
     * @param string|null $productVersionId
     */
    public function setProductVersionId(?string $productVersionId): void
    {
        $this->productVersionId = $productVersionId;
    }

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
     * @return string
     */
    public function getSalesChannelId(): string
    {
        return $this->salesChannelId;
    }

    /**
     * @param string $salesChannelId
     */
    public function setSalesChannelId(string $salesChannelId): void
    {
        $this->salesChannelId = $salesChannelId;
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
