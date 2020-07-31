<?php declare(strict_types=1);

namespace Lengow\Connector\Entity\Lengow\Action;

use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityIdTrait;
// OneToOne association class
use Shopware\Core\Checkout\Order\OrderEntity as ShopwareOrderEntity;

/**
 * Class ActionEntity
 * @package Lengow\Connector\Core\Content\Connector\Entity
 */
class ActionEntity extends Entity
{
    use EntityIdTrait;

    /**
     * @var ShopwareOrderEntity|null
     */
    protected $order;

    /**
     * @var int
     */
    protected $actionId;

    /**
     * @var string|null
     */
    protected $orderLineSku;

    /**
     * @var string
     */
    protected $actionType;

    /**
     * @var int
     */
    protected $retry;

    /**
     * @var array|null
     */
    protected $parameters;

    /**
     * @var int
     */
    protected $state;

    /**
     * @var \DateTime|null
     */
    protected $createdAt;

    /**
     * @var \DateTime|null
     */
    protected $updatedAt;

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
     * @return int
     */
    public function getActionId(): int
    {
        return $this->actionId;
    }

    /**
     * @param int $actionId
     */
    public function setActionId(int $actionId): void
    {
        $this->actionId = $actionId;
    }

    /**
     * @return string|null
     */
    public function getOrderLineSku(): ?string
    {
        return $this->orderLineSku;
    }

    /**
     * @param string|null $orderLineSku
     */
    public function setOrderLineSku(?string $orderLineSku): void
    {
        $this->orderLineSku = $orderLineSku;
    }

    /**
     * @return string
     */
    public function getActionType(): string
    {
        return $this->actionType;
    }

    /**
     * @param string $actionType
     */
    public function setActionType(string $actionType): void
    {
        $this->actionType = $actionType;
    }

    /**
     * @return int
     */
    public function getRetry(): int
    {
        return $this->retry;
    }

    /**
     * @param int $retry
     */
    public function setRetry(int $retry): void
    {
        $this->retry = $retry;
    }

    /**
     * @return array|null
     */
    public function getParameters(): ?array
    {
        return $this->parameters;
    }

    /**
     * @param array|null $parameters
     */
    public function setParameters(?array $parameters): void
    {
        $this->parameters = $parameters;
    }

    /**
     * @return int
     */
    public function getState(): int
    {
        return $this->state;
    }

    /**
     * @param int $state
     */
    public function setState(int $state): void
    {
        $this->state = $state;
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
