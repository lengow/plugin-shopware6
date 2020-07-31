<?php declare(strict_types=1);

namespace Lengow\Connector\Entity\Lengow\OrderError;

use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityIdTrait;
// OneToOne association class
use Lengow\Connector\Entity\Lengow\Order\OrderEntity as LengowOrderEntity;

/**
 * Class OrderErrorEntity
 * @package Lengow\Connector\Core\Content\Connector\Entity
 */
class OrderErrorEntity extends Entity
{
    use EntityIdTrait;

    /**
     * @var LengowOrderEntity|null
     */
    protected $order;

    /**
     * @var string|null
     */
    protected $message;

    /**
     * @var int
     */
    protected $type;

    /**
     * @var bool
     */
    protected $isFinished;

    /**
     * @var bool
     */
    protected $mail;

    /**
     * @var \DateTime|null
     */
    protected $createdAt;

    /**
     * @var \DateTime|null
     */
    protected $updatedAt;

    /**
     * @return LengowOrderEntity|null
     */
    public function getOrder(): ?LengowOrderEntity
    {
        return $this->order;
    }

    /**
     * @param LengowOrderEntity|null $order
     */
    public function setOrder(?LengowOrderEntity $order): void
    {
        $this->order = $order;
    }

    /**
     * @return string|null
     */
    public function getMessage(): ?string
    {
        return $this->message;
    }

    /**
     * @param string|null $message
     */
    public function setMessage(?string $message): void
    {
        $this->message = $message;
    }

    /**
     * @return int
     */
    public function getType(): int
    {
        return $this->type;
    }

    /**
     * @param int $type
     */
    public function setType(int $type): void
    {
        $this->type = $type;
    }

    /**
     * @return bool
     */
    public function isFinished(): bool
    {
        return $this->isFinished;
    }

    /**
     * @param bool $isFinished
     */
    public function setIsFinished(bool $isFinished): void
    {
        $this->isFinished = $isFinished;
    }

    /**
     * @return bool
     */
    public function isMail(): bool
    {
        return $this->mail;
    }

    /**
     * @param bool $mail
     */
    public function setMail(bool $mail): void
    {
        $this->mail = $mail;
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
