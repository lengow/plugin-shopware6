<?php declare(strict_types=1);

namespace Lengow\Connector\Service;

use \DateTime;
use \Exception;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\System\Currency\CurrencyCollection;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;
use Lengow\Connector\Components\LengowMarketplace;
use Lengow\Connector\Entity\Lengow\Order\OrderEntity as LengowOrderEntity;
use Lengow\Connector\Entity\Lengow\OrderError\OrderErrorEntity as LengowOrderErrorEntity;
use Lengow\Connector\Exception\LengowException;
use Lengow\Connector\Factory\LengowMarketplaceFactory;

/**
 * Class LengowImportOrder
 * @package Lengow\Connector\Service
 */
class LengowImportOrder
{
    /**
     * @var string result for order imported
     */
    private const RESULT_NEW = 'new';

    /**
     * @var string result for order updated
     */
    private const RESULT_UPDATE = 'update';

    /**
     * @var string result for order in error
     */
    private const RESULT_ERROR = 'error';

    /**
     * @var int interval of months for order import
     */
    private const MONTH_INTERVAL_TIME = 3;

    /**
     * @var LengowConfiguration Lengow configuration service
     */
    private $lengowConfiguration;

    /**
     * @var LengowLog Lengow log service
     */
    private $lengowLog;

    /**
     * @var LengowMarketplaceFactory Lengow marketplace factory
     */
    private $lengowMarketplaceFactory;

    /**
     * @var LengowOrderError Lengow order error service
     */
    private $lengowOrderError;

    /**
     * @var LengowOrder Lengow order service
     */
    private $lengowOrder;

    /**
     * @var EntityRepositoryInterface Shopware currency repository
     */
    private $currencyRepository;

    /**
     * @var SalesChannelEntity Shopware sales channel entity
     */
    private $salesChannel;

    /**
     * @var array valid states lengow to create a Lengow order
     */
    private $lengowStates = [
        LengowOrder::STATE_WAITING_SHIPMENT,
        LengowOrder::STATE_SHIPPED,
        LengowOrder::STATE_CLOSED,
    ];

    /**
     * @var bool use debug mode
     */
    private $debugMode;

    /**
     * @var bool display log messages
     */
    private $logOutput;

    /**
     * @var LengowMarketplace Lengow marketplace instance
     */
    private $lengowMarketplace;

    /**
     * @var string id lengow of current order
     */
    private $marketplaceSku;

    /**
     * @var int id of delivery address for current order
     */
    private $deliveryAddressId;

    /**
     * @var mixed API order data
     */
    private $orderData;

    /**
     * @var mixed API package data
     */
    private $packageData;

    /**
     * @var bool is first package
     */
    private $firstPackage;

    /**
     * @var bool import one order var from lengow import
     */
    private $importOneOrder;

    /**
     * @var string marketplace order state
     */
    private $orderStateMarketplace;

    /**
     * @var string Lengow order state
     */
    private $orderStateLengow;

    /**
     * @var bool re-import order
     */
    private $isReimported = false;

    /**
     * LengowImportOrder Construct
     *
     * @param LengowConfiguration $lengowConfiguration Lengow configuration service
     * @param LengowLog $lengowLog Lengow log service
     * @param LengowMarketplaceFactory $lengowMarketplaceFactory Lengow marketplace factory
     * @param LengowOrderError $lengowOrderError Lengow order error service
     * @param LengowOrder $lengowOrder Lengow order service
     * @param EntityRepositoryInterface $currencyRepository Shopware currency repository
     */
    public function __construct(
        LengowConfiguration $lengowConfiguration,
        LengowLog $lengowLog,
        LengowMarketplaceFactory $lengowMarketplaceFactory,
        LengowOrderError $lengowOrderError,
        LengowOrder $lengowOrder,
        EntityRepositoryInterface $currencyRepository
    )
    {
        $this->lengowConfiguration = $lengowConfiguration;
        $this->lengowLog = $lengowLog;
        $this->lengowMarketplaceFactory = $lengowMarketplaceFactory;
        $this->lengowOrderError = $lengowOrderError;
        $this->lengowOrder = $lengowOrder;
        $this->currencyRepository = $currencyRepository;
    }

