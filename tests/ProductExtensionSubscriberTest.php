<?php declare(strict_types=1);

namespace Lengow\Connector\tests;

use Lengow\Connector\Entity\Lengow\Product\ProductEntity as LengowProductEntity;
use Lengow\Connector\EntityExtension\ExtensionStructure\ProductExtensionStructure;
use Lengow\Connector\Subscriber\ProductExtensionSubscriber;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\MockObject\Rule\InvocationOrder;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Framework\Api\Context\SystemSource;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityLoadedEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Shopware\Core\Framework\Uuid\Uuid;

class ProductExtensionSubscriberTest extends TestCase
{
    private const EXTENSION_NAME = 'activeInLengow';

    public function testEveryProductOfOneEventIsLoadedWithASingleQuery(): void
    {
        $productEntities = [$this->createProduct(), $this->createProduct(), $this->createProduct()];

        $lengowProductRepository = $this->createRepository([], self::once());

        (new ProductExtensionSubscriber($lengowProductRepository))->onProductsLoaded($this->createEvent($productEntities));

        foreach ($productEntities as $productEntity) {
            self::assertInstanceOf(
                ProductExtensionStructure::class,
                $productEntity->getExtension(self::EXTENSION_NAME)
            );
        }
    }

    public function testTheQueryAsksForEveryProductOfTheEvent(): void
    {
        $productEntities = [$this->createProduct(), $this->createProduct()];
        $expectedIds = [$productEntities[0]->getId(), $productEntities[1]->getId()];

        $criteria = null;
        $lengowProductRepository = $this->createRepository([], null, $criteria);

        (new ProductExtensionSubscriber($lengowProductRepository))->onProductsLoaded($this->createEvent($productEntities));

        self::assertInstanceOf(Criteria::class, $criteria);
        $filters = $criteria->getFilters();
        self::assertCount(1, $filters);
        self::assertInstanceOf(EqualsAnyFilter::class, $filters[0]);
        self::assertSame('productId', $filters[0]->getField());
        self::assertSame($expectedIds, $filters[0]->getValue());
    }

    public function testEveryProductOnlyGetsItsOwnSalesChannels(): void
    {
        $firstProduct = $this->createProduct();
        $secondProduct = $this->createProduct();
        $firstSalesChannelId = Uuid::randomHex();
        $secondSalesChannelId = Uuid::randomHex();

        $lengowProductRepository = $this->createRepository([
            $this->createLengowProduct($firstProduct->getId(), $firstSalesChannelId),
            $this->createLengowProduct($secondProduct->getId(), $firstSalesChannelId),
            $this->createLengowProduct($secondProduct->getId(), $secondSalesChannelId),
        ]);

        (new ProductExtensionSubscriber($lengowProductRepository))
            ->onProductsLoaded($this->createEvent([$firstProduct, $secondProduct]));

        self::assertSame([$firstSalesChannelId => true], $this->getExtension($firstProduct)->activeArray);
        self::assertSame(
            [$firstSalesChannelId => true, $secondSalesChannelId => true],
            $this->getExtension($secondProduct)->activeArray
        );
    }

    public function testAProductWithoutLengowProductGetsAnEmptyExtension(): void
    {
        $productEntity = $this->createProduct();

        $lengowProductRepository = $this->createRepository([
            $this->createLengowProduct(Uuid::randomHex(), Uuid::randomHex()),
        ]);

        (new ProductExtensionSubscriber($lengowProductRepository))->onProductsLoaded($this->createEvent([$productEntity]));

        self::assertFalse($this->getExtension($productEntity)->active);
        self::assertSame([], $this->getExtension($productEntity)->activeArray);
    }

    public function testAnExistingExtensionIsNotReplaced(): void
    {
        $productEntity = $this->createProduct();
        $extension = new ProductExtensionStructure();
        $productEntity->addExtension(self::EXTENSION_NAME, $extension);

        $lengowProductRepository = $this->createRepository([], self::never());

        (new ProductExtensionSubscriber($lengowProductRepository))->onProductsLoaded($this->createEvent([$productEntity]));

        self::assertSame($extension, $productEntity->getExtension(self::EXTENSION_NAME));
    }

    public function testAnEventWithoutProductsRunsNoQuery(): void
    {
        $lengowProductRepository = $this->createRepository([], self::never());

        (new ProductExtensionSubscriber($lengowProductRepository))->onProductsLoaded($this->createEvent([]));
    }

    /**
     * @param array $lengowProducts Lengow products the repository answers with
     * @param InvocationOrder|null $expectedSearches expected number of searches
     * @param Criteria|null $usedCriteria criteria the subscriber searched with
     *
     * @return EntityRepository|MockObject
     */
    private function createRepository(
        array $lengowProducts,
        ?InvocationOrder $expectedSearches = null,
        ?Criteria &$usedCriteria = null
    ) {
        $lengowProductRepository = $this->createMock(EntityRepository::class);
        $lengowProductRepository->expects($expectedSearches ?? self::any())
            ->method('search')
            ->willReturnCallback(
                static function (Criteria $criteria, Context $context) use (
                    $lengowProducts,
                    &$usedCriteria
                ): EntitySearchResult {
                    $usedCriteria = $criteria;

                    return new EntitySearchResult(
                        'lengow_product',
                        count($lengowProducts),
                        new EntityCollection($lengowProducts),
                        null,
                        $criteria,
                        $context
                    );
                }
            );

        return $lengowProductRepository;
    }

    /**
     * @param array $productEntities products of the event
     *
     * @return EntityLoadedEvent
     */
    private function createEvent(array $productEntities): EntityLoadedEvent
    {
        return new EntityLoadedEvent(new ProductDefinition(), $productEntities, new Context(new SystemSource()));
    }

    /**
     * @return ProductEntity
     */
    private function createProduct(): ProductEntity
    {
        $productEntity = new ProductEntity();
        $productEntity->setId(Uuid::randomHex());

        return $productEntity;
    }

    /**
     * @param string $productId Shopware product id
     * @param string $salesChannelId sales channel id
     *
     * @return LengowProductEntity
     */
    private function createLengowProduct(string $productId, string $salesChannelId): LengowProductEntity
    {
        $lengowProduct = new LengowProductEntity();
        $lengowProduct->setUniqueIdentifier(Uuid::randomHex());
        $lengowProduct->setProductId($productId);
        $lengowProduct->assign(['salesChannelId' => $salesChannelId]);

        return $lengowProduct;
    }

    /**
     * @param ProductEntity $productEntity product carrying the extension
     *
     * @return ProductExtensionStructure
     */
    private function getExtension(ProductEntity $productEntity): ProductExtensionStructure
    {
        $extension = $productEntity->getExtension(self::EXTENSION_NAME);
        self::assertInstanceOf(ProductExtensionStructure::class, $extension);

        return $extension;
    }
}
