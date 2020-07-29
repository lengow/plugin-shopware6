<?php declare(strict_types=1);

namespace Lengow\Connector\Core\Content\Connector\Entity;

use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityIdTrait;

/**
 * Class OrderEntity
 * @package Lengow\Connector\Core\Content\Connector\Entity
 */
class OrderEntity extends Entity
{
    use EntityIdTrait;

    /**
     * @var string
     */
    protected $orderId;

    /**
     * @var int
     */
    protected $orderSku;

    /**
     * @var string
     */
    protected $salesChannelId;

    /**
     * @var int
     */
    protected $deliveryAddressId;

    /**
     * @var string
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
     * @var string
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
     * @var int
     */
    protected $orderItem;

    /**
     * @var string
     */
    protected $orderTypes;

    /**
     * @var string
     */
    protected $currency;

    /**
     * @var float
     */
    protected $totalPaid;

    /**
     * @var float
     */
    protected $commission;

    /**
     * @var string
     */
    protected $customerName;

    /**
     * @var string
     */
    protected $customerEmail;

    /**
     * @var string
     */
    protected $carrier;

    /**
     * @var string
     */
    protected $carrierMethod;

    /**
     * @var string
     */
    protected $carrierTracking;

    /**
     * @var string
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
     * @var string
     */
    protected $message;

    /**
     * @var \DateTime
     */
    protected $createdAt;

    /**
     * @var \DateTime
     */
    protected $updatedAt;

    /**
     * @var \DateTime
     */
    protected $importedAt;

    /**
     * @var string
     */
    protected $extra;

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
    public function getOrderSku(): int
    {
        return $this->orderSku;
    }

    /**
     * @param int $orderSku
     */
    public function setOrderSku(int $orderSku): void
    {
        $this->orderSku = $orderSku;
    }

    /**
     * @return string
     */
    public function getSalesChannelId(): string
    {
        return $this->salesChannelId;
    }

    /**
     * @param string $salesChannelId
     */
    public function setSalesChannelId(string $salesChannelId): void
    {
        $this->salesChannelId = $salesChannelId;
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
     * @return string
     */
    public function getDeliveryCountryIso(): string
    {
        return $this->deliveryCountryIso;
    }

    /**
     * @param string $deliveryCountryIso
     */
    public function setDeliveryCountryIso(string $deliveryCountryIso): void
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
     * @return string
     */
    public function getMarketplaceLabel(): string
    {
        return $this->marketplaceLabel;
    }

    /**
     * @param string $marketplaceLabel
     */
    public function setMarketplaceLabel(string $marketplaceLabel): void
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
     * @return int
     */
    public function getOrderItem(): int
    {
        return $this->orderItem;
    }

    /**
     * @param int $orderItem
     */
    public function setOrderItem(int $orderItem): void
    {
        $this->orderItem = $orderItem;
    }

    /**
     * @return string
     */
    public function getOrderTypes(): string
    {
        return $this->orderTypes;
    }

    /**
     * @param string $orderTypes
     */
    public function setOrderTypes(string $orderTypes): void
    {
        $this->orderTypes = $orderTypes;
    }

    /**
     * @return string
     */
    public function getCurrency(): string
    {
        return $this->currency;
    }

    /**
     * @param string $currency
     */
    public function setCurrency(string $currency): void
    {
        $this->currency = $currency;
    }

    /**
     * @return float
     */
    public function getTotalPaid(): float
    {
        return $this->totalPaid;
    }

    /**
     * @param float $totalPaid
     */
    public function setTotalPaid(float $totalPaid): void
    {
        $this->totalPaid = $totalPaid;
    }

    /**
     * @return float
     */
    public function getCommission(): float
    {
        return $this->commission;
    }

    /**
     * @param float $commission
     */
    public function setCommission(float $commission): void
    {
        $this->commission = $commission;
    }

    /**
     * @return string
     */
    public function getCustomerName(): string
    {
        return $this->customerName;
    }

    /**
     * @param string $customerName
     */
    public function setCustomerName(string $customerName): void
    {
        $this->customerName = $customerName;
    }

    /**
     * @return string
     */
    public function getCustomerEmail(): string
    {
        return $this->customerEmail;
    }

    /**
     * @param string $customerEmail
     */
    public function setCustomerEmail(string $customerEmail): void
    {
        $this->customerEmail = $customerEmail;
    }

    /**
     * @return string
     */
    public function getCarrier(): string
    {
        return $this->carrier;
    }

    /**
     * @param string $carrier
     */
    public function setCarrier(string $carrier): void
    {
        $this->carrier = $carrier;
    }

    /**
     * @return string
     */
    public function getCarrierMethod(): string
    {
        return $this->carrierMethod;
    }

    /**
     * @param string $carrierMethod
     */
    public function setCarrierMethod(string $carrierMethod): void
    {
        $this->carrierMethod = $carrierMethod;
    }

    /**
     * @return string
     */
    public function getCarrierTracking(): string
    {
        return $this->carrierTracking;
    }

    /**
     * @param string $carrierTracking
     */
    public function setCarrierTracking(string $carrierTracking): void
    {
        $this->carrierTracking = $carrierTracking;
    }

    /**
     * @return string
     */
    public function getCarrierIdRelay(): string
    {
        return $this->carrierIdRelay;
    }

    /**
     * @param string $carrierIdRelay
     */
    public function setCarrierIdRelay(string $carrierIdRelay): void
    {
        $this->carrierIdRelay = $carrierIdRelay;
    }

    /**
     * @return bool
     */
    public function getSentMarketplace(): bool
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
    public function getIsInError(): bool
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
    public function getIsReimported(): bool
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

    /**
     * @return \DateTime
     */
    public function getImportedAt(): \DateTime
    {
        return $this->importedAt;
    }

    /**
     * @param \DateTime $importedAt
     */
    public function setImportedAt(\DateTime $importedAt): void
    {
        $this->importedAt = $importedAt;
    }

    /**
     * @return string
     */
    public function getExtra(): string
    {
        return $this->extra;
    }

    /**
     * @param string $extra
     */
    public function setExtra(string $extra): void
    {
        $this->extra = $extra;
    }
}