    /**
     * init a import order
     *
     * @param array $params optional options for load a import order
     *
     * @throws LengowException
     */
    public function init(array $params): void
    {
        $this->salesChannel = $params['sales_channel'];
        $this->debugMode = $params['debug_mode'];
        $this->logOutput = $params['log_output'];
        $this->marketplaceSku = $params['marketplace_sku'];
        $this->deliveryAddressId = $params['delivery_address_id'];
        $this->orderData = $params['order_data'];
        $this->packageData = $params['package_data'];
        $this->firstPackage = $params['first_package'];
        $this->importOneOrder = $params['import_one_order'];
        $this->lengowMarketplace = $this->lengowMarketplaceFactory->create($this->orderData->marketplace);
        $this->orderStateMarketplace = $this->orderData->marketplace_status;
        $this->orderStateLengow = $this->lengowMarketplace->getStateLengow($this->orderStateMarketplace);
    }

    /**
     * Create or update order
     *
     * @throws Exception|LengowException
     *
     * @return array|false
     */
    public function exec()
    {
        // if order error exist and not finished -> stop import order
        $orderErrors = $this->lengowOrderError->orderIsInError($this->marketplaceSku, $this->deliveryAddressId);
        if ($orderErrors) {
            /** @var LengowOrderErrorEntity $orderError */
            $orderError = $orderErrors->first();
            $decodedMessage = $this->lengowLog->decodeMessage(
                $orderError->getMessage(),
                LengowTranslation::DEFAULT_ISO_CODE
            );
            $this->lengowLog->write(
                LengowLog::CODE_IMPORT,
                $this->lengowLog->encodeMessage('log.import.error_already_created', [
                    'decoded_message' => $decodedMessage,
                    'date_message' => $orderError->getCreatedAt()->format('Y-m-d H:i:s'),
                ]),
                $this->logOutput,
                $this->marketplaceSku
            );
            return false;
        }
        // get a Shopware order id in the lengow order table
        $order = $this->lengowOrder->getOrderFromLengowOrder(
            $this->marketplaceSku,
            $this->lengowMarketplace->getName(),
            $this->deliveryAddressId
        );
        // if order is already exist
        if ($order) {
            // TODO check and update order
            return false;
        }
        if (!$this->importOneOrder) {
            // skip import if the order is anonymized
            if ($this->orderData->anonymized) {
                $this->lengowLog->write(
                    LengowLog::CODE_IMPORT,
                    $this->lengowLog->encodeMessage('log.import.anonymized_order'),
                    $this->logOutput,
                    $this->marketplaceSku
                );
                return false;
            }
            // skip import if the order is older than 3 months
            $dateTimeOrder = new DateTime($this->orderData->marketplace_order_date);
            $interval = $dateTimeOrder->diff(new DateTime());
            $monthsInterval = $interval->m + ($interval->y * 12);
            if ($monthsInterval >= self::MONTH_INTERVAL_TIME) {
                $this->lengowLog->write(
                    LengowLog::CODE_IMPORT,
                    $this->lengowLog->encodeMessage('log.import.old_order'),
                    $this->logOutput,
                    $this->marketplaceSku
                );
                return false;
            }
        }
        // checks if an external id already exists
        $orderId = $this->checkExternalIds($this->orderData->merchant_order_id);
        if ($orderId && !$this->debugMode && !$this->isReimported) {
            $this->lengowLog->write(
                LengowLog::CODE_IMPORT,
                $this->lengowLog->encodeMessage('log.import.external_id_exist', [
                    'order_id' => $orderId
                ]),
                $this->logOutput,
                $this->marketplaceSku
            );
            return false;
        }
        /** @var LengowOrderEntity $lengowOrder */
        // TODO get a record in the lengow order table
        $lengowOrder = null;
        // if order is new, accepted, canceled or refunded -> skip
        if (!in_array($this->orderStateLengow, $this->lengowStates)) {
            // TODO check and complete an order not imported if it is canceled or refunded
            $this->lengowLog->write(
                LengowLog::CODE_IMPORT,
                $this->lengowLog->encodeMessage('log.import.current_order_state_unavailable', [
                    'order_state_marketplace' => $this->orderStateMarketplace,
                    'marketplace_name' => $this->lengowMarketplace->getName(),
                ]),
                $this->logOutput,
                $this->marketplaceSku
            );
            return false;
        }
        // TODO load order types data
        // TODO create a new record in lengow order table if not exist
        // TODO created a record in the lengow order table
        // checks if the required order data is present
        if (!$this->checkOrderData($lengowOrder)) {
            // TODO return error result
        }
        return false;
    }

