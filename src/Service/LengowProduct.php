<?php declare(strict_types=1);

namespace Lengow\Connector\Service;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Content\Product\ProductCollection;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\System\Currency\CurrencyEntity;
use Shopware\Core\System\Language\LanguageEntity;
use Shopware\Core\Checkout\Shipping\ShippingMethodEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\MultiFilter;
use Shopware\Core\Checkout\Shipping\Aggregate\ShippingMethodPrice\ShippingMethodPriceEntity;
use Shopware\Core\Framework\Uuid\Uuid;
use Lengow\Connector\Util\EnvironmentInfoProvider;

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
     * @var string measurement unit for all size field of a product
     */
    private const PRODUCT_WEIGHT_UNIT = 'kg';

    /**
     * @var string measurement unit for all weight field of a product
     */
    private const PRODUCT_SIZE_UNIT = 'mm';

    /**
     * @var string shipping price range quantity min
     */
    private const SHIPPING_PRICE_RANGE_MIN = 0;

    /**
     * @var string shipping price range quantity max
     */
    private const SHIPPING_PRICE_RANGE_MAX = 100000;

    /**
     * Callback for shopware ProductEntity getter
     */
    private const LINK = [
        'id' => 'getId',
        'sku' => 'getProductNumber',
        'sku_supplier' => 'getManufacturerNumber',
        'ean' => 'getEan',
        'name' => 'getName',
        'quantity' => 'getAvailableStock',
        'weight' => 'getWeight',
        'width' => 'getWidth',
        'height' => 'getHeight',
        'length' => 'getLength',
        'parent_id' => 'getParentId',
        'status' => 'getActive',
        'description' => 'getDescription',
        'description_html' => 'getDescription',
        'meta_title' => 'getMetaTitle',
        'minimal_quantity' => 'getMinPurchase',
        'meta_keyword' => 'getKeywords',
        'url' => 'getSeoUrls',
    ];

    /**
     * @var EntityRepositoryInterface product repository
     */
    private $productRepository;

    /**
     * @var EntityRepositoryInterface product repository
     */
    private $propertyGroupRepository;

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
     * @var EnvironmentInfoProvider Lengow environment info provider
     */
    private $environmentInfoProvider;

    /**
     * LengowProduct Construct
     *
     * @param EntityRepositoryInterface $productRepository product repository
     * @param LengowLog $lengowLog Lengow log service
     * @param EnvironmentInfoProvider $environmentInfoProvider lengow environmentInfoProvider
     * @param EntityRepositoryInterface $propertyGroupRepository shopware sales channel context factory
     */
    public function __construct(
        EntityRepositoryInterface $productRepository,
        LengowLog $lengowLog,
        EnvironmentInfoProvider $environmentInfoProvider,
        EntityRepositoryInterface $propertyGroupRepository
    )
    {
        $this->productRepository = $productRepository;
        $this->lengowLog = $lengowLog;
        $this->environmentInfoProvider = $environmentInfoProvider;
        $this->propertyGroupRepository = $propertyGroupRepository;
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
            $productData[$node] = $api->{$node};
        }
        if (isset($productData['amount'], $productData['quantity'])) {
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
            $attributeValue = str_replace(['\_', 'X'], ['_', '_'], $attributeValue);
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

    /**
     * Get product to export with right association
     *
     * @param string $productId
     *
     * @return ProductEntity|null
     */
    public function getProductForExportById(string $productId) : ?ProductEntity
    {
        $productCriteria = new Criteria();
        $productCriteria->setIds([$productId])
            ->addAssociation('categories')
            ->addAssociation('prices')
            ->addAssociation('properties')
            ->addAssociation('manufacturer')
            ->addAssociation('media')
            ->addAssociation('productMedia.media')
            ->addAssociation('options')
            ->addAssociation('customFields');
        $productCollection = $this->productRepository->search($productCriteria, Context::createDefaultContext())->getEntities();
        if ($productCollection->count() < 0) {
            return null;
        }
        return $productCollection->first();
    }

    /**
     * Get all exportable data from a product
     *
     * @param string $productId the product Id
     * @param array $headerFields the headers fields
     * @param CurrencyEntity $currency currency to ise
     * @param ShippingMethodEntity $shipping shipping method to use
     * @param LanguageEntity|null $language language to use
     *
     * @return array
     */
    public function getData(
        string $productId,
        array $headerFields,
        CurrencyEntity $currency,
        ShippingMethodEntity $shipping,
        LanguageEntity $language = null
    ): array {
        $productData = [];
        $isChild = false;
        static $parent = null;
        $product = $this->getProductForExportById($productId);
        // if product is not found
        if (!$product) {
            return $productData;
        }
        // Don't reload parent if it's same from previous item
        if ($product->getParentId()) {
            if (!$parent || $parent->getId() !== $product->getParentId()) {
                $parent = $this->getProductForExportById($product->getParentId());
            }
            // if product is child and parent is not found
            if (!$parent) {
                return $productData;
            }
            $isChild = true;
        } else {
            $parent = null;
        }
        foreach ($headerFields as $headerField) {
            switch ($headerField) {
                case LengowExport::$defaultFields['id']:
                    $productData[$headerField] = $this->getProductIdentifier($product, $isChild);
                    break;
                case LengowExport::$defaultFields['name']:
                    $productData[$headerField] = $this->getTranslatedField(
                        'name',
                        $product,
                        $headerField,
                        $isChild,
                        $parent
                    );
                    break;
                case LengowExport::$defaultFields['description']:
                    $productData[$headerField] = $this->getDescription($product, $headerField, $isChild, $parent);
                    break;
                case LengowExport::$defaultFields['description_html']:
                    $productData[$headerField] = $this->getDescriptionHtml($product, $headerField, $isChild, $parent);
                    break;
                case LengowExport::$defaultFields['meta_title']:
                    $productData[$headerField] = $this->getTranslatedField(
                        'metaTitle',
                        $product,
                        $headerField,
                        $isChild,
                        $parent
                    );
                    break;
                case LengowExport::$defaultFields['meta_keyword']:
                    $productData[$headerField] = $this->getTranslatedField(
                        'keywords',
                        $product,
                        $headerField,
                        $isChild,
                        $parent
                    );
                    break;
                case LengowExport::$defaultFields['supplier']:
                    $productData[$headerField] = $this->getManufacturer($product, $isChild);
                    break;
                case LengowExport::$defaultFields['url']:
                    $productData[$headerField] = $this->getProductUrl(
                        $product,
                        $this->getTranslatedField(
                            'name',
                            $product,
                            LengowExport::$defaultFields['name'],
                            $isChild,
                            $parent
                        )
                    );
                    break;
                case LengowExport::$defaultFields['sku']:
                case LengowExport::$defaultFields['sku_supplier']:
                case LengowExport::$defaultFields['ean']:
                case LengowExport::$defaultFields['quantity']:
                case LengowExport::$defaultFields['parent_id']:
                case LengowExport::$defaultFields['status']:
                case LengowExport::$defaultFields['minimal_quantity']:
                    $productData[$headerField] = $this->getUntranslatedField($product, $headerField, $isChild, $parent);
                    break;
                case LengowExport::$defaultFields['weight']:
                case LengowExport::$defaultFields['width']:
                case LengowExport::$defaultFields['height']:
                case LengowExport::$defaultFields['length']:
                    $productData[$headerField] = $this->getSizeField($product, $headerField, $isChild, $parent);
                    break;
                case LengowExport::$defaultFields['size_unit']:
                    $productData[$headerField] = self::PRODUCT_SIZE_UNIT;
                    break;
                case LengowExport::$defaultFields['weight_unit']:
                    $productData[$headerField] = self::PRODUCT_WEIGHT_UNIT;
                    break;
                case LengowExport::$defaultFields['category']:
                    $productData[$headerField] = $this->getProductCategory($product, $isChild, $parent);
                    break;
                case LengowExport::$defaultFields['price_excl_tax']:
                case LengowExport::$defaultFields['price_incl_tax']:
                case LengowExport::$defaultFields['price_before_discount_excl_tax']:
                case LengowExport::$defaultFields['price_before_discount_incl_tax']:
                    $productData[$headerField] = $this->getCalculatedPrice(
                        $product,
                        $headerField,
                        $currency,
                        $isChild,
                        $parent
                    );
                    break;
                case LengowExport::$defaultFields['currency']:
                    $productData[$headerField] = $currency->getIsoCode();
                    break;
                case LengowExport::$defaultFields['shipping_cost']:
                    $productData[$headerField] = $this->getShippingPrice($shipping, $currency, $product);
                    break;
                case LengowExport::$defaultFields['shipping_delay']:
                    $productData[$headerField] = $shipping->getDeliveryTime()->getName();
                    break;
                // case LengowExport::$defaultFields['discount_percent']:
                //     $productData[$headerField] = '';
                //     break;
                // case LengowExport::$defaultFields['discount_start_date']:
                //     $productData[$headerField] = '';
                //     break;
                // case LengowExport::$defaultFields['discount_end_date']:
                //     $productData[$headerField] = '';
                //     break;
                case LengowExport::$defaultFields['image_url_1']:
                case LengowExport::$defaultFields['image_url_2']:
                case LengowExport::$defaultFields['image_url_3']:
                case LengowExport::$defaultFields['image_url_4']:
                case LengowExport::$defaultFields['image_url_5']:
                case LengowExport::$defaultFields['image_url_6']:
                case LengowExport::$defaultFields['image_url_7']:
                case LengowExport::$defaultFields['image_url_8']:
                case LengowExport::$defaultFields['image_url_9']:
                case LengowExport::$defaultFields['image_url_10']:
                    $productData[$headerField] = $this->getProductImgUrl($product, $headerField, $isChild, $parent);
                    break;
                case LengowExport::$defaultFields['type']:
                    $productData[$headerField] = $product->getParentId() ? 'simple' : 'parent';
                    break;
                case LengowExport::$defaultFields['variation']:
                    $productData[$headerField] = $this->getVariationPropertiesNames($product);
                    break;
                case LengowExport::$defaultFields['language']:;
                        if ($language && $language->getTranslationCode() && $language->getTranslationCode()->getCode()) {
                            $productData[$headerField] = $language->getTranslationCode()->getCode();
                        } else {
                            $productData[$headerField] = '';
                        }
                    break;
                default:
                    $productData[$headerField] = $this->getPropertiesAndCustomField($headerField, $product);
            }
        }
        unset($product);
        return $productData;
    }

    /**
     * Get product id ex 750 for parent and 750_101,750_102... for children
     *
     * @param ProductEntity $product the product
     * @param bool $isChild is the product a children
     *
     * @return string
     */
    public function getProductIdentifier(ProductEntity $product, bool $isChild) : string
    {
        if ($isChild) {
            return $product->getParentId() . '_' . $product->getId();
        }
        return $product->getId();
    }

    /**
     * Construct product url
     *
     * @param ProductEntity $product the product
     * @param string $productTranslatedName  the product translated name
     *
     * @return string
     */
    public function getProductUrl(ProductEntity $product, string $productTranslatedName) : string
    {
        $url = $this->environmentInfoProvider->getBaseUrl();
        $url .= DIRECTORY_SEPARATOR .  str_replace(' ', '-', $productTranslatedName);
        $url .= DIRECTORY_SEPARATOR . $product->getProductNumber();
        return $url;
    }

    /**
     * Get the product manufacturer if it exist or the parent product manufacturer
     *
     * @param ProductEntity $product the product
     * @param bool $isChild is the product a child
     * @param ProductEntity|null $parent the parent product
     *
     * @return string
     */
    public function getManufacturer(ProductEntity $product, bool $isChild, ProductEntity $parent = null) : string
    {
        $manufacturer = '';
        if ($product->getManufacturer()) {
            $manufacturer = $product->getManufacturer()->getName();
            if ($manufacturer === '' && $isChild && $parent->getManufacturer()) {
                $manufacturer = $parent->getManufacturer()->getName();
            }
        }
        return $manufacturer;
    }

    /**
     * Get a field related to size and add unit of measurement
     *
     * @param ProductEntity $product the product
     * @param string $headerField the field to retrieve
     * @param bool $isChild is the product a children
     * @param ProductEntity|null $parent the parent product
     *
     * @return string
     */
    public function getSizeField(
        ProductEntity $product,
        string $headerField,
        bool $isChild = false,
        ProductEntity $parent = null
    ): string {
        $value = $product->{self::LINK[$headerField]}();
        if (!$value && $parent && $isChild) {
            $value = $parent->{self::LINK[$headerField]}();
        }
        return (string) ($value ?? '0');
    }

    /**
     * Get a translatable generic field
     *
     * @param string $translatedName name of the translated field
     * @param ProductEntity $product the product
     * @param string $headerField the field to retrieve
     * @param bool $isChild is the product a children
     * @param ProductEntity|null $parent the parent product
     *
     * @return string
     */
    public function getTranslatedField(
        string $translatedName,
        ProductEntity $product,
        string $headerField,
        bool $isChild = false,
        ProductEntity $parent = null
    ): string {
        $value = $product->getTranslated()[$translatedName] ?? $product->{self::LINK[$headerField]}();
        if (!$value && $parent && $isChild) {
            $value = $parent->getTranslated()[$translatedName] ?? $parent->{self::LINK[$headerField]}();
        }
        return (string) ($value ?? '');
    }

    /**
     * Get generic field that are not translated
     *
     * @param ProductEntity $product the product
     * @param string $headerField the field to retrieve
     * @param bool $isChild is the product a children product ?
     * @param ProductEntity|null $parent the parent
     *
     * @return string
     */
    public function getUntranslatedField(
        ProductEntity $product,
        string $headerField,
        bool $isChild = false,
        ProductEntity $parent = null
    ): string {
        $value = $product->{self::LINK[$headerField]}();
        if (!$value && $parent && $isChild) {
            $value = $parent->{self::LINK[$headerField]}();
        }
        return (string) ($value ?? '');
    }

    /**
     * Get description without html tag
     *
     * @param ProductEntity $product the product
     * @param string $headerField the field to retrieve
     * @param bool $isChild is the product a children product ?
     * @param ProductEntity|null $parent the parent
     *
     * @return string
     */
    public function getDescription(
        ProductEntity $product,
        string $headerField,
        bool $isChild = false,
        ProductEntity $parent = null
    ): string {
        $value = strip_tags(
            html_entity_decode(
                $product->getTranslated()['description'] ??
                $product->{self::LINK[$headerField]}() ?? ''
            )
        );
        if (!$value && $parent && $isChild) {
            $value = strip_tags(
                html_entity_decode(
                    $parent->getTranslated()['description'] ??
                    $parent->{self::LINK[$headerField]}() ?? ''
                )
            );
        }
        return (string) ($value ?? '');
    }

    /**
     * Get description with html tag
     *
     * @param ProductEntity $product the product
     * @param string $headerField the field to retrieve
     * @param bool $isChild is the product a children product ?
     * @param ProductEntity|null $parent the parent
     *
     * @return string
     */
    public function getDescriptionHtml(
        ProductEntity $product,
        string $headerField,
        bool $isChild = false,
        ProductEntity $parent = null
    ): string {
        $value = html_entity_decode(
            $product->getTranslated()['description'] ??
            $product->{self::LINK[$headerField]}() ?? ''
        );
        if (!$value && $isChild) {
            $value = html_entity_decode(
                $parent->getTranslated()['description'] ??
                $product->{self::LINK[$headerField]}() ?? ''
            );
        }
        return (string)($value ?? '');
    }

    /**
     * Check if headerField is a property or a customField and call the according method
     *
     * @param string $headerField the field to retrieve
     * @param ProductEntity $product the product
     *
     * @return string
     */
    public function getPropertiesAndCustomField(string $headerField, ProductEntity $product) : string
    {
        if (strncmp($headerField, 'prop_', 5) === 0) {
            return $this->getPropertiesData(substr($headerField, 5), $product);
        }
        if (strncmp($headerField, 'custom_', 7) === 0) {
            return $this->getCustomFieldData(substr($headerField, 7), $product);
        }
        return '';
    }

    /**
     * Get variation properties data
     *
     * @param string $headerField the field to retrieve
     * @param ProductEntity $product the product
     *
     * @return string
     */
    public function getPropertiesData(string $headerField, ProductEntity $product) : string
    {
        if ($product->getOptions()->count() > 0) {
            foreach ($product->getOptions() as $productOption) {
                if ($productOption->getGroup() && $productOption->getGroup()->getName() === $headerField) {
                    return $productOption->getName();
                }
            }
        }
        return '';
    }

    /**
     * Get all variation properties name in one string separated by ';'
     *
     * @param ProductEntity $product the product
     *
     * @return string
     */
    public function getVariationPropertiesNames(ProductEntity $product) : string
    {
        if ($product->getParentId()) { // if product is a child, no variation name
            return '';
        }
        $propertiesGroupIds = [];
        if ($product->getProperties() && $product->getProperties()->count() > 0) {
            foreach ($product->getProperties() as $properties) {
                $propertiesGroupIds[] = $properties->getGroupId();
            }
            $propertiesGroupIds = array_unique($propertiesGroupIds);
            $propertyGroupCriteria = new Criteria();
            $propertyGroupCriteria->setIds($propertiesGroupIds);
            $result = $this->propertyGroupRepository->search($propertyGroupCriteria, Context::createDefaultContext())->getEntities();
            $variationPropertiesName = '';
            foreach ($result as $propertyGroup) {
                $variationPropertiesName .= $propertyGroup->getName();
            }
            return $variationPropertiesName;
        }
        return '';
    }

    /**
     * Get custom field data
     *
     * @param string $headerField the field to retrieve
     * @param ProductEntity $product the product
     *
     * @return string
     */
    public function getCustomFieldData(string $headerField, ProductEntity $product) :string
    {
        return (string) ($product->getTranslated()['customFields'][$headerField] ?? '');
    }

    /**
     * Get Shipping price by currency from shipping method
     *
     * @param ShippingMethodEntity $shipping the shipping method
     * @param CurrencyEntity $currency the currency
     * @param ProductEntity $product the product
     *
     * @return string
     */
    public function getShippingPrice(
        ShippingMethodEntity $shipping,
        CurrencyEntity $currency,
        ProductEntity $product
    ): string {
        $weight = $product->getWeight() ?: 1;
        $size = ($product->getHeight() ?: 1) * ($product->getLength() ?: 1) * ($product->getWidth() ?: 1);
        $price = $this->getAppliedShippingRule($shipping, $currency, $weight, $size);
        if (($price === '') && $shipping->getPrices()->count() > 0) {
            foreach ($shipping->getPrices() as $shippingPrices) {
                if ($shippingPrices->getCurrencyPrice()->count() > 0) {
                    foreach ($shippingPrices->getCurrencyPrice() as $shippingCurrencyPrice) {
                        if ($shippingCurrencyPrice->getCurrencyId() === $currency->getId()) {
                            return (string)$shippingCurrencyPrice->getNet();
                        }
                    }
                }
            }
        }
        return $price;
    }

    /**
     * Get applied shipping rule and return shippingCosts
     *
     * @param ShippingMethodEntity $shipping the shipping method
     * @param CurrencyEntity $currency the currency to use
     * @param float $weight product weight
     * @param float $size product size
     *
     * @return string
     */
    public function getAppliedShippingRule(
        ShippingMethodEntity $shipping,
        CurrencyEntity $currency,
        float $weight,
        float $size
    ): string
    {
        $weightCosts = null;
        $sizeCosts = null;
        if ($shipping->getPrices()->count() > 0) {
            foreach ($shipping->getPrices() as $shippingPrices) {
                if ($shippingPrices->getCalculation() === 3
                    && $shippingPrices->getRule()
                    && $this->inShippingPriceRange($shippingPrices, $weight))
                {
                    $weightCosts = $shippingPrices;
                }
                if ($shippingPrices->getCalculation() === 4
                    && $shippingPrices->getRule()
                    && $this->inShippingPriceRange($shippingPrices, $size))
                {
                    $sizeCosts = $shippingPrices;
                }
            }
        }
        if (!$weightCosts && !$sizeCosts) {
            return '';
        }
        $sizePriority = (!$weightCosts && $sizeCosts) || (
                $weightCosts && $sizeCosts && (
                    $weightCosts->getRule()->getPriority() < $sizeCosts->getRule()->getPriority()
                )
            );
        $weightPriority = (!$sizeCosts && $weightCosts) || (
                $weightCosts && $sizeCosts && (
                    $weightCosts->getRule()->getPriority() > $sizeCosts->getRule()->getPriority()
                )
            );
        if ($sizePriority) {
            return $this->getCurrencyPriceFromShippingCost($sizeCosts, $currency);
        }
        if ($weightPriority) {
            return $this->getCurrencyPriceFromShippingCost($weightCosts, $currency);
        }
        return '';
    }

    /**
     * Get price from shipping cost corresponding to current currency
     *
     * @param ShippingMethodPriceEntity $shippingCost
     * @param CurrencyEntity $currency
     *
     * @return string
     */
    public function getCurrencyPriceFromShippingCost(
        ShippingMethodPriceEntity $shippingCost,
        CurrencyEntity $currency
    ): string
    {
        if ($shippingCost->getCurrencyPrice()->count() > 0) {
            foreach ($shippingCost->getCurrencyPrice() as $shippingCurrencyPrice) {
                if ($shippingCurrencyPrice->getCurrencyId() === $currency->getId()) {
                    return (string)$shippingCurrencyPrice->getNet();
                }
            }
        }
        return '';
    }

    /**
     * Get if value is within shipping price range
     *
     * @param ShippingMethodPriceEntity $shippingPrice shipping price entity
     * @param float $value value to check
     *
     * @return bool
     */
    public function inShippingPriceRange(ShippingMethodPriceEntity $shippingPrice, float $value): bool
    {
        /** @var float $from */
        $from = $shippingPrice->getQuantityStart() ?? self::SHIPPING_PRICE_RANGE_MIN;
        /** @var float $to */
        $to = $shippingPrice->getQuantityEnd() ??  self::SHIPPING_PRICE_RANGE_MAX;
        return ($value >= $from && $value <= $to);
    }

    /**
     * Get a product price by currency (Net or Gross depending on the headerField)
     *
     * @param ProductEntity $product the product
     * @param string $headerField the field to retrieve
     * @param CurrencyEntity $currency the currency to use
     * @param bool $isChild product is a child
     * @param ProductEntity|null $parent the parent product
     *
     * @return string|null
     */
    public function getCalculatedPrice(
        ProductEntity $product,
        string $headerField,
        CurrencyEntity $currency,
        bool $isChild = false,
        ProductEntity $parent = null
    ): ?string
    {
        $productPrice = null;
        foreach ($product->getPrices() as $price) {
            if ($price->getPrice()->count() > 0
                && $price->getPrice()->first()->getCurrencyId() === $currency->getId()
            ) {
                $productPrice = $price->getPrice()->first();
                break;
            }
        }
        if (!$productPrice) {
            // if price not found and product is a children, get price from parent product
            if ($isChild && $parent) {
                return $this->getCalculatedPrice($parent, $headerField, $currency);
            }
            return '0';
        }
        switch ($headerField) {
            case LengowExport::$defaultFields['price_excl_tax']:
                return (string) $productPrice->getNet();
            case LengowExport::$defaultFields['price_incl_tax']:
                return (string) $productPrice->getGross();
            // case LengowExport::$defaultFields['price_before_discount_excl_tax']:
            // case LengowExport::$defaultFields['price_before_discount_incl_tax']:
            default:
                return '0';
        }
    }

    /**
     * This method retrieve image url associated to product,
     * if none could be found, the method get them from the parent if it exist
     *
     * @param ProductEntity $product the product
     * @param string $headerFields the field to retrieve
     * @param bool $isChild is the product a child of another product
     * @param ProductEntity|null $parent the parent product
     *
     * @return string
     */
    public function getProductImgUrl(
        ProductEntity $product,
        string $headerFields,
        bool $isChild = false,
        ProductEntity $parent = null
    ): string {
        $nb = (int) preg_replace("~^image_url_(\d+)$~", "$1", $headerFields);
        if (!is_numeric($nb)) {
            return '';
        }
        $nb = $nb > 0 ? $nb - 1 : $nb;
        if ($product->getMedia()->count() > $nb) {
            $productMedia = array_values($product->getMedia()->getElements())[$nb];
            if ($productMedia->getMedia()) {
                $url = $productMedia->getMedia()->getUrl();
                if ($url !== '') {
                    return $url;
                }
            }
        }
        // if children don't have image url, recall this method to get image url from parent
        if ($parent && $isChild ){
            return $this->getProductImgUrl($parent, $headerFields);
        }
        // no url found for product or parent if exist
        return '';
    }

    /**
     * Get a product category breadcrumb
     *
     * @param ProductEntity|null $product the product
     * @param ProductEntity|null $parent the parent product
     * @param bool $isChild the product is a children
     *
     * @return string the breadcrumb
     */
    public function getProductCategory(ProductEntity $product, $isChild = false, $parent = null): string
    {
        $breadcrumb = '';
        if ($product->getCategories() && $product->getCategories()->count() > 0) {
            $breadcrumbArray = $product->getCategories()->first()->getBreadcrumb();
            foreach ($breadcrumbArray as $catName) {
                $breadcrumb .= $catName;
                if (end($breadcrumbArray) !== $catName) {
                    $breadcrumb .= ' > ';
                }
            }
        }
        // if children don't have breadcrumb, recall this method to get breadcrumb from parent
        if (empty($breadcrumb) && $parent && $isChild) {
            return $this->getProductCategory($parent);
        }
        return $breadcrumb;
    }
}
