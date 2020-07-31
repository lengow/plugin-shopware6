<?php declare(strict_types=1);

namespace Lengow\Connector\Entity\Lengow\Order;

use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityIdTrait;
// OneToOne association class
use Shopware\Core\Checkout\Order\OrderEntity as ShopwareOrderEntity;
use Shopware\Core\System\SalesChannel\SalesChannelEntity as ShopwareSalesChannelEntity;

/**
 * Class OrderEntity
 * @package Lengow\Connector\Entity\Lengow\Order
 */
class OrderEntity extends Entity
{
    use EntityIdTrait;

    /**
     * @var ShopwareOrderEntity|null
     */
    protected $order;

    /**
     * @var int|null
     */
    protected $orderSku;

    /**
     * @var ShopwareSalesChannelEntity
     */
    protected $salesChannel;

    /**
     * @var int
     */
    protected $deliveryAddressId;

    /**
     * @var string|null
     */
    protected $deliveryCountryIso;

    /**
     * @var string
     */
    protected $marketplaceSku;

    /**
     * @var string
     */
    protected $marketplaceName;

    /**
     * @var string|null
     */
    protected $marketplaceLabel;

    /**
     * @var string
     */
    protected $orderLengowState;

    /**
     * @var int
     */
    protected $orderProcessState;

    /**
     * @var \DateTime
     */
    protected $orderDate;

    /**
     * @var int|null
     */
    protected $orderItem;

    /**
     * @var array|null
     */
    protected $orderTypes;

    /**
     * @var string|null
     */
    protected $currency;

    /**
     * @var float|null
     */
    protected $totalPaid;

    /**
     * @var float|null
     */
    protected $commission;

    /**
     * @var string|null
     */
    protected $customerName;

    /**
     * @var string|null
     */
    protected $customerEmail;

    /**
     * @var string|null
     */
    protected $carrier;

    /**
     * @var string|null
     */
    protected $carrierMethod;

    /**
     * @var string|null
     */
    protected $carrierTracking;

    /**
     * @var string|null
     */
    protected $carrierIdRelay;

    /**
     * @var bool
     */
    protected $sentMarketplace;

    /**
     * @var bool
     */
    protected $isInError;

    /**
     * @var bool
     */
    protected $isReimported;

    /**
     * @var string|null
     */
    protected $message;

    /**
     * @var \DateTime|null
     */
    protected $createdAt;

    /**
     * @var \DateTime|null
     */
    protected $updatedAt;

    /**
     * @var \DateTime|null
     */
    protected $importedAt;

    /**
     * @var array|null
     */
    protected $extra;

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
     * @return int|null
     */
    public function getOrderSku(): ?int
    {
        return $this->orderSku;
    }

    /**
     * @param int|null $orderSku
     */
    public function setOrderSku(?int $orderSku): void
    {
        $this->orderSku = $orderSku;
    }

    /**
     * @return ShopwareSalesChannelEntity
     */
    public function getSalesChannel(): ShopwareSalesChannelEntity
    {
        return $this->salesChannel;
    }

    /**
     * @param ShopwareSalesChannelEntity $salesChannel
     */
    public function setSalesChannel(ShopwareSalesChannelEntity $salesChannel): void
    {
        $this->salesChannel = $salesChannel;
    }

    /**
     * @return int
     */
    public function getDeliveryAddressId(): int
    {
        return $this->deliveryAddressId;
    }

    /**
     * @param int $deliveryAddressId
     */
    public function setDeliveryAddressId(int $deliveryAddressId): void
    {
        $this->deliveryAddressId = $deliveryAddressId;
    }

    /**
     * @return string|null
     */
    public function getDeliveryCountryIso(): ?string
    {
        return $this->deliveryCountryIso;
    }

    /**
     * @param string|null $deliveryCountryIso
     */
    public function setDeliveryCountryIso(?string $deliveryCountryIso): void
    {
        $this->deliveryCountryIso = $deliveryCountryIso;
    }

    /**
     * @return string
     */
    public function getMarketplaceSku(): string
    {
        return $this->marketplaceSku;
    }

    /**
     * @param string $marketplaceSku
     */
    public function setMarketplaceSku(string $marketplaceSku): void
    {
        $this->marketplaceSku = $marketplaceSku;
    }

    /**
     * @return string
     */
    public function getMarketplaceName(): string
    {
        return $this->marketplaceName;
    }

    /**
     * @param string $marketplaceName
     */
    public function setMarketplaceName(string $marketplaceName): void
    {
        $this->marketplaceName = $marketplaceName;
    }

    /**
     * @return string|null
     */
    public function getMarketplaceLabel(): ?string
    {
        return $this->marketplaceLabel;
    }

    /**
     * @param string|null $marketplaceLabel
     */
    public function setMarketplaceLabel(?string $marketplaceLabel): void
    {
        $this->marketplaceLabel = $marketplaceLabel;
    }

    /**
     * @return string
     */
    public function getOrderLengowState(): string
    {
        return $this->orderLengowState;
    }

    /**
     * @param string $orderLengowState
     */
    public function setOrderLengowState(string $orderLengowState): void
    {
        $this->orderLengowState = $orderLengowState;
    }

