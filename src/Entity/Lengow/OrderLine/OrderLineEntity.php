<?php declare(strict_types=1);

namespace Lengow\Connector\Entity\Lengow\OrderLine;

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
     * @var \DateTime|null
     */
    protected $createdAt;

    /**
     * @var \DateTime|null
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
     * @return \DateTime|null
     */
    public function getCreatedAt(): ?\DateTime
    {
        return $this->createdAt;
    }

    /**
     * @param \DateTime|null $createdAt
     */
    public function setCreatedAt(?\DateTime $createdAt): void
    {
        $this->createdAt = $createdAt;
    }

    /**
     * @return \DateTime|null
     */
    public function getUpdatedAt(): ?\DateTime
    {
        return $this->updatedAt;
    }

    /**
     * @param \DateTime|null $updatedAt
     */
    public function setUpdatedAt(?\DateTime $updatedAt): void
    {
        $this->updatedAt = $updatedAt;
    }
}
