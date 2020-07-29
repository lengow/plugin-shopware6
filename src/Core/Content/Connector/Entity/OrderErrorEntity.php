<?php declare(strict_types=1);

namespace Lengow\Connector\Core\Content\Connector\Entity;

use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityIdTrait;

/**
 * Class OrderErrorEntity
 * @package Lengow\Connector\Core\Content\Connector\Entity
 */
class OrderErrorEntity extends Entity
{
    use EntityIdTrait;

    /**
     * @var string
     */
    protected $lengowOrderId;

    /**
     * @var string
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
    public function getLengowOrderId(): string
    {
        return $this->lengowOrderId;
    }

    /**
     * @param string $lengowOrderId
     */
    public function setLengowOrderId(string $lengowOrderId): void
    {
        $this->lengowOrderId = $lengowOrderId;
    }

    /**
     * @return string
     */
    public function getMessage(): string
    {
        return $this->message;
    }

    /**
     * @param string $message
     */
    public function setMessage(string $message): void
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
