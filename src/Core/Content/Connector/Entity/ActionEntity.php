<?php declare(strict_types=1);

namespace Lengow\Connector\Core\Content\Connector\Entity;

use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityIdTrait;

/**
 * Class ActionEntity
 * @package Lengow\Connector\Core\Content\Connector\Entity
 */
class ActionEntity extends Entity
{
    use EntityIdTrait;

    /**
     * @var string
     */
    protected $orderId;

    /**
     * @var int
     */
    protected $actionId;

    /**
     * @var string
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
     * @var string
     */
    protected $parameters;

    /**
     * @var int
     */
    protected $state;

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
     * @return string
     */
    public function getOrderLineSku(): string
    {
        return $this->orderLineSku;
    }

    /**
     * @param string $orderLineSku
     */
    public function setOrderLineSku(string $orderLineSku): void
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
     * @return string
     */
    public function getParameters(): string
    {
        return $this->parameters;
    }

    /**
     * @param string $parameters
     */
    public function setParameters(string $parameters): void
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
