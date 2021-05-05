<?php declare(strict_types=1);

namespace Lengow\Connector\Subscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Shopware\Core\Checkout\Order\OrderEntity as ShopwareOrderEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Struct\ArrayStruct;
use Shopware\Core\System\Currency\CurrencyCollection;
use Shopware\Core\System\Currency\CurrencyEntity;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Page\Checkout\Finish\CheckoutFinishPageLoadedEvent;
use Lengow\Connector\Service\LengowConfiguration;
use Lengow\Connector\Storefront\Struct\CheckoutFinishTrackerData;

/**
 * Class CheckoutFinishPageLoadedSubscriber
 * @package Lengow\Connector\Subscriber
 */
class CheckoutFinishPageLoadedSubscriber implements EventSubscriberInterface
{
    /**
     * @var string
     */
    public const TRACKING_WITH_ID = "productId";

    /**
     * @var string
     */
    public const TRACKING_WITH_REFERENCE = "productNumber";

    /**
     * @var EntityRepositoryInterface Shopware currency repository
     */
    private $currencyRepository;

    /**
     * @var LengowConfiguration Lengow configuration service
     */
    private $lengowConfiguration;

    /**
     * @var string tracking mode, either by Id or by reference
     */
    private $trackingMode;

    /**
     * LengowSettingUpdateSubscriber constructor
     *
     * @param EntityRepositoryInterface $currencyRepository Shopware currency repository
     * @param LengowConfiguration $lengowConfiguration Lengow configuration service
     */
    public function __construct(EntityRepositoryInterface $currencyRepository, LengowConfiguration $lengowConfiguration)
    {
        $this->currencyRepository = $currencyRepository;
        $this->lengowConfiguration = $lengowConfiguration;
    }

    /**
     * Mandatory for subscriber
     * the event CheckoutFinishPageLoadedEvent trigger once and only once a user land on the after checkout page
     *
     * @return array
     */
    public static function getSubscribedEvents(): array
    {
        return [
            CheckoutFinishPageLoadedEvent::class => 'onCheckoutFinish',
        ];
    }

    /**
     * get all tracker data and assign them to the template
     *
     * @param CheckoutFinishPageLoadedEvent $event shopware event
     */
    public function onCheckoutFinish(CheckoutFinishPageLoadedEvent $event) : void
    {
        // if tracking is disable, no need for anything else
        if (!$this->lengowConfiguration->get(LengowConfiguration::TRACKING_ENABLED)) {
            return;
        }
        // retrieve tracking mode from configuration
        $this->trackingMode =
            $this->lengowConfiguration->get(LengowConfiguration::TRACKING_ID) === self::TRACKING_WITH_ID
                ? self::TRACKING_WITH_ID
                : self::TRACKING_WITH_REFERENCE;
        /** @var SalesChannelContext $salesChannelContext */
        $salesChannelContext = $event->getSalesChannelContext();
        /** @var ShopwareOrderEntity $order */
        $order = $event->getPage()->getOrder();
        $trackerData = new CheckoutFinishTrackerData();
        $trackerData->assign([
            'data' => $this->getTrackerData($order, $salesChannelContext),
        ]);
        $event->getPage()->addExtension(CheckoutFinishTrackerData::EXTENSION_NAME, $trackerData);
    }

    /**
     * Get all data for lengow tracker
     *
     * @param ShopwareOrderEntity $order the shopware order
     * @param SalesChannelContext $salesChannelContext the sales channel context
     *
     * @return array
     */
    private function getTrackerData(ShopwareOrderEntity $order, SalesChannelContext $salesChannelContext): array
    {
        return [
            'accountId' => (int) $this->lengowConfiguration->get(LengowConfiguration::ACCOUNT_ID),
            'orderReference' => $order->getOrderNumber(),
            'orderAmount' => $order->getPrice()->getTotalPrice(),
            'currency' => $this->retrieveOrderCurrency($order->getCurrencyId()),
            'paymentMethod' => $salesChannelContext->getPaymentMethod()->getName(),
            'cart' => json_encode($this->getCartData($order)),
            'cartNumber' => 0,
            'newBiz' => 1,
            'valid' => 1,
        ];
    }

    /**
     * Get all cart data from order
     *
     * @param ShopwareOrderEntity $order the shopware order
     *
     * @return array
     */
    private function getCartData(ShopwareOrderEntity $order): array
    {
        $cartData = [];
        if ($order->getLineItems()) {
            foreach ($order->getLineItems() as $lineItem) {
                $cartData[] = [
                    'product_id' => $this->trackingMode === self::TRACKING_WITH_ID
                        ? (string) $lineItem->getProductId()
                        : (string) $lineItem->getPayload()['productNumber'],
                    'price' => (int) $lineItem->getTotalPrice(),
                    'quantity' => (int) $lineItem->getQuantity(),
                ];
            }
        }
        return $cartData;
    }

    /**
     * get currency from order currency Id
     *
     * @param string $currencyId the currency id
     *
     * @return string
     */
    private function retrieveOrderCurrency(string $currencyId): string
    {
        $currencyCriteria = new Criteria();
        $currencyCriteria->setIds([$currencyId]);
        $currenciesCollection = $this->currencyRepository->search(
            $currencyCriteria,
            Context::createDefaultContext()
        )->getEntities();
        if ($currenciesCollection->count() > 0) {
            return $currenciesCollection->first()->getIsoCode();
        }
        return '';
    }
}
