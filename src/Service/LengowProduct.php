<?php declare(strict_types=1);

namespace Lengow\Connector\Service;

use Shopware\Core\Content\Product\Aggregate\ProductMedia\ProductMediaEntity;
use Shopware\Core\Content\Product\Aggregate\ProductTranslation\ProductTranslationEntity;
use Shopware\Core\Content\Product\ProductCollection;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Content\Property\Aggregate\PropertyGroupOption\PropertyGroupOptionCollection;
use Shopware\Core\Content\Property\Aggregate\PropertyGroupOption\PropertyGroupOptionEntity;
use Shopware\Core\Content\Property\PropertyGroupEntity;
use Shopware\Core\Content\Seo\SeoUrl\SeoUrlEntity;
use Shopware\Core\Checkout\Shipping\Aggregate\ShippingMethodPrice\ShippingMethodPriceEntity;
use Shopware\Core\Checkout\Shipping\ShippingMethodEntity;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Pricing\Price;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\MultiFilter;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\Currency\CurrencyEntity;
use Shopware\Core\System\Language\LanguageEntity;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;
use Lengow\Connector\Util\EnvironmentInfoProvider;
use Lengow\Connector\Util\StringCleaner;

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
     * @var float shipping price range quantity min
     */
    private const SHIPPING_PRICE_RANGE_MIN = 0;

    /**
     * @var float shipping price range quantity max
     */
    private const SHIPPING_PRICE_RANGE_MAX = 10000000000;

    /**
     * @var string Accepted mime type for product images
     */
    private const PRODUCT_IMAGE_MIME_TYPE = 'image/jpeg';

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
        'description_short' => 'getMetaDescription',
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
     * @var SalesChannelEntity Shopware sales channel instance
     */
    private $salesChannel;

    /**
     * @var CurrencyEntity Shopware currency instance
     */
    private $currency;

    /**
     * @var LanguageEntity Shopware language instance
     */
    private $language;

    /**
     * @var ShippingMethodEntity Shopware shipping method instance
     */
    private $shippingMethod;

    /**
     * @var string Product type (simple, parent or child)
     */
    private $type;

    /**
     * @var ProductEntity Shopware product instance
     */
    private $product;

    /**
     * @var ProductTranslationEntity Shopware product translation instance
     */
    private $translation;

    /**
     * @var array All product option (only for child)
     */
    private $options;

    /**
     * @var array All product custom fields
     */
    private $customFields;

    /**
     * @var array All product properties
     */
    private $properties;

    /**
     * @var array All product images
     */
    private $images;

    /**
     * @var array All product prices
     */
    private $prices;

    /**
     * @var ProductEntity Shopware product instance
     */
    private static $parentProduct;

    /**
     * @var ProductTranslationEntity Shopware product translation instance
     */
    private static $parentTranslation;

    /**
     * @var array All product parent custom fields
     */
    private static $parentCustomFields;

    /**
     * @var array All product parent properties
     */
    private static $parentProperties;

    /**
     * LengowProduct Construct
     *
     * @param EntityRepositoryInterface $productRepository product repository
     * @param LengowLog $lengowLog Lengow log service
     * @param EnvironmentInfoProvider $environmentInfoProvider lengow environmentInfoProvider
     */
    public function __construct(
        EntityRepositoryInterface $productRepository,
        LengowLog $lengowLog,
        EnvironmentInfoProvider $environmentInfoProvider
    )
    {
        $this->productRepository = $productRepository;
        $this->lengowLog = $lengowLog;
        $this->environmentInfoProvider = $environmentInfoProvider;
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
            $productData['price_unit'] = (float) $productData['amount'] / (float) $productData['quantity'];
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
     * Init LengowProduct class
     *
     * @param array $params optional options
     */
    public function init(array $params = []): void
    {
        $this->salesChannel = $params['sales_channel'];
        $this->language = $params['language'];
        $this->currency = $params['currency'];
        $this->shippingMethod = $params['shipping_method'];
    }

    /**
     * Load a new product
     *
     * @param string $productId Shopware product id
     * @param string $productType Product type (simple, parent or child)
     */
    public function load(string $productId, string $productType): void
    {
        $this->type = $productType;
        $this->product = $this->getProductForExportById($productId);
        $this->translation = $this->getProductTranslation($this->product);
        $this->options = $this->getOptionsData();
        $this->customFields = $this->getCustomFieldData($this->product);
        $this->properties = $this->getPropertiesData($this->product);
        if ($this->product && $this->product->getParentId()) {
            if (!self::$parentProduct || self::$parentProduct->getId() !== $this->product->getParentId()) {
                self::$parentProduct = $this->getProductForExportById($this->product->getParentId());
                self::$parentTranslation = $this->getProductTranslation(self::$parentProduct);
                self::$parentCustomFields = $this->getCustomFieldData(self::$parentProduct);
                self::$parentProperties = $this->getPropertiesData(self::$parentProduct);
            }
        } else {
            self::$parentProduct = null;
            self::$parentTranslation = null;
            self::$parentCustomFields = null;
            self::$parentProperties = null;
        }
        $this->images = $this->getImages();
        $this->prices = $this->getPrices();
    }

    /**
     * Get all exportable data from a product
     *
     * @param array $headerFields the headers fields
     *
     * @return array
     */
    public function getData(array $headerFields): array
    {
        $productData = [];
        if (!$this->product) {
            return $productData;
        }
        foreach ($headerFields as $headerField) {
            switch ($headerField) {
                case LengowExport::$defaultFields['id']:
                    $productData[$headerField] = $this->getProductIdentifier();
                    break;
                case LengowExport::$defaultFields['name']:
                case LengowExport::$defaultFields['description_short']:
                case LengowExport::$defaultFields['meta_title']:
                case LengowExport::$defaultFields['meta_keyword']:
                    $productData[$headerField] = $this->getTranslatedField($headerField);
                    break;
                case LengowExport::$defaultFields['description']:
                    $productData[$headerField] = $this->getDescription();
                    break;
                case LengowExport::$defaultFields['description_html']:
                    $productData[$headerField] = $this->getDescription(false);
                    break;
                case LengowExport::$defaultFields['supplier']:
                    $productData[$headerField] = $this->getManufacturer();
                    break;
                case LengowExport::$defaultFields['url']:
                    $productData[$headerField] = $this->getProductUrl();
                    break;
                case LengowExport::$defaultFields['sku']:
                case LengowExport::$defaultFields['sku_supplier']:
                case LengowExport::$defaultFields['ean']:
                case LengowExport::$defaultFields['quantity']:
                case LengowExport::$defaultFields['parent_id']:
                case LengowExport::$defaultFields['minimal_quantity']:
                    $productData[$headerField] = $this->getUntranslatedField($headerField);
                    break;
                case LengowExport::$defaultFields['status']:
                    // get parent status > bug on child status which is null instead of boolean > issue created on Shopware's Github
                    $value = self::$parentProduct ? self::$parentProduct->getActive() : $this->product->getActive();
                    $productData[$headerField] = (string) $value === '1' ? 'Enabled' : 'Disabled';
                    break;
                case LengowExport::$defaultFields['weight']:
                case LengowExport::$defaultFields['width']:
                case LengowExport::$defaultFields['height']:
                case LengowExport::$defaultFields['length']:
                    $productData[$headerField] = $this->getSizeField($headerField);
                    break;
                case LengowExport::$defaultFields['size_unit']:
                    $productData[$headerField] = self::PRODUCT_SIZE_UNIT;
                    break;
                case LengowExport::$defaultFields['weight_unit']:
                    $productData[$headerField] = self::PRODUCT_WEIGHT_UNIT;
                    break;
                case LengowExport::$defaultFields['category']:
                    $productData[$headerField] = $this->getProductCategory($this->product, self::$parentProduct);
                    break;
                case LengowExport::$defaultFields['price_excl_tax']:
                case LengowExport::$defaultFields['price_incl_tax']:
                case LengowExport::$defaultFields['price_before_discount_excl_tax']:
                case LengowExport::$defaultFields['price_before_discount_incl_tax']:
                case LengowExport::$defaultFields['discount_amount']:
                case LengowExport::$defaultFields['discount_percent']:
                case LengowExport::$defaultFields['discount_start_date']:
                case LengowExport::$defaultFields['discount_end_date']:
                    $productData[$headerField] = $this->prices[$headerField];
                    break;
                case LengowExport::$defaultFields['currency']:
                    $productData[$headerField] = $this->currency->getIsoCode();
                    break;
                case LengowExport::$defaultFields['shipping_method']:
                    $productData[$headerField] = StringCleaner::cleanData((string) $this->shippingMethod->getName());
                    break;
                case LengowExport::$defaultFields['shipping_cost']:
                    $productData[$headerField] = $this->getShippingPrice();
                    break;
                case LengowExport::$defaultFields['shipping_delay']:
                    $productData[$headerField] = $this->shippingMethod->getDeliveryTime() !== null
                        ? $this->shippingMethod->getDeliveryTime()->getName()
                        : '';
                    break;
                case (preg_match('`image_url_([0-9]+)`', $headerField) ? true : false):
                    $productData[$headerField] = $this->images[$headerField];
                    break;
                case LengowExport::$defaultFields['type']:
                    $productData[$headerField] = $this->type;
                    break;
                case LengowExport::$defaultFields['variation']:
                    $productData[$headerField] = $this->getVariation();
                    break;
                case LengowExport::$defaultFields['language']:
                        if ($this->language->getTranslationCode() && $this->language->getTranslationCode()->getCode()) {
                            $productData[$headerField] = $this->language->getTranslationCode()->getCode();
                        } else {
                            $productData[$headerField] = '';
                        }
                    break;
                default:
                    $value = '';
                    if (strncmp($headerField, 'opt_', 4) === 0) {
                        $optionName = substr($headerField, 4);
                        $value = $this->options[$optionName] ?? '';
                    } elseif (strncmp($headerField, 'prop_', 5) === 0) {
                        $propertyName = substr($headerField, 5);
                        $value = $this->properties[$propertyName] ?? (self::$parentProperties[$propertyName] ?? '');
                    } elseif (strncmp($headerField, 'custom_', 7) === 0) {
                        $fieldName = substr($headerField, 7);
                        $value = $this->customFields[$fieldName] ?? (self::$parentCustomFields[$fieldName] ?? '');
                    }
                    $productData[$headerField] = StringCleaner::cleanData((string) $value);
            }
        }
        unset($product);
        return $productData;
    }

    /**
     * Get product to export with right association
     *
     * @param string $productId
     *
     * @return ProductEntity|null
     */
    private function getProductForExportById(string $productId) : ?ProductEntity
    {
        $context = Context::createDefaultContext();
        $productCriteria = new Criteria();
        $productCriteria->setIds([$productId])
            ->addAssociation('mainCategories')
            ->addAssociation('mainCategories.category')
            ->addAssociation('categories')
            ->addAssociation('seoUrls')
            ->addAssociation('configuratorSettings')
            ->addAssociation('configuratorSettings.option')
            ->addAssociation('configuratorSettings.option.group')
            ->addAssociation('prices')
            ->addAssociation('properties')
            ->addAssociation('properties.group')
            ->addAssociation('properties.translations')
            ->addAssociation('manufacturer')
            ->addAssociation('media')
            ->addAssociation('productMedia.media')
            ->addAssociation('options')
            ->addAssociation('options.group')
            ->addAssociation('options.translations')
            ->addAssociation('translations')
            ->addAssociation('customFields');
        $productCollection = $this->productRepository->search($productCriteria, $context)->getEntities();
        return $productCollection->count() > 0 ? $productCollection->first() : null;
    }

    /**
     * Get product translation for a specific language
     *
     * @param ProductEntity $product Shopware product instance
     *
     * @return ProductTranslationEntity|null
     */
    private function getProductTranslation(ProductEntity $product) : ?ProductTranslationEntity
    {
        $productTranslation = null;
        if ($this->language && $product && $product->getTranslations()) {
            $productTranslation = $product->getTranslations()->filterByLanguageId($this->language->getId())->first();
        }
        return $productTranslation;
    }

    /**
     * Get product id ex 750 for parent and 750_101,750_102... for children
     *
     * @return string
     */
    private function getProductIdentifier() : string
    {
        if (self::$parentProduct) {
            return self::$parentProduct->getId() . '_' . $this->product->getId();
        }
        return $this->product->getId();
    }

    /**
     * Construct product url
     *
     * @return string
     */
    private function getProductUrl() : string
    {
        $seoUrls = [];
        if ($this->product->getSeoUrls()) {
            $seoUrls = $this->product->getSeoUrls()
                ->filterBySalesChannelId($this->salesChannel->getId())
                ->getElements();
        }
        $seoPathInfo = '';
        /** @var SeoUrlEntity $seoUrl */
        foreach ($seoUrls as $seoUrl) {
            $seoPathInfo = $seoUrl->getSeoPathInfo();
            if ($seoUrl->getIsCanonical()) {
                break;
            }
        }
        return $this->environmentInfoProvider->getBaseUrl() . DIRECTORY_SEPARATOR . $seoPathInfo;

    }

    /**
     * Get the product manufacturer if it exist or the parent product manufacturer
     *
     * @return string
     */
    private function getManufacturer() : string
    {
        $manufacturer = '';
        if ($this->product->getManufacturer() !== null) {
            $manufacturer = $this->product->getManufacturer()->getName();
        }
        if ($manufacturer === '' && self::$parentProduct && self::$parentProduct->getManufacturer()) {
            $manufacturer = self::$parentProduct->getManufacturer()->getName();
        }
        return StringCleaner::cleanData((string) $manufacturer);
    }

    /**
     * Get a translatable generic field
     *
     * @param string $headerField the field to retrieve
     *
     * @return string
     */
    private function getTranslatedField(string $headerField): string
    {
        $value = null;
        if ($this->translation) {
            $value = $this->translation->{self::LINK[$headerField]}();
        }
        // if the field does not contain any translation, we take the default value
        if (!$value) {
            $value = $this->product->{self::LINK[$headerField]}();
        }
        // if the field does not contain any value, we take the parent value
        if (!$value && self::$parentProduct) {
            if (self::$parentTranslation) {
                $value = self::$parentTranslation->{self::LINK[$headerField]}();
            }
            if (!$value) {
                $value = self::$parentProduct->{self::LINK[$headerField]}();
            }
        }
        return $value ? StringCleaner::cleanData((string) $value) : '';
    }

    /**
     * Get generic field that are not translated
     *
     * @param string $headerField the field to retrieve
     *
     * @return string
     */
    private function getUntranslatedField(string $headerField): string
    {
        $value = $this->product->{self::LINK[$headerField]}();
        if (!$value && self::$parentProduct) {
            $value = self::$parentProduct->{self::LINK[$headerField]}();
        }
        return (string) ($value ?? '');
    }

    /**
     * Get a field related to size and add unit of measurement
     *
     * @param string $headerField the field to retrieve
     *
     * @return string
     */
    private function getSizeField(string $headerField): string
    {
        $value = $this->getUntranslatedField($headerField);
        return $value !== '' ? $value : '0';
    }

    /**
     * Get description with or without html tag
     *
     * @param bool $cleanHtml Clean html characters or not
     *
     * @return string
     */
    private function getDescription(bool $cleanHtml = true): string
    {
        $value = $this->getTranslatedField(LengowExport::$defaultFields['description']);
        if ($cleanHtml) {
            $value = StringCleaner::cleanHtml($value);
        }
        return $value ?? '';
    }

    /**
     * Get all variation name in one string separated by ','
     *
     * @return string
     */
    private function getVariation() : string
    {
        $variation = '';
        if (self::$parentProduct === null && $this->product->getConfiguratorSettings()) {
            $groupedOptions = $this->product->getConfiguratorSettings()->getGroupedOptions();
            /** @var PropertyGroupEntity $propertyGroup */
            foreach ($groupedOptions as $propertyGroup) {
                $variation .= 'opt_' . $propertyGroup->getName() . ', ';
            }
        }
        return rtrim($variation, ', ');
    }

    /**
     * Get option data only for child product
     *
     * @return array
     */
    private function getOptionsData(): array
    {
        $options = [];
        if ($this->product && $this->product->getOptions() === null) {
            return $options;
        }
        return $this->getAllPropertiesValues($this->product->getOptions());
    }

    /**
     * Get properties data
     *
     * @param ProductEntity $product Shopware product instance
     *
     * @return array
     */
    private function getPropertiesData(ProductEntity $product): array
    {
        $properties = [];
        if ($product && $product->getProperties() === null) {
            return $properties;
        }
        return $this->getAllPropertiesValues($product->getProperties());
    }

    /**
     * Get property value when translation exist
     *
     * @param PropertyGroupOptionCollection $propertyGroupOptionCollection Shopware property group option collection
     *
     * @return array
     */
    private function getAllPropertiesValues(PropertyGroupOptionCollection $propertyGroupOptionCollection): array
    {
        $properties = [];
        if ($propertyGroupOptionCollection->count() === 0){
            return $properties;
        }
        /** @var PropertyGroupOptionEntity $propertyGroupOption */
        foreach ($propertyGroupOptionCollection as $propertyGroupOption) {
            if ($propertyGroupOption->getGroup() === null) {
                continue;
            }
            $value = null;
            $translation = null;
            if ($this->language && $propertyGroupOption->getTranslations()) {
                $translation = $propertyGroupOption->getTranslations()
                    ->filterByLanguageId($this->language->getId())
                    ->first();
                if ($translation) {
                    $value = $translation->getName();
                }
            }
            $value = $value ?? $propertyGroupOption->getName();
            if ($value) {
                $groupName = $propertyGroupOption->getGroup()->getName();
                if (isset($properties[$groupName])) {
                    $properties[$groupName] .= ', ' . $value;
                } else {
                    $properties[$groupName] = $value;
                }
            }
        }
        return $properties;
    }

    /**
     * Get a custom field data
     *
     * @param ProductEntity $product Shopware product instance
     *
     * @return array
     */
    private function getCustomFieldData(ProductEntity $product): array
    {
        $customFields = [];
        if ($product && $product->getCustomFields() === null) {
            return $customFields;
        }
        $translationCustomFields = [];
        if ($this->language && $product->getTranslations()) {
            /** @var ProductTranslationEntity $translation */
            $translation = $product->getTranslations()
                ->filterByLanguageId($this->language->getId())
                ->first();
            if ($translation) {
                $translationCustomFields = $translation->getCustomFields() ?? [];
            }
        }
        $defaultCustomFields = $product->getCustomFields();
        if (!empty($defaultCustomFields) && empty($translationCustomFields)) {
            $customFields = $defaultCustomFields;
        } elseif (!empty($defaultCustomFields) && !empty($translationCustomFields)) {
            $customFields = array_merge($defaultCustomFields, $translationCustomFields);
        }
        return $customFields;
    }

    /**
     * Get images for a product
     *
     * @return array
     */
    private function getImages(): array
    {
        $urls = [];
        $imageUrls = [];
        $coverUrl = null;
        // get variation or parent images
        $productMedia = $this->product->getMedia();
        $coverId = $this->product->getCoverId();
        if (self::$parentProduct && ($productMedia === null || $productMedia->count() === 0)) {
            $productMedia = self::$parentProduct->getMedia();
            $coverId = self::$parentProduct->getCoverId();
        }
        // get all product image urls
        if ($productMedia && $productMedia->count() > 0) {
            $productMediaIterator = array ($productMedia->getIterator());
            uasort($productMediaIterator, function (ProductMediaEntity $a, ProductMediaEntity $b): int {
                return ($a->getPosition() < $b->getPosition()) ? -1 : 1;
            });
            /** @var ProductMediaEntity $media */
            foreach ($productMedia as $media) {
                if ($media->getMedia()) {
                    if ($coverId && $media->getId() === $coverId) {
                        $coverUrl = $media->getMedia()->getUrl();
                    } elseif ($media->getMedia()->getMimeType() === self::PRODUCT_IMAGE_MIME_TYPE) {
                        $urls[] = $media->getMedia()->getUrl();
                    }
                }
            }
        }
        // get cover image url to always put it first
        if ($coverUrl) {
            array_unshift($urls, $coverUrl);
        }
        // retrieves up to 10 images per product
        for ($i = 1; $i < 11; $i++) {
            $imageUrls['image_url_' . $i] = $urls[$i - 1] ?? '';
        }
        return $imageUrls;
    }

    /**
     * Get prices for a product
     *
     * @return array
     */
    private function getPrices(): array
    {
        $price = $this->product->getCurrencyPrice($this->currency->getId());
        if (self::$parentProduct && $price === null) {
            $price = self::$parentProduct->getCurrencyPrice($this->currency->getId());
        }
        // get original price before discount
        // the list price greater than the price indicates that the product is in reduction
        $listPrice = $price->getListPrice();
        if ($listPrice && $listPrice->getNet() > $price->getNet()) {
            $priceExclTax = $listPrice->getNet();
            $priceInclTax = $listPrice->getGross();
        } else {
            $priceExclTax = $price->getNet();
            $priceInclTax = $price->getGross();
        }
        // get price with discount
        $discountPriceExclTax = $price->getNet();
        $discountPriceInclTax = $price->getGross();
        $discountAmount = $priceInclTax - $discountPriceInclTax;
        $discountPercent = $discountAmount > 0 ? round((($discountAmount * 100) / $priceInclTax), 2) : 0;
        return [
            'price_excl_tax' => round($discountPriceExclTax, 2),
            'price_incl_tax' => round($discountPriceInclTax, 2),
            'price_before_discount_excl_tax' => round($priceExclTax, 2),
            'price_before_discount_incl_tax' => round($priceInclTax, 2),
            'discount_amount' => $discountAmount,
            'discount_percent' => $discountPercent,
            'discount_start_date' => '',
            'discount_end_date' => '',
        ];
    }

    /**
     * Get Shipping price by currency from shipping method
     *
     * @return float
     */
    private function getShippingPrice(): float
    {
        // check is free shipping option is set
        if ($this->product->getShippingFree()
            || ($this->product->getShippingFree() === null
                && self::$parentProduct
                && self::$parentProduct->getShippingFree()
            )
        ) {
            return 0.00;
        }
        // get product price if the shipping method is based on the cart price
        $price = $this->prices['price_incl_tax'];
        // get product weight if the shipping method is based on the product weight
        $weight = null;
        if ($this->product->getWeight()) {
            $weight = $this->product->getWeight();
        } elseif (self::$parentProduct && self::$parentProduct->getWeight()) {
            $weight = self::$parentProduct->getWeight();
        }
        // get product volume if the shipping method is based on the product volume
        $volume = null;
        if ($this->product->getHeight()) {
            $volume = $this->product->getHeight() * $this->product->getLength() * $this->product->getWidth();
        } elseif (self::$parentProduct
            && self::$parentProduct->getWeight()
            && self::$parentProduct->getLength()
            && self::$parentProduct->getWidth()
        ) {
            $volume = self::$parentProduct->getHeight()
                * self::$parentProduct->getLength()
                * self::$parentProduct->getWidth();
        }
        // recovery of shipping costs if one and only one rule has priority
        $shippingCost = $this->getAppliedShippingRule($price, $weight, $volume);
        // recovery of the first shipping costs available by default
        if ($shippingCost === null && $this->shippingMethod->getPrices()->count() > 0) {
            $shippingCost = $this->getCurrencyPriceFromShippingCost($this->shippingMethod->getPrices()->first());
        }
        return $shippingCost ?? 0.00;
    }

    /**
     * Get applied shipping rule and return shippingCosts
     *
     * @param float|null $price product price
     * @param float|null $weight product weight
     * @param float|null $volume product volume
     *
     * @return float|null
     */
    private function getAppliedShippingRule(float $price = null, float $weight = null, float $volume = null): ?float
    {
        $costsByPriority = [];
        if ($this->shippingMethod->getPrices()->count() > 0) {
            /** @var ShippingMethodPriceEntity $shippingPrices */
            foreach ($this->shippingMethod->getPrices() as $shippingPrices) {
                if ($price
                    && $shippingPrices->getCalculation() === 2 // shipping method is based on the cart price
                    && $shippingPrices->getRule()
                    && $this->inShippingPriceRange($shippingPrices, $price)
                ) {
                    $costsByPriority[$shippingPrices->getRule()->getPriority()][] = $shippingPrices;
                }
                if ($weight
                    && $shippingPrices->getCalculation() === 3 // shipping method is based on the product weight
                    && $shippingPrices->getRule()
                    && $this->inShippingPriceRange($shippingPrices, $weight)
                ) {
                    $costsByPriority[$shippingPrices->getRule()->getPriority()][] = $shippingPrices;
                }
                if ($volume
                    && $shippingPrices->getCalculation() === 4 // shipping method is based on the product volume
                    && $shippingPrices->getRule()
                    && $this->inShippingPriceRange($shippingPrices, $volume)
                ) {
                    $costsByPriority[$shippingPrices->getRule()->getPriority()][] = $shippingPrices;
                }
            }
        }
        ksort($costsByPriority);
        if (empty($costsByPriority)) {
            return null;
        }
        $firstPriority = current($costsByPriority);
        if (count($firstPriority) === 1) {
            return $this->getCurrencyPriceFromShippingCost(current($firstPriority));
        }
        return null;
    }

    /**
     * Get price from shipping cost corresponding to current currency
     *
     * @param ShippingMethodPriceEntity $shippingCost
     *
     * @return float|null
     */
    private function getCurrencyPriceFromShippingCost(ShippingMethodPriceEntity $shippingCost): ?float
    {
        $currencyPrice = null;
        $defaultPrice = null;
        if ($shippingCost->getCurrencyPrice() && $shippingCost->getCurrencyPrice()->count() > 0) {
            /** @var Price $price */
            foreach ($shippingCost->getCurrencyPrice() as $price) {
                if ($price->getCurrencyId() === $this->currency->getId()) {
                    $currencyPrice = $price->getGross();
                }
                if ($price->getCurrencyId() === Defaults::CURRENCY) {
                    $defaultPrice = $price->getGross();
                }
            }
        }
        return $currencyPrice ?? $defaultPrice;
    }

    /**
     * Get if value is within shipping price range
     *
     * @param ShippingMethodPriceEntity $shippingPrice shipping price entity
     * @param float $value value to check
     *
     * @return bool
     */
    private function inShippingPriceRange(ShippingMethodPriceEntity $shippingPrice, float $value): bool
    {
        /** @var float $from */
        $from = $shippingPrice->getQuantityStart() ?? self::SHIPPING_PRICE_RANGE_MIN;
        /** @var float $to */
        $to = $shippingPrice->getQuantityEnd() ?? self::SHIPPING_PRICE_RANGE_MAX;
        return ($value >= $from && $value <= $to);
    }

    /**
     * Get a product category breadcrumb
     *
     * @param ProductEntity $product the product
     * @param ProductEntity|null $parentProduct the product
     *
     * @return string the breadcrumb
     */
    private function getProductCategory(ProductEntity $product, ProductEntity $parentProduct = null): string
    {
        $mainCategory = null;
        $breadcrumb = '';
        // get main category if exist
        if ($product->getMainCategories() && $product->getMainCategories()->count() > 0) {
            $mainCategory = $product->getMainCategories()->first()->getCategory();
        }
        // get first category by default
        if ($mainCategory === null && $product->getCategories() && $product->getCategories()->count() > 0) {
            $mainCategory = $product->getCategories()->first();
        }
        // create breadcrumb
        if ($mainCategory) {
            $breadcrumbArray = $mainCategory->getBreadcrumb();
            foreach ($breadcrumbArray as $catName) {
                $breadcrumb .= $catName;
                if (end($breadcrumbArray) !== $catName) {
                    $breadcrumb .= ' > ';
                }
            }
        }
        // if children don't have breadcrumb, recall this method to get breadcrumb from parent
        if (empty($breadcrumb) && $parentProduct) {
            return $this->getProductCategory($parentProduct);
        }
        return $breadcrumb;
    }
}