    /**
     * @return int
     */
    public function getOrderProcessState(): int
    {
        return $this->orderProcessState;
    }

    /**
     * @param int $orderProcessState
     */
    public function setOrderProcessState(int $orderProcessState): void
    {
        $this->orderProcessState = $orderProcessState;
    }

    /**
     * @return \DateTime
     */
    public function getOrderDate(): \DateTime
    {
        return $this->orderDate;
    }

    /**
     * @param \DateTime $orderDate
     */
    public function setOrderDate(\DateTime $orderDate): void
    {
        $this->orderDate = $orderDate;
    }

    /**
     * @return int|null
     */
    public function getOrderItem(): ?int
    {
        return $this->orderItem;
    }

    /**
     * @param int|null $orderItem
     */
    public function setOrderItem(?int $orderItem): void
    {
        $this->orderItem = $orderItem;
    }

    /**
     * @return array|null
     */
    public function getOrderTypes(): ?array
    {
        return $this->orderTypes;
    }

    /**
     * @param array|null $orderTypes
     */
    public function setOrderTypes(?array $orderTypes): void
    {
        $this->orderTypes = $orderTypes;
    }

    /**
     * @return string|null
     */
    public function getCurrency(): ?string
    {
        return $this->currency;
    }

    /**
     * @param string|null $currency
     */
    public function setCurrency(?string $currency): void
    {
        $this->currency = $currency;
    }

    /**
     * @return float|null
     */
    public function getTotalPaid(): ?float
    {
        return $this->totalPaid;
    }

    /**
     * @param float|null $totalPaid
     */
    public function setTotalPaid(?float $totalPaid): void
    {
        $this->totalPaid = $totalPaid;
    }

    /**
     * @return float|null
     */
    public function getCommission(): ?float
    {
        return $this->commission;
    }

    /**
     * @param float|null $commission
     */
    public function setCommission(?float $commission): void
    {
        $this->commission = $commission;
    }

    /**
     * @return string|null
     */
    public function getCustomerName(): ?string
    {
        return $this->customerName;
    }

    /**
     * @param string|null $customerName
     */
    public function setCustomerName(?string $customerName): void
    {
        $this->customerName = $customerName;
    }

    /**
     * @return string|null
     */
    public function getCustomerEmail(): ?string
    {
        return $this->customerEmail;
    }

    /**
     * @param string|null $customerEmail
     */
    public function setCustomerEmail(?string $customerEmail): void
    {
        $this->customerEmail = $customerEmail;
    }

    /**
     * @return string|null
     */
    public function getCarrier(): ?string
    {
        return $this->carrier;
    }

    /**
     * @param string|null $carrier
     */
    public function setCarrier(?string $carrier): void
    {
        $this->carrier = $carrier;
    }

    /**
     * @return string|null
     */
    public function getCarrierMethod(): ?string
    {
        return $this->carrierMethod;
    }

    /**
     * @param string|null $carrierMethod
     */
    public function setCarrierMethod(?string $carrierMethod): void
    {
        $this->carrierMethod = $carrierMethod;
    }

    /**
     * @return string|null
     */
    public function getCarrierTracking(): ?string
    {
        return $this->carrierTracking;
    }

    /**
     * @param string|null $carrierTracking
     */
    public function setCarrierTracking(?string $carrierTracking): void
    {
        $this->carrierTracking = $carrierTracking;
    }

    /**
     * @return string|null
     */
    public function getCarrierIdRelay(): ?string
    {
        return $this->carrierIdRelay;
    }

    /**
     * @param string|null $carrierIdRelay
     */
    public function setCarrierIdRelay(?string $carrierIdRelay): void
    {
        $this->carrierIdRelay = $carrierIdRelay;
    }

    /**
     * @return bool
     */
    public function isSentMarketplace(): bool
    {
        return $this->sentMarketplace;
    }

    /**
     * @param bool $sentMarketplace
     */
    public function setSentMarketplace(bool $sentMarketplace): void
    {
        $this->sentMarketplace = $sentMarketplace;
    }

    /**
     * @return bool
     */
    public function isInError(): bool
    {
        return $this->isInError;
    }

    /**
     * @param bool $isInError
     */
    public function setIsInError(bool $isInError): void
    {
        $this->isInError = $isInError;
    }

    /**
     * @return bool
     */
    public function isReimported(): bool
    {
        return $this->isReimported;
    }

    /**
     * @param bool $isReimported
     */
    public function setIsReimported(bool $isReimported): void
    {
        $this->isReimported = $isReimported;
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

    /**
     * @return \DateTime|null
     */
    public function getImportedAt(): ?\DateTime
    {
        return $this->importedAt;
    }

    /**
     * @param \DateTime|null $importedAt
     */
    public function setImportedAt(?\DateTime $importedAt): void
    {
        $this->importedAt = $importedAt;
    }

    /**
     * @return array|null
     */
    public function getExtra(): ?array
    {
        return $this->extra;
    }

    /**
     * @param array|null $extra
     */
    public function setExtra(?array $extra): void
    {
        $this->extra = $extra;
    }
}
