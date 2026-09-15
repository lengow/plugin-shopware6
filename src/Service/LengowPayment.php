<?php declare(strict_types=1);

namespace Lengow\Connector\Service;

use Shopware\Core\Checkout\Payment\Cart\PaymentHandler\AbstractPaymentHandler;
use Shopware\Core\Checkout\Payment\Cart\PaymentHandler\PaymentHandlerType;
use Shopware\Core\Checkout\Payment\Cart\PaymentTransactionStruct;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Struct\Struct;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * Class LengowPayment
 * @package Lengow\Connector\Service
 *
 * Payment handler attached to the Lengow payment method. Orders imported from
 * a marketplace are already paid there, so nothing has to be captured here.
 *
 * Extends AbstractPaymentHandler, which is available from Shopware 6.6 and is
 * the only remaining payment handler contract in 6.7: the former
 * SynchronousPaymentHandlerInterface was removed in that version.
 */
class LengowPayment extends AbstractPaymentHandler
{
    /**
     * No refund, recurring or prepared payment flow is handled by this method
     *
     * @param PaymentHandlerType $type payment handler type to check
     * @param string $paymentMethodId payment method id
     * @param Context $context shopware context
     *
     * @return bool
     */
    public function supports(PaymentHandlerType $type, string $paymentMethodId, Context $context): bool
    {
        return false;
    }

    /**
     * Nothing to capture: the marketplace already collected the payment
     *
     * @param Request $request current request
     * @param PaymentTransactionStruct $transaction payment transaction
     * @param Context $context shopware context
     * @param Struct|null $validateStruct data returned by validate()
     *
     * @return RedirectResponse|null always null, no redirect is required
     */
    public function pay(
        Request $request,
        PaymentTransactionStruct $transaction,
        Context $context,
        ?Struct $validateStruct
    ): ?RedirectResponse
    {
        return null;
    }
}
