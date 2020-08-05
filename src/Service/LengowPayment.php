<?php declare(strict_types=1);

namespace Lengow\Connector\Service;

use Shopware\Core\Checkout\Payment\Cart\PaymentHandler\SynchronousPaymentHandlerInterface;
use Shopware\Core\Checkout\Payment\Cart\SyncPaymentTransactionStruct;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionStateHandler;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

/**
 * Class LengowPayment
 * @package Lengow\Connector\Service
 */
class LengowPayment implements SynchronousPaymentHandlerInterface
{
    /**
     * @var OrderTransactionStateHandler
     */
    private $transactionStateHandler;

    /**
     * LengowPayment constructor.
     *
     * @param OrderTransactionStateHandler $transactionStateHandler
     */
    public function __construct(OrderTransactionStateHandler $transactionStateHandler)
    {
        $this->transactionStateHandler = $transactionStateHandler;
    }

    /**
     * @param SyncPaymentTransactionStruct $transaction
     * @param RequestDataBag $dataBag
     * @param SalesChannelContext $salesChannelContext
     */
    public function pay(SyncPaymentTransactionStruct $transaction,
                        RequestDataBag $dataBag,
                        SalesChannelContext $salesChannelContext): void
    {
    }
}
