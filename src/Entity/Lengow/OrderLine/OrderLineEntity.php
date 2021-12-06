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
     * @var ShopwareOrderEntity
     */
    protected $order;

    /**
     * @var ShopwareProductEntity
     */
    protected $product;

    /**
     * @var string
     */
    protected $orderLineId;

    /**
     * @var DateTimeInterface|null
     */
    protected $createdAt;

    /**
     * @var DateTimeInterface|null
     */
    protected $updatedAt;

    /**
     * @return ShopwareOrderEntity
     */
    public function getOrder(): ShopwareOrderEntity
    {
        return $this->order;
    }

    /**
     * @param ShopwareOrderEntity $order
     */
    public function setOrder(ShopwareOrderEntity $order): void
    {
        $this->order = $order;
    }

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