    /**
     * Checks if an external id already exists
     *
     * @param array|null $externalIds external ids return by API
     *
     * @return int|false
     */
    private function checkExternalIds(array $externalIds = null)
    {
        if (empty($externalIds)) {
            return false;
        }
        foreach ($externalIds as $externalId) {
            if ($this->lengowOrder->getLengowOrderFromOrderId($externalId, $this->deliveryAddressId)) {
                return $externalId;
            }
        }
        return false;
    }

    /**
     * Checks if order data are present
     *
     * @param LengowOrderEntity|null $lengowOrder Lengow Order instance
     *
     * @return bool
     */
    protected function checkOrderData(LengowOrderEntity $lengowOrder = null): bool
    {
        $errorMessages = [];
        if (empty($this->packageData->cart)) {
            $errorMessages[] = $this->lengowLog->encodeMessage('lengow_log.error.no_product');
        }
        if (!isset($this->orderData->currency->iso_a3)) {
            $errorMessages[] = $this->lengowLog->encodeMessage('lengow_log.error.no_currency');
        } else {
            $context = Context::createDefaultContext();
            $criteria = new Criteria();
            $criteria->addFilter(new EqualsFilter('isoCode', $this->orderData->currency->iso_a3));
            /** @var CurrencyCollection $currencyCollection */
            $currencyCollection = $this->currencyRepository->search($criteria, $context)->getEntities();
            if ($currencyCollection->count() === 0) {
                $errorMessages[] = $this->lengowLog->encodeMessage('lengow_log.error.currency_not_available', [
                    'currency_iso' => $this->orderData->currency->iso_a3,
                ]);
            }
        }
        if ($this->orderData->total_order == -1) {
            $errorMessages[] = $this->lengowLog->encodeMessage('lengow_log.error.no_change_rate');
        }
        if ($this->orderData->billing_address === null) {
            $errorMessages[] = $this->lengowLog->encodeMessage('lengow_log.error.no_billing_address');
        } elseif ($this->orderData->billing_address->common_country_iso_a2 === null) {
            $errorMessages[] = $this->lengowLog->encodeMessage('lengow_log.error.no_country_for_billing_address');
        }
        if ($this->packageData->delivery->common_country_iso_a2 === null) {
            $errorMessages[] = $this->lengowLog->encodeMessage('lengow_log.error.no_country_for_delivery_address');
        }
        if (!empty($errorMessages)) {
            foreach ($errorMessages as $errorMessage) {
                // TODO create order error
                $decodedMessage = $this->lengowLog->decodeMessage($errorMessage, LengowTranslation::DEFAULT_ISO_CODE);
                $this->lengowLog->write(
                    LengowLog::CODE_IMPORT,
                    $this->lengowLog->encodeMessage('log.import.order_import_failed', [
                        'decoded_message' => $decodedMessage,
                    ]),
                    $this->logOutput,
                    $this->marketplaceSku
                );
            };
            return false;
        }
        return true;
    }

    /**
     * Return an array of result for each order
     *
     * @param string $type Type of result (new, update, error)
     * @param string $lengowOrderId Lengow order id
     * @param string|null $orderId Shopware order id
     *
     * @return array
     */
    private function returnResult(string $type, string $lengowOrderId, string $orderId = null): array
    {
        return [
            'order_id' => $orderId,
            'lengow_order_id' => $lengowOrderId,
            'marketplace_sku' => $this->marketplaceSku,
            'marketplace_name' => $this->lengowMarketplace->getName(),
            'lengow_state' => $this->orderStateLengow,
            'order_new' => $type === self::RESULT_NEW,
            'order_update' => $type === self::RESULT_UPDATE,
            'order_error' => $type === self::RESULT_ERROR,
        ];
    }
}