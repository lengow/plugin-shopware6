<?php declare(strict_types=1);

namespace Lengow\Connector\Service;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Content\Product\ProductCollection;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\MultiFilter;
use Shopware\Core\Framework\Uuid\Uuid;
use Lengow\Connector\Exception\LengowException;

/**
 * Class LengowProduct
 * @package Lengow\Connector\Service
 */
class LengowProduct
{
    /**
     * @var string name of field id
     */
    private const FIELD_ID = 'id';

    /**
     * @var string name of field product number
     */
    private const FIELD_PRODUCT_NUMBER = 'productNumber';

    /**
     * @var string name of field ean
     */
    private const FIELD_EAN = 'ean';

    /**
     * @var string name of field manufacturer_number
     */
    private const FIELD_MANUFACTURER_NUMBER = 'manufacturerNumber';

    /**
     * @var EntityRepositoryInterface product repository
     */
    private $productRepository;

    /**
     * @var LengowLog Lengow log service
     */
    private $lengowLog;

    /**
     * @var array API nodes containing relevant data
     */
    private $productApiNodes = [
        'marketplace_product_id',
        'marketplace_status',
        'merchant_product_id',
        'marketplace_order_line_id',
        'quantity',
        'amount',
    ];

    /**
     * @var array list of fields for advanced search
     */
    private $advancedSearchFields = [
        self::FIELD_PRODUCT_NUMBER,
        self::FIELD_EAN,
        self::FIELD_MANUFACTURER_NUMBER,
    ];

    /**
     * LengowProduct Construct
     *
     * @param EntityRepositoryInterface $productRepository product repository
     * @param LengowLog $lengowLog Lengow log service
     *
     */
    public function __construct(EntityRepositoryInterface $productRepository, LengowLog $lengowLog)
    {
        $this->productRepository = $productRepository;
        $this->lengowLog = $lengowLog;
    }

    /**
     * Extract cart data from API
     *
     * @param object $api product data
     *
     * @return array
     */
    public function extractProductDataFromAPI(object $api): array
    {
        $productData = [];
        foreach ($this->productApiNodes as $node) {
            if (isset($api->{$node})) {
                $productData[$node] = $api->{$node};
            }
        }
        if (isset($productData['amount']) && isset($productData['quantity'])) {
            $productData['price_unit'] = (float)$productData['amount'] / (float)$productData['quantity'];
        } else {
            $productData['price_unit'] = 0;
        }
        return $productData;
    }

    /**
     * Search product with classic search and advanced search
     *
     * @param string $attributeName name of field for search (merchant_product_id or marketplace_product_id)
     * @param string $attributeValue value for search
     * @param bool $logOutput see log or not
     * @param string|null $marketplaceSku Lengow id of current order
     *
     * @return ProductEntity|null
     */
    public function searchProduct(
        string $attributeName,
        string $attributeValue,
        bool $logOutput = false,
        string $marketplaceSku = null
    ): ?ProductEntity
    {
        // remove _FBA from product id
        $attributeValue = preg_replace('/_FBA$/', '', $attributeValue);
        if (empty($attributeValue)) {
            return null;
        }
        // classic search by product id
        $searchField = self::FIELD_ID;
        $product = $this->getProductByField($attributeValue, $searchField);
        if ($product === null) {
            $this->lengowLog->write(
                LengowLog::CODE_IMPORT,
                $this->lengowLog->encodeMessage('log.import.product_advanced_search', [
                    'attribute_name' => $attributeName,
                    'attribute_value' => $attributeValue,
                ]),
                $logOutput,
                $marketplaceSku
            );
            // advanced search by product number, ean and manufacturer_number
            foreach ($this->advancedSearchFields as $field) {
                $searchField = $field;
                $product = $this->getProductByField($attributeValue, $searchField);
                if ($product !== null) {
                    break;
                }
            }
        }
        if ($product) {
            $this->lengowLog->write(
                LengowLog::CODE_IMPORT,
                $this->lengowLog->encodeMessage('log.import.product_be_found', [
                    'product_number' => $product->getProductNumber(),
                    'product_id' => $product->getId(),
                    'attribute_name' => $attributeName,
                    'attribute_value' => $attributeValue,
                    'search_field' => $searchField,
                ]),
                $logOutput,
                $marketplaceSku
            );
        }
        return $product;
    }

    /**
     * Get a product with a specific field
     *
     * @param string $attributeValue value for search
     * @param string $field id to search product (id, number, ean or manufacturer_number)
     *
     * @return ProductEntity|null
     */
    public function getProductByField(string $attributeValue, string $field): ?ProductEntity
    {
        $context = Context::createDefaultContext();
        $criteria = new Criteria();
        if ($field === self::FIELD_ID) {
            $attributeValue = str_replace('\_', '_', $attributeValue);
            $attributeValue = str_replace('X', '_', $attributeValue);
            $ids = explode('_', $attributeValue);
            if ($ids[0] !== null && strlen($ids[0]) === 32 && Uuid::isValid($ids[0]) && count($ids) < 3) {
                if (isset($ids[1]) && strlen($ids[1]) === 32 && Uuid::isValid($ids[1])) {
                    $criteria->addFilter(new MultiFilter(MultiFilter::CONNECTION_AND, [
                        new EqualsFilter('id', $ids[1]),
                        new EqualsFilter('parentId', $ids[0]),
                    ]));
                } else {
                    $criteria->setIds([$ids[0]]);
                }
            } else {
                return null;
            }
        } else {
            $criteria->addFilter(new EqualsFilter($field, $attributeValue));
        }
        /** @var ProductCollection $productCollection */
        $productCollection = $this->productRepository->search($criteria, $context)->getEntities();
        return $productCollection->count() !== 0 ? $productCollection->first() : null;
    }

}
