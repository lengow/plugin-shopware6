<?php declare(strict_types=1);

namespace Lengow\Connector\Entity\Lengow\Action;

use DateTimeInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityIdTrait;
// OneToOne association class
use Shopware\Core\Checkout\Order\OrderEntity as ShopwareOrderEntity;

/**
 * Class ActionEntity
 * @package Lengow\Connector\Entity\Lengow\Action
 */
class ActionEntity extends Entity
{
    use EntityIdTrait;

    /**
     * @var ShopwareOrderEntity
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
     * @var array
     */
    protected $parameters;

    /**
     * @var int
     */
    protected $state;

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
     * @param ShopwareOrderEntity$order
     */
    public function setOrder(ShopwareOrderEntity $order): void
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
     * @return array
     */
    public function getParameters(): ?array
    {
        return $this->parameters;
    }

    /**
     * @param array $parameters
     */
    public function setParameters(array $parameters): void
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
