<?php declare(strict_types=1);

namespace Lengow\Connector\tests;

use Lengow\Connector\Exception\LengowException;
use Lengow\Connector\Service\LengowConfiguration;
use Lengow\Connector\Service\LengowImportOrder;
use Lengow\Connector\Service\LengowLog;
use Lengow\Connector\Service\LengowOrder;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionMethod;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\Price\QuantityPriceCalculator;
use Shopware\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Shopware\Core\Checkout\Cart\Price\Struct\CartPrice;
use Shopware\Core\Checkout\Cart\Price\Struct\QuantityPriceDefinition;
use Shopware\Core\Checkout\Cart\Tax\Struct\CalculatedTaxCollection;
use Shopware\Core\Checkout\Cart\Tax\Struct\TaxRule;
use Shopware\Core\Checkout\Cart\Tax\Struct\TaxRuleCollection;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

class LengowImportOrderTest extends TestCase
{
    public function testUnmappedCartLineKeepsItsShopwarePrice(): void
    {
        $lengowLog = $this->createMock(LengowLog::class);
        $lengowLog->expects(self::once())
            ->method('encodeMessage')
            ->with('log.import.unmapped_cart_line', [
                'product_id' => 'auxiliary-product',
                'line_item_type' => 'custom',
            ])
            ->willReturn('unmapped cart line');
        $lengowLog->expects(self::once())
            ->method('write')
            ->with(LengowLog::CODE_IMPORT, 'unmapped cart line', false, 'marketplace-sku');

        $order = $this->createImportOrder($lengowLog);
        $price = new \stdClass();
        $orderData = [
            'lineItems' => [[
                'productId' => 'auxiliary-product',
                'type' => 'custom',
                'price' => $price,
            ]],
        ];

        $result = $this->invokeChangeProductPrice($order, $orderData, []);

        self::assertSame($price, $result['lineItems'][0]['price']);
    }

    public function testCartLineWithoutProductIdUsesActionableLogContext(): void
    {
        $lengowLog = $this->createMock(LengowLog::class);
        $lengowLog->expects(self::once())
            ->method('encodeMessage')
            ->with('log.import.unmapped_cart_line', [
                'product_id' => 'unknown',
                'line_item_type' => 'custom',
            ])
            ->willReturn('unmapped cart line');
        $lengowLog->expects(self::once())
            ->method('write')
            ->with(LengowLog::CODE_IMPORT, 'unmapped cart line', false, 'marketplace-sku');

        $order = $this->createImportOrder($lengowLog);
        $price = new \stdClass();
        $orderData = [
            'lineItems' => [[
                'type' => 'custom',
                'price' => $price,
            ]],
        ];

        $result = $this->invokeChangeProductPrice($order, $orderData, []);

        self::assertSame($price, $result['lineItems'][0]['price']);
    }

    public function testMappedCartLineWithInvalidMarketplacePriceStillFails(): void
    {
        $lengowLog = $this->createMock(LengowLog::class);
        $lengowLog->expects(self::once())
            ->method('encodeMessage')
            ->with('lengow_log.exception.price_unit_not_valid', [
                'product_id' => 'marketplace-product',
            ])
            ->willReturn('invalid marketplace price');

        $order = $this->createImportOrder($lengowLog);

        $this->expectException(LengowException::class);
        $this->expectExceptionMessage('invalid marketplace price');

        $this->invokeChangeProductPrice(
            $order,
            [
                'lineItems' => [[
                    'productId' => 'marketplace-product',
                    'price' => null,
                ]],
            ],
            [
                'marketplace-product' => [
                    'quantity' => 1,
                ],
            ]
        );
    }

    public function testB2bOrderWithSourceTaxDoesNotForceTaxFreeCart(): void
    {
        $configuration = $this->createMock(LengowConfiguration::class);
        $configuration->expects(self::once())
            ->method('get')
            ->with(LengowConfiguration::B2B_WITHOUT_TAX_ENABLED)
            ->willReturn(true);
        $cart = $this->createMock(Cart::class);
        $cart->expects(self::never())->method('setPrice');
        $order = $this->createVatOrder(
            $configuration,
            ['is_business' => true],
            (object) ['total_tax' => '10.62']
        );

        self::assertSame($cart, $this->invokeSetOrderVatMode($order, $cart));
    }

