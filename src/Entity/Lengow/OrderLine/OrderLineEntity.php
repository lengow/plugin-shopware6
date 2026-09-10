<?php declare(strict_types=1);

namespace Lengow\Connector\Entity\Lengow\OrderLine;

use DateTimeInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityIdTrait;
// OneToOne association class
use Shopware\Core\Checkout\Order\OrderEntity as ShopwareOrderEntity;
use Shopware\Core\Content\Product\ProductEntity as ShopwareProductEntity;

/**
 * Class OrderLineEntity
 * @package Lengow\Connector\Entity\Lengow\OrderLine
 */
class OrderLineEntity extends Entity
{
    use EntityIdTrait;

    /**
     * @var string|null
     */
    protected $orderId;

    /**
     * @var string|null
     */
    protected $orderVersionId;

    /**
     * @var string|null
     */
    protected $productId;

    /**
     * @var string|null
     */
    protected $productVersionId;

    /**
     * @var ShopwareOrderEntity|null
     */
    protected $order;

    /**
     * @var ShopwareProductEntity|null
     */
    protected $product;

    /**
     * @var string
     */
    protected $orderLineId;



    /**
     * @return string|null
     */
    public function getOrderId(): ?string
    {
        return $this->orderId;
    }

    /**
     * @param string|null $orderId
     */
    public function setOrderId(?string $orderId): void
    {
        $this->orderId = $orderId;
    }

    /**
     * @return string|null
     */
    public function getOrderVersionId(): ?string
    {
        return $this->orderVersionId;
    }

    /**
     * @param string|null $orderVersionId
     */
    public function setOrderVersionId(?string $orderVersionId): void
    {
        $this->orderVersionId = $orderVersionId;
    }

    /**
     * @return string|null
     */
    public function getProductId(): ?string
    {
        return $this->productId;
    }

    /**
     * @param string|null $productId
     */
    public function setProductId(?string $productId): void
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
     * @return ShopwareOrderEntity|null
     */
    public function getOrder(): ?ShopwareOrderEntity
    {
        return $this->order;
    }

    /**
     * @param ShopwareOrderEntity|null $order
     */
    public function setOrder(?ShopwareOrderEntity $order): void
    {
        $this->order = $order;
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
    public function getOrderLineId(): string
    {
        return $this->orderLineId;
    }

    /**
     * @param string $orderLineId
     */
    public function setOrderLineId(string $orderLineId): void
    {
        $this->orderLineId = $orderLineId;
    }

    /**
     * @return DateTimeInterface|null
     */
    public function getCreatedAt(): ?DateTimeInterface
    {
        return $this->createdAt;
    }

    /**
     * @param DateTimeInterface|null $createdAt
     */
    public function setCreatedAt(?DateTimeInterface $createdAt): void
    {
        $this->createdAt = $createdAt;
    }

    /**
     * @return DateTimeInterface|null
     */
    public function getUpdatedAt(): ?DateTimeInterface
    {
        return $this->updatedAt;
    }

    /**
     * @param DateTimeInterface|null $updatedAt
     */
    public function setUpdatedAt(?DateTimeInterface $updatedAt): void
    {
        $this->updatedAt = $updatedAt;
    }
}
