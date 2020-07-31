<?php declare(strict_types=1);

namespace Lengow\Connector\Entity\Lengow\Settings;

use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityIdTrait;
// OneToOne association class
use Shopware\Core\System\SalesChannel\SalesChannelEntity as ShopwareSalesChannelEntity;

/**
 * Class SettingsEntity
 * @package Lengow\Connector\Core\Content\Connector\Entity
 */
class SettingsEntity extends Entity
{
    use EntityIdTrait;

    /**
     * @var ShopwareSalesChannelEntity|null
     */
    protected $salesChannel;

    /**
     * @var string
     */
    protected $name;

    /**
     * @var string|null
     */
    protected $value;

    /**
     * @var \DateTime
     */
    protected $createdAt;

    /**
     * @var \DateTime
     */
    protected $updatedAt;

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
     * @return string
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * @param string $name
     */
    public function setName(string $name): void
    {
        $this->name = $name;
    }

    /**
     * @return string|null
     */
    public function getValue(): ?string
    {
        return $this->value;
    }

    /**
     * @param string|null $value
     */
    public function setValue(?string $value): void
    {
        $this->value = $value;
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