    public function testB2bOrderWithoutSourceTaxForcesTaxFreeCart(): void
    {
        $configuration = $this->createMock(LengowConfiguration::class);
        $configuration->expects(self::once())
            ->method('get')
            ->with(LengowConfiguration::B2B_WITHOUT_TAX_ENABLED)
            ->willReturn(true);
        $cart = $this->createMock(Cart::class);
        $cart->expects(self::once())
            ->method('setPrice')
            ->with(self::callback(
                static fn (CartPrice $price): bool => $price->getTaxStatus() === CartPrice::TAX_STATE_FREE
            ));
        $order = $this->createVatOrder(
            $configuration,
            ['is_business' => true],
            (object) ['total_tax' => '0.00']
        );

        self::assertSame($cart, $this->invokeSetOrderVatMode($order, $cart));
    }

    public function testNonB2bOrderDoesNotForceTaxFreeCart(): void
    {
        $configuration = $this->createMock(LengowConfiguration::class);
        $configuration->expects(self::never())->method('get');
        $cart = $this->createMock(Cart::class);
        $cart->expects(self::never())->method('setPrice');
        $order = $this->createVatOrder(
            $configuration,
            [],
            (object) ['total_tax' => '0.00']
        );

        self::assertSame($cart, $this->invokeSetOrderVatMode($order, $cart));
    }

    public function testTaxFreeOrderDropsVatFromShippingCosts(): void
    {
        $calculator = $this->createMock(QuantityPriceCalculator::class);
        $calculator->expects(self::once())
            ->method('calculate')
            ->with(self::callback(static function (QuantityPriceDefinition $definition): bool {
                return $definition->getPrice() === 11.1 && $definition->getTaxRules()->count() === 0;
            }))
            ->willReturn($this->createCalculatedPrice(11.1, new TaxRuleCollection()));

        $orderData = $this->invokeChangeShippingCosts(
            $this->createShippingOrder($calculator),
            $this->createOrderData(CartPrice::TAX_STATE_FREE)
        );

        self::assertCount(0, $orderData['shippingCosts']->getCalculatedTaxes());
        self::assertSame($orderData['shippingCosts'], $orderData['deliveries'][0]['shippingCosts']);
    }

    public function testTaxedOrderKeepsVatOnShippingCosts(): void
    {
        $calculator = $this->createMock(QuantityPriceCalculator::class);
        $calculator->expects(self::once())
            ->method('calculate')
            ->with(self::callback(static function (QuantityPriceDefinition $definition): bool {
                return $definition->getPrice() === 11.1 && $definition->getTaxRules()->count() === 1;
            }))
            ->willReturn($this->createCalculatedPrice(11.1, $this->createTaxRules()));

        $orderData = $this->invokeChangeShippingCosts(
            $this->createShippingOrder($calculator),
            $this->createOrderData(CartPrice::TAX_STATE_GROSS)
        );

        self::assertCount(1, $orderData['shippingCosts']->getTaxRules());
    }

    public function testTaxFreeOrderDropsVatFromProductPrice(): void
    {
        $calculated = $this->createCalculatedPrice(36.44, new TaxRuleCollection());
        $calculator = $this->createMock(QuantityPriceCalculator::class);
        $calculator->expects(self::once())
            ->method('calculate')
            ->with(self::callback(static function (QuantityPriceDefinition $definition): bool {
                return $definition->getPrice() === 36.44 && $definition->getTaxRules()->count() === 0;
            }))
            ->willReturn($calculated);

        $reflection = new ReflectionClass(LengowImportOrder::class);
        /** @var LengowImportOrder $order */
        $order = $reflection->newInstanceWithoutConstructor();
        $this->setProperty($reflection, $order, 'calculator', $calculator);

        $orderData = $this->invokeChangeProductPrice(
            $order,
            [
                'price' => $this->createCartPrice(CartPrice::TAX_STATE_FREE),
                'lineItems' => [[
                    'productId' => 'marketplace-product',
                    'price' => $this->createCalculatedPrice(36.44, $this->createTaxRules()),
                ]],
            ],
            [
                'marketplace-product' => [
                    'price_unit' => 36.44,
                    'quantity' => 1,
                ],
            ]
        );

        self::assertSame($calculated, $orderData['lineItems'][0]['price']);
        self::assertCount(0, $orderData['lineItems'][0]['priceDefinition']->getTaxRules());
    }

