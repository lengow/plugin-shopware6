<?php declare(strict_types=1);

namespace Lengow\Connector\tests;

use Lengow\Connector\Exception\LengowException;
use Lengow\Connector\Service\LengowImportOrder;
use Lengow\Connector\Service\LengowLog;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionMethod;
use ReflectionProperty;
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

    private function setProperty(ReflectionClass $reflection, LengowImportOrder $order, string $name, mixed $value): void
    {
        $property = $reflection->getProperty($name);
        $property->setAccessible(true);
        $property->setValue($order, $value);
    }
}
