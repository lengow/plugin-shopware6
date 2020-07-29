<?php declare(strict_types=1);

namespace Lengow\Connector\Core\Content\Connector\Entity;

use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityIdTrait;

/**
 * Class OrderLineEntity
 * @package Lengow\Connector\Core\Content\Connector\Entity
 */
class OrderLineEntity extends Entity
{
    use EntityIdTrait;

    /**
     * @var string
     */
    protected $orderId;

    /**
     * @var string
     */
    protected $productId;

    /**
     * @var string
     */
    protected $orderLineId;

    /**
     * @var \DateTime
     */
    protected $createdAt;

    /**
     * @var \DateTime
     */
    protected $updatedAt;

    /**
     * @return string
     */
    public function getOrderId(): string
    {
        return $this->orderId;
    }

    /**
     * @param string $orderId
     */
    public function setOrderId(string $orderId): void
    {
        $this->orderId = $orderId;
    }

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

    /**
     * @return \DateTime
     */
    public function getUpdatedAt(): \DateTime
    {
        return $this->updatedAt;
    }

    /**
     * @param \DateTime $updatedAt
     */
    public function setUpdatedAt(\DateTime $updatedAt): void
    {
        $this->updatedAt = $updatedAt;
    }
}