    private function createImportOrder(LengowLog $lengowLog): LengowImportOrder
    {
        $reflection = new ReflectionClass(LengowImportOrder::class);
        /** @var LengowImportOrder $order */
        $order = $reflection->newInstanceWithoutConstructor();

        $this->setProperty($reflection, $order, 'lengowLog', $lengowLog);
        $this->setProperty($reflection, $order, 'logOutput', false);
        $this->setProperty($reflection, $order, 'marketplaceSku', 'marketplace-sku');

        return $order;
    }

    private function invokeChangeProductPrice(LengowImportOrder $order, array $orderData, array $products): array
    {
        $method = new ReflectionMethod(LengowImportOrder::class, 'changeProductPrice');
        $method->setAccessible(true);

        /** @var SalesChannelContext $salesChannelContext */
        $salesChannelContext = (new ReflectionClass(SalesChannelContext::class))->newInstanceWithoutConstructor();

        return $method->invoke($order, $orderData, $products, $salesChannelContext);
    }

    private function createVatOrder(
        LengowConfiguration $configuration,
        array $orderTypes,
        object $orderData
    ): LengowImportOrder {
        $reflection = new ReflectionClass(LengowImportOrder::class);
        /** @var LengowImportOrder $order */
        $order = $reflection->newInstanceWithoutConstructor();

        $this->setProperty($reflection, $order, 'lengowConfiguration', $configuration);
        $this->setProperty($reflection, $order, 'orderTypes', $orderTypes);
        $this->setProperty($reflection, $order, 'orderData', $orderData);

        return $order;
    }

    private function invokeSetOrderVatMode(LengowImportOrder $order, Cart $cart): Cart
    {
        $method = new ReflectionMethod(LengowImportOrder::class, 'setOrderVatMode');
        $method->setAccessible(true);

        return $method->invoke($order, $cart);
    }

    private function createShippingOrder(QuantityPriceCalculator $calculator): LengowImportOrder
    {
        $reflection = new ReflectionClass(LengowImportOrder::class);
        /** @var LengowImportOrder $order */
        $order = $reflection->newInstanceWithoutConstructor();

        $this->setProperty($reflection, $order, 'calculator', $calculator);
        $this->setProperty($reflection, $order, 'shippingCost', 11.1);
        $this->setProperty($reflection, $order, 'processingFee', 0.0);
        $this->setProperty($reflection, $order, 'orderStateLengow', 'shipped');
        $this->setProperty($reflection, $order, 'shippedByMp', false);
        $this->setProperty($reflection, $order, 'trackingNumber', null);

        $lengowOrder = $this->createMock(LengowOrder::class);
        $lengowOrder->method('getStateMachineStateByOrderState')->willReturn(null);
        $this->setProperty($reflection, $order, 'lengowOrder', $lengowOrder);

        return $order;
    }

    private function createOrderData(string $taxState): array
    {
        return [
            'price' => $this->createCartPrice($taxState),
            'shippingCosts' => $this->createCalculatedPrice(11.1, $this->createTaxRules()),
            'deliveries' => [[]],
        ];
    }

    private function createCartPrice(string $taxState): CartPrice
    {
        return new CartPrice(
            47.54,
            47.54,
            47.54,
            new CalculatedTaxCollection(),
            new TaxRuleCollection(),
            $taxState
        );
    }

    private function createCalculatedPrice(float $price, TaxRuleCollection $taxRules): CalculatedPrice
    {
        return new CalculatedPrice($price, $price, new CalculatedTaxCollection(), $taxRules);
    }

    private function createTaxRules(): TaxRuleCollection
    {
        return new TaxRuleCollection([new TaxRule(19.0)]);
    }

    private function invokeChangeShippingCosts(LengowImportOrder $order, array $orderData): array
    {
        $method = new ReflectionMethod(LengowImportOrder::class, 'changeShippingCosts');
        $method->setAccessible(true);

        /** @var SalesChannelContext $salesChannelContext */
        $salesChannelContext = (new ReflectionClass(SalesChannelContext::class))->newInstanceWithoutConstructor();

        return $method->invoke($order, $orderData, $salesChannelContext);
    }

    private function setProperty(ReflectionClass $reflection, LengowImportOrder $order, string $name, mixed $value): void
    {
        $property = $reflection->getProperty($name);
        $property->setAccessible(true);
        $property->setValue($order, $value);
    }
}
