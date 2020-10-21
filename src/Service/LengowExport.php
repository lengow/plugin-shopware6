<?php declare(strict_types=1);

namespace Lengow\Connector\Service;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\System\Currency\CurrencyEntity;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Content\Product\ProductCollection;
use Shopware\Core\Checkout\Shipping\ShippingMethodEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\MultiFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\ContainsFilter;
use Shopware\Core\Framework\Uuid\Uuid;
use Lengow\Connector\Exception\LengowException;

/**
 * Class LengowExport
 * @package Lengow\Connector\Service
 */
class LengowExport
{
    /**
     * @var LengowConfiguration Lengow configuration service
     */
    private $lengowConfiguration;

    /**
     * @var EntityRepositoryInterface productRepository
     */
    private $productRepository;

    /**
     * @var EntityRepositoryInterface productRepository
     */
    private $salesChannelRepository;

    /**
     * @var EntityRepositoryInterface productRepository
     */
    private $categoryRepository;

    /**
     * @var EntityRepositoryInterface productRepository
     */
    private $lengowProductRepository;

    /*
     * @var EntityRepositoryInterface shopware currency repository
     */
    private $currencyRepository;

    /*
     * @var EntityRepositoryInterface shopware languages repository
     */
    private $languageRepository;

    /**
     * @var EntityRepositoryInterface  shopware custom field repository
     */
    private $customFieldRepository;

    /**
     * @var EntityRepositoryInterface shopware custom field set repository
     */
    private $customFieldSetRepository;

    /**
     * @var EntityRepositoryInterface shopware property group translation repository
     */
    private $propertyGroupTranslationRepository;

    /**
     * @var EntityRepositoryInterface shopware shipping method repository
     */
    private $shippingMethodRepository;

    /**
     * @var LengowProduct lengow product service
     */
    private $lengowProduct;

    /**
     * @var LengowFeed lengow feed service
     */
    private $lengowFeed;

    /**
     * @var LengowLog Lengow log service
     */
    private $lengowLog;

    /**
     * @var array default fields
     */
    public static $defaultFields = [
        'id' => 'id',
        'name' => 'name',
        'description' => 'description',
        'description_html' => 'description_html',
        'supplier' => 'supplier',
        'url' => 'url',
        'sku' => 'sku',
        'sku_supplier' => 'sku_supplier',
        'ean' => 'ean',
        'quantity' => 'quantity',
        'parent_id' => 'parent_id',
        'status' => 'status',
        'minimal_quantity' => 'minimal_quantity',
        'weight' => 'weight',
        'width' => 'width',
        'height' => 'height',
        'length' => 'length',
        'size_unit' => 'size_unit',
        'weight_unit' => 'weight_unit',
        'category' => 'category',
        'price_excl_tax' => 'price_excl_tax',
        'price_incl_tax' => 'price_incl_tax',
        'price_before_discount_excl_tax' => 'price_before_discount_excl_tax',
        'price_before_discount_incl_tax' => 'price_before_discount_incl_tax',
        'discount_percent' => 'discount_percent',
        'discount_start_date' => 'discount_start_date',
        'discount_end_date' => 'discount_end_date',
        'currency' => 'currency',
        'shipping_cost' => 'shipping_cost',
        'shipping_delay' => 'shipping_delay',
        'image_url_1' => 'image_url_1',
        'image_url_2' => 'image_url_2',
        'image_url_3' => 'image_url_3',
        'image_url_4' => 'image_url_4',
        'image_url_5' => 'image_url_5',
        'image_url_6' => 'image_url_6',
        'image_url_7' => 'image_url_7',
        'image_url_8' => 'image_url_8',
        'image_url_9' => 'image_url_9',
        'image_url_10' => 'image_url_10',
        'type' => 'type',
        'variation' => 'variation',
        'language' => 'language',
        'description_short' => 'description_short',
        'meta_title' => 'meta_title',
        'meta_keyword' => 'meta_keyword',
    ];

    /**
     * all export configuration parameters :
     * @var array [
     *  bool 'selection'
     *  bool 'out_of_stock'
     *  bool 'variation'
     *  bool 'inactive'
     *  string 'product_ids'
     * ]
     */
    private $exportConfiguration;

    /**
     * LengowExport constructor
     * @param LengowConfiguration $lengowConfiguration lengow config access
     * @param EntityRepositoryInterface $productRepository product repository
     * @param EntityRepositoryInterface $salesChannelRepository sales channel repository
     * @param EntityRepositoryInterface $categoryRepository category repository
     * @param EntityRepositoryInterface $shippingMethodRepository, shipping method repository
     * @param EntityRepositoryInterface $lengowProductRepository lengow product repository
     * @param EntityRepositoryInterface $currencyRepository lengow product repository
     * @param EntityRepositoryInterface $languageRepository lengow product repository
     * @param EntityRepositoryInterface $customFieldRepository lengow product repository
     * @param EntityRepositoryInterface $customFieldSetRepository lengow product repository
     * @param EntityRepositoryInterface $propertyGroupTranslationRepository
     * @param LengowLog $lengowLog Lengow log service
     * @param LengowFeed $lengowFeed lengow feed service
     * @param LengowProduct $lengowProduct lengow product service
     */
    public function __construct(
        LengowConfiguration $lengowConfiguration,
        EntityRepositoryInterface $productRepository,
        EntityRepositoryInterface $salesChannelRepository,
        EntityRepositoryInterface $categoryRepository,
        EntityRepositoryInterface $shippingMethodRepository,
        EntityRepositoryInterface $lengowProductRepository,
        EntityRepositoryInterface $currencyRepository,
        EntityRepositoryInterface $languageRepository,
        EntityRepositoryInterface $customFieldRepository,
        EntityRepositoryInterface $customFieldSetRepository,
        EntityRepositoryInterface $propertyGroupTranslationRepository,
        LengowLog $lengowLog,
        LengowFeed $lengowFeed,
        LengowProduct $lengowProduct
    ) {
        $this->lengowConfiguration = $lengowConfiguration;
        $this->productRepository = $productRepository;
        $this->salesChannelRepository = $salesChannelRepository;
        $this->categoryRepository = $categoryRepository;
        $this->lengowProductRepository = $lengowProductRepository;
        $this->currencyRepository = $currencyRepository;
        $this->languageRepository = $languageRepository;
        $this->shippingMethodRepository = $shippingMethodRepository;
        /** export exec repository */
        $this->customFieldRepository = $customFieldRepository;
        $this->customFieldSetRepository = $customFieldSetRepository;
        $this->propertyGroupTranslationRepository = $propertyGroupTranslationRepository;
        $this->lengowFeed = $lengowFeed;
        $this->lengowProduct = $lengowProduct;
        $this->lengowLog = $lengowLog;
    }

    /**
     * Init LengowExport class
     *
     * @param array $exportArgs
     */
    public function init(array $exportArgs): void
    {
        $this->exportConfiguration['log_output'] = $exportArgs['log_output'] ?? false;
        $this->exportConfiguration['stream'] = $exportArgs['log_output'] ?? false;
        $this->exportConfiguration = [
            'log_output' => $exportArgs['log_output'] ?? false,
            'selection' => $exportArgs['selection'],
            'out_of_stock' => $exportArgs['out_of_stock'] ?? false,
            'variation' => $exportArgs['variation'] ?? true,
            'inactive' => $exportArgs['inactive'] ?? false,
            'format' => $this->validateFormat($exportArgs['format'] ?: ''),
            'product_ids' => $exportArgs['product_ids'] ?: '',
            'sales_channel_id' => $exportArgs['sales_channel_id'],
            'stream' => $exportArgs['stream'] ?: false,
            'offset' => $exportArgs['offset'] ?? 0,
            'limit' => $exportArgs['limit'] ?? 0,
            'currency' => $this->getExportCurrency(
                $exportArgs['sales_channel_id'],
                ($exportArgs['currency'] ?? null)
            ),
            'shipping' => $this->getExportShippingMethod($exportArgs['sales_channel_id']),
        ];
    }

    /**
     * Validate requested format
     *
     * @param string $format requested format
     * @return string
     */
    private function validateFormat(string $format): string
    {
        if ($format && in_array($format, LengowFeed::$availableFormats, true)) {
            return $format;
        }
        $this->lengowLog->write(
            LengowLog::CODE_EXPORT,
            $this->lengowLog->encodeMessage('log.export.default_format_used', [
                'format_name' => LengowFeed::FORMAT_CSV,
            ]),
            $this->exportConfiguration['log_output']
        );
        return LengowFeed::FORMAT_CSV;
    }

    /**
     * Get total export size
     *
     * @param string $salesChannelId sales channel id to count
     * @return int total export number
     */
    public function getTotalExport(string $salesChannelId): int
    {
        $categoryEntryPoint = $this->getCategoryEntryPoint($salesChannelId);
        $masterCategoryId = $this->getMasterCategory($categoryEntryPoint);
        if (!$masterCategoryId) {
            return 0;
        }
        $categoryCriteria = new Criteria();
        $categoryCriteria->addAssociation('products');
        $categoryCollection = $this->categoryRepository->search($categoryCriteria, Context::createDefaultContext());
        $total = 0;
        foreach ($categoryCollection as $category) {
            $total += count($category->getProducts());
        }
        return $total;
    }


    /**
     * Get all product ID in export // todo Refacto this method to use Raw sql (performance improvment)
     *
     * @param string $salesChannelId sales channel id
     * @return array products ids
     */
    public function getProductIdsExport(string $salesChannelId) : array
    {
        // if selection is activated or product_ids get argument is used
        if ($this->exportConfiguration['selection'] || $this->exportConfiguration['product_ids']) {
            return $this->getSelectionProductIdsExport($this->getLengowProductIdsToExport($salesChannelId));
        }
        // else retrieve all products for saleChannelId
        $categoryEntryPoint = $this->getCategoryEntryPoint($salesChannelId);
        $masterCategoryId = $this->getMasterCategory($categoryEntryPoint);
        if (!$masterCategoryId) {
            $this->lengowLog->write(
                LengowLog::CODE_EXPORT,
                $this->lengowLog->encodeMessage('log.export.category_not_found'),
                $this->exportConfiguration['log_output']
            );
            return [];
        }
        $categoryCriteria = new Criteria();
        $categoryCriteria->addFilter(new ContainsFilter('path', $masterCategoryId))->addAssociation('products');
        $categoryCollection = $this->categoryRepository->search($categoryCriteria, Context::createDefaultContext());
        $productIdArray = [];
        $parentProductCounter = 0;
        $childProductCounter = 0;
        foreach ($categoryCollection as $category) {
            foreach ($category->getProducts() as $product) {
                if ($product->getParentId() === null) {
                    if ($this->isExportable($product)) {
                        $productIdArray[] = $product->getId();
                        $parentProductCounter++;
                    }
                    $children = $this->getChild($product);
                    $childProductCounter += count($this->getExportableChild($children));
                    $productIdArray = array_merge($productIdArray, $this->getExportableChild($children));
                }
            }
        }
        $this->lengowLog->write(
            LengowLog::CODE_EXPORT,
            $this->lengowLog->encodeMessage('log.export.total_product_exported', [
                'nb_products' => count($productIdArray),
                'nb_products_children' => $childProductCounter,
                'nb_products_parent' => $parentProductCounter,
            ]),
            $this->exportConfiguration['log_output']
        );
        return $productIdArray;
    }

    /**
     * Get product id to export if selection or product_ids get argument are active
     *
     * @param string $salesChannelId the sales channel id
     * @return array
     */
    public function getLengowProductIdsToExport(string $salesChannelId) : array
    {
        $lengowProductIds = [];
        if ($this->exportConfiguration['selection']) {
            $lengowProductCriteria = new Criteria();
            $lengowProductCriteria->addFilter(new EqualsFilter('salesChannelId', $salesChannelId));
            $lengowProductArray = $this->lengowProductRepository
                ->search($lengowProductCriteria, Context::createDefaultContext())
                ->getEntities()
                ->getElements();
            foreach($lengowProductArray as $id => $product) {
                $lengowProductIds[] = $product->getProductId();
            }
            if ($this->exportConfiguration['product_ids']) {
                // search for specific product ids
                $lengowProductIds = array_intersect(
                    $lengowProductIds,
                    explode(',', $this->exportConfiguration['product_ids'])
                );
            }
        } else if ($this->exportConfiguration['product_ids']) { // search for specific product ids
            $lengowProductIds = explode(',', $this->exportConfiguration['product_ids']);
        }
        return $lengowProductIds;
    }

    /**
     * Get product to export and order them by parent->children if selection or product_ids get argument are active
     *
     * @param array $productIds product ids
     * @return array
     */
    public function getSelectionProductIdsExport(array $productIds) : array
    {
        $selectionProductIdsExport = [];
        $productCriteria = new Criteria();
        $productCriteria->setIds($productIds);
        $productCollection = $this->productRepository->search($productCriteria, Context::createDefaultContext())->getEntities();
        $parentProductCounter = 0;
        $childProductCounter = 0;
        if ($productCollection->count() > 0) {
            foreach ($productCollection as $product) {
                if ($product->getParentId()) { // skip product if it's a child
                    continue;
                }
                if ($this->isExportable($product)) {
                    $selectionProductIdsExport[] = $product->getId();
                    $parentProductCounter++;
                }
                $children = $this->getChild($product);
                $childProductCounter += count($this->getExportableChild($children));
                $selectionProductIdsExport = array_merge(
                    $selectionProductIdsExport,
                    $this->getExportableChild($children)
                );
            }
        }
        $this->lengowLog->write(
            LengowLog::CODE_EXPORT,
            $this->lengowLog->encodeMessage('log.export.total_product_exported', [
                'nb_products' => count($selectionProductIdsExport),
                'nb_products_children' => $childProductCounter,
                'nb_products_parent' => $parentProductCounter,
            ]),
            $this->exportConfiguration['log_output']
        );
        return $selectionProductIdsExport;
    }

    /**
     * Get all exportable children products
     *
     * @param ProductCollection $childrenCollection All children products
     * @return array
     */
    public function getExportableChild(ProductCollection $childrenCollection) : array
    {
        $exportableChildren = [];
        foreach ($childrenCollection as $product) {
            if ($this->isExportable($product)) {
                $exportableChildren[] = $product->getId();
            }
        }
        return $exportableChildren;
    }

    /**
     * check if product is exportable depending on get param and configuration
     *
     * @param ProductEntity $product the product
     * @return bool
     */
    public function isExportable(ProductEntity $product) : bool
    {
        return !(!$this->exportConfiguration['variation']
                || (!$this->exportConfiguration['out_of_stock'] && $product->getAvailableStock() <= 0)
                || (!$this->exportConfiguration['inactive'] && $product->getActive() !== true)
        );
    }

    /**
     * Get child id from a parent product
     *
     * @param ProductEntity $parentProduct the parent product
     * @return ProductCollection
     */
    public function getChild(ProductEntity $parentProduct) : ProductCollection
    {
        $productCriteria = new Criteria();
        $productCriteria->addFilter(new EqualsFilter('parentId', $parentProduct->getId()));
        return $this->productRepository->search($productCriteria, Context::createDefaultContext())->getEntities();
    }

    /**
     * Get sales channel category entry point
     *
     * @param string $salesChannelId sales channel id
     * @return string|null
     */
    private function getCategoryEntryPoint(string $salesChannelId)
    {
        $salesChannelCriteria = new Criteria();
        $salesChannelCriteria->setIds([$salesChannelId]);
        $salesChannelArray = $this->salesChannelRepository
            ->search($salesChannelCriteria, Context::createDefaultContext())
            ->getEntities()
            ->getElements();
        if (isset($salesChannelArray[$salesChannelId])) {
            $salesChannel = $salesChannelArray[$salesChannelId];
            return ($salesChannel->getNavigationCategoryId());
        }
        return null;
    }

    /**
     * Get salesChannel master category
     *
     * @param string|null $categoryEntryPoint
     * @return string|null
     */
    private function getMasterCategory(?string $categoryEntryPoint = null): ?string
    {
        if (!$categoryEntryPoint) {
            return null;
        }
        $categoryCriteria = new Criteria();
        $categoryCriteria->setIds([$categoryEntryPoint]);
        $categoryArray = $this->categoryRepository
            ->search($categoryCriteria, Context::createDefaultContext())
            ->getEntities()
            ->getElements();
        if (!isset($categoryArray[array_key_first($categoryArray)])) {
            return null;
        }
        return (string) $categoryArray[array_key_first($categoryArray)]->getId();
    }

    /**
     * return json encoded string with all export parameters
     *
     * @return mixed All export params with example
     */
    public function getExportParams()
    {
        $exportParams = [];
        $exportParams['mode'] = [
            'Authorized Value' => ['size', 'total'],
            'type' => 'string',
            'example' => 'mode=size'
        ];
        $exportParams['format'] = [
            'Authorized Value' => ['csv', 'json'],
            'type' => 'string',
            'example' => 'format=csv'
        ];
        $exportParams['limit'] = [
            'Authorized Value' => ['0-999999'],
            'type' => 'signed integer',
            'example' => 'limit=100'
        ];
        $exportParams['offset'] = [
            'Authorized Value' => ['0-999999'],
            'type' => 'signed integer',
            'example' => 'offset=100'
        ];
        $exportParams['product_ids'] = [
            'Authorized Value' => ['all integers'],
            'type' => 'integer',
            'example' => 'product_ids=101,102,103'
        ];
        $exportParams['sales_channel_id'] = [
            'Authorized Value' => [$this->getAllSalesChannelAvailableId()],
            'type' => 'Binary',
            'example' => '98432def39fc4624b33213a56b8c944d'
        ];
        $exportParams['stream'] = [
            'Authorized Value' => ['0', '1'],
            'type' => 'Bool',
            'example' => 'stream=1'
        ];
        $exportParams['out_of_stock'] = [
            'Authorized Value' => ['0', '1'],
            'type' => 'Bool',
            'example' => 'out_of_stock=1'
        ];
        $exportParams['variation'] = [
            'Authorized Value' => ['0', '1'],
            'type' => 'Bool',
            'example' => 'variation=1'
        ];
        $exportParams['inactive'] = [
            'Authorized Value' => ['0', '1'],
            'type' => 'Bool',
            'example' => 'inactive=1'
        ];
        $exportParams['selection'] = [
            'Authorized Value' => ['0', '1'],
            'type' => 'Bool',
            'example' => 'selection=1'
        ];
        $exportParams['log_output'] = [
            'Authorized Value' => ['0', '1'],
            'type' => 'Bool',
            'example' => 'log_output=1'
        ];
        $exportParams['update_export_date'] = [
            'Authorized Value' => ['0', '1'],
            'type' => 'Bool',
            'example' => 'update_export_date=1'
        ];
        $exportParams['currency'] = [
            'Authorized Value' => [$this->getAllCurrenciesAvailable()],
            'type' => 'string',
            'example' => 'currency=EUR'
        ];
        $exportParams['language'] = [
            'Authorized Value' => $this->getAllLanguages(),
            'type' => 'string',
            'example' => 'language=english'
        ];
        $exportParams['get_params'] = [
            'Authorized Value' => ['0', '1'],
            'type' => 'bool',
            'example' => 'get_params=1'
        ];
        return json_encode($exportParams);
    }

    /**
     * Get all sales channels
     *
     * @return array all sales channel ids
     */
    private function getAllSalesChannelAvailableId() : array
    {
        $result = $this->salesChannelRepository->search(
            new Criteria(),
            Context::createDefaultContext()
        );
        $salesChannels = (array) $result->getEntities()->getElements();
        $ids = [];
        foreach ($salesChannels as $salesChannel) {
            $ids[] = $salesChannel->getId();
        }
        return $ids;
    }

    /**
     * get all currencies available
     *
     * @return array all available currencies
     */
    private function getAllCurrenciesAvailable() : array
    {
        $result = $this->currencyRepository->search(
            new Criteria(),
            Context::createDefaultContext()
        );
        $currencies = (array) $result->getEntities()->getElements();
        $iso = [];
        foreach ($currencies as $currency) {
            $iso[] = $currency->getIsoCode();
        }
        return $iso;
    }

    /**
     * Get all Languages available
     *
     * @return array
     */
    private function getAllLanguages() : array
    {
        $result = $this->languageRepository->search(
            new Criteria(),
            Context::createDefaultContext()
        );
        $langs = (array) $result->getEntities()->getElements();
        $languages = [];
        foreach ($langs as $language) {
            $languages[] = $language->getName();
        }
        return $languages;
    }

    /**
     * execute export
     *
     * @param string $salesChannelName the sales channel name for logging export
     * @param array $exportArgs the export arguments to init export
     * @return bool
     * @throws LengowException
     */
    public function exec(string $salesChannelName, array $exportArgs) : bool
    {
        try {
            $fields = $this->getHeaderFields($exportArgs['sales_channel_id']);
            $this->lengowFeed->init($exportArgs['sales_channel_id'], $exportArgs['stream'], $exportArgs['format']);
            // Write headers
            $this->lengowFeed->write(LengowFeed::HEADER, $fields);
            $this->init($exportArgs);
            // Log export start
            $this->lengowLog->write(
                LengowLog::CODE_EXPORT,
                $this->lengowLog->encodeMessage('log.export.start', [
                    'sales_channel_name' => $salesChannelName,
                ]),
                $this->exportConfiguration['log_output']
            );
            // Write body
            $this->writeFieldsData($fields);
            // Write footer
            if (!$this->lengowFeed->end()) {
                $this->lengowLog->write(
                    LengowLog::CODE_EXPORT,
                    $this->lengowLog->encodeMessage('log.export.error_folder_not_created_or_writable'),
                    $this->exportConfiguration['log_output']
                );
            } elseif (!$exportArgs['stream']) {
                $this->lengowLog->write(
                    LengowLog::CODE_EXPORT,
                    $this->lengowLog->encodeMessage('log.export.generate_feed_available_here', [
                        'sales_channel_name' => $salesChannelName,
                        'link_file_export' => $this->lengowFeed->getExportFilePath(),
                    ]),
                    $this->exportConfiguration['log_output']
                );
            }
            if ($exportArgs['update_export_date']) {
                $this->lengowConfiguration->set(
                    'lengowLastExport',
                    (string) time(),
                    $exportArgs['sales_channel_id']
                );
            }
            return true;
        } catch (Exception $e) {
            $this->lengowLog->write(
                LengowLog::CODE_EXPORT,
                $this->lengowLog->encodeMessage('log.export.export_failed', [
                    'fail_reason' => $e->getMessage(),
                ]),
                $this->exportConfiguration['log_output']
            );
            return false;
        }
    }

    /**
     * Get all field's headers
     *
     * @param string $salesChannelId sales channel id
     * @return array
     */
    private function getHeaderFields(string $salesChannelId) : array
    {
        $fields = [];
        foreach (self::$defaultFields as $key => $value) {
            $fields[] = $key;
        }
        $fields = array_merge(
            $fields,
            $this->getAllCustomHeaderField(),
            $this->getAllPropertiesHeaderField($salesChannelId)
        );
        return $fields;
    }

    /**
     * @param array $headerFields all header field
     * @return bool
     */
    public function writeFieldsData(array $headerFields) : bool
    {
        $numberOfProducts = $displayedProducts = 0;
        $language = null;
        $salesChannelCriteria = new Criteria();
        $salesChannelCriteria
            ->addFilter(new EqualsFilter('id', $this->exportConfiguration['sales_channel_id']))
            ->addAssociation('currency')
            ->addAssociation('shippingMethod')
            ->addAssociation('shippingMethod.prices')
            ->addAssociation('languages')
            ->addAssociation('languages.translationCode');
        $result = $this->salesChannelRepository->search(
            $salesChannelCriteria,
            Context::createDefaultContext()
        );
        if (($result->getEntities()->count() > 0) && $result->getEntities()->first()->getLanguages()->count() > 0) {
            $language = $result->getEntities()->first()->getLanguages()->first();
        }
        if (!$this->exportConfiguration['currency'] || !$this->exportConfiguration['shipping']) {
            $this->lengowLog->write(
                LengowLog::CODE_EXPORT,
                $this->lengowLog->encodeMessage('log.export.specify_shipping_and_currency', [
                    'sales_channel_name' => $result->getName(),
                ]),
                $this->exportConfiguration['log_output']
            );
            return false;
        }
        $isFirst = true;
        $this->lengowLog->write(
            LengowLog::CODE_EXPORT,
            $this->lengowLog->encodeMessage('log.export.memory_usage', [
                'memory' => round(memory_get_usage() / 1000000, 2),
            ]),
            $this->exportConfiguration['log_output']
        );
        $productIds = $this->getProductIdsExport($this->exportConfiguration['sales_channel_id']);
        foreach ($productIds as $productId) {
            // if offset specified in params
            if ($this->exportConfiguration['offset'] !== 0
                && $this->exportConfiguration['offset'] > $numberOfProducts)
            {
                $numberOfProducts++;
                continue;
            }
            if ($this->exportConfiguration['limit'] !== 0
                && $this->exportConfiguration['limit'] <= $displayedProducts)
            {
                break;
            }
            $fieldsData = $this->lengowProduct->getData(
                $productId,
                $headerFields,
                $this->exportConfiguration['currency'],
                $this->exportConfiguration['shipping'],
                $language
            );
            $this->lengowFeed->write(LengowFeed::BODY, $fieldsData, $isFirst);
            $isFirst = false;
            $numberOfProducts++;
            $displayedProducts++;
            if ($displayedProducts > 0 && $displayedProducts % 50 === 0) {
                $this->lengowLog->write(
                    LengowLog::CODE_EXPORT,
                    $this->lengowLog->encodeMessage('log.export.count_product', [
                        'nb_products' => $displayedProducts,
                    ]),
                    $this->exportConfiguration['log_output']
                );
            }
            gc_collect_cycles();
        }
        $this->lengowLog->write(
            LengowLog::CODE_EXPORT,
            $this->lengowLog->encodeMessage('log.export.memory_usage', [
                'memory' => round(memory_get_usage() / 1000000, 2)
            ]),
            $this->exportConfiguration['log_output']
        );
        return true;
    }

    /**
     * Get export currency
     *
     * @param string $salesChannelId the sales channel id
     * @param null $currencyIso search for a specific currency
     * @return CurrencyEntity|null
     */
    public function getExportCurrency(string $salesChannelId, $currencyIso = null) : ?CurrencyEntity
    {
        // if currency is specified, check if it exist and retrieve it
        if ($currencyIso) {
            $currencyCriteria = new Criteria();
            $currencyCriteria->addFilter(new EqualsFilter('isoCode', $currencyIso));
            $currenciesCollection = $this->currencyRepository->search(
                $currencyCriteria,
                Context::createDefaultContext()
            )->getEntities();
            if ($currenciesCollection->count() > 0) {
                $this->lengowLog->write(
                    LengowLog::CODE_EXPORT,
                    $this->lengowLog->encodeMessage('log.export.currency_used', [
                        'currency_name' => $currenciesCollection->first()->getIsoCode(),
                    ]),
                    $this->exportConfiguration['log_output']
                );
                return $currenciesCollection->first();
            }
        }
        // if no currency specified, get sales channel's default one
        $salesChannelCriteria = new Criteria();
        $salesChannelCriteria->addFilter(new EqualsFilter('id', $salesChannelId))
            ->addAssociation('currency');
        $result = $this->salesChannelRepository->search(
            $salesChannelCriteria,
            Context::createDefaultContext()
        );
        if ($result->getEntities()->count() > 0 && $result->getEntities()->first()->getCurrency()) {
            $this->lengowLog->write(
                LengowLog::CODE_EXPORT,
                $this->lengowLog->encodeMessage('log.export.default_currency_used', [
                    'currency_name' => $result->getEntities()->first()->getCurrency()->getIsoCode(),
                ]),
                $this->exportConfiguration['log_output']
            );
            return $result->getEntities()->first()->getCurrency();
        }
        return null;
    }

    /**
     * get shipping method to use for export
     *
     * @param string $salesChannelId the sales channel Id
     * @return ShippingMethodEntity|null
     */
    public function getExportShippingMethod(string $salesChannelId) : ?ShippingMethodEntity
    {
        // get shipping method from lengow configuration
        $shippingMethodId = $this->lengowConfiguration->get(
            LengowConfiguration::LENGOW_EXPORT_DEFAULT_SHIPPING_METHOD,
            $salesChannelId
        );
        $shippingMethodCriteria = new Criteria();
        $shippingMethodCriteria->addFilter(new EqualsFilter('id', $shippingMethodId))
            ->addAssociation('prices')
            ->addAssociation('prices.rule')
            ->addAssociation('prices.rules.shippingMethodPriceCalculations');
        $shippingMethodCollection = $this->shippingMethodRepository
            ->search($shippingMethodCriteria, Context::createDefaultContext())
            ->getEntities();
        if ($shippingMethodCollection->count() > 0) {
            $this->lengowLog->write(
                LengowLog::CODE_EXPORT,
                $this->lengowLog->encodeMessage('log.export.shipping_method_used', [
                    'shipping_method_name' => $shippingMethodCollection->first()->getName(),
                ]),
                $this->exportConfiguration['log_output']
            );
            return $shippingMethodCollection->first();
        }
        // if shipping method selected in configuration is not found, use sales channel default one
        $salesChannelCriteria = new Criteria();
        $salesChannelCriteria->addFilter(new EqualsFilter('id', $salesChannelId))
            ->addAssociation('shippingMethod')
            ->addAssociation('shippingMethod.prices')
            ->addAssociation('shippingMethod.prices.rules')
            ->addAssociation('shippingMethod.prices.rules.shippingMethodPriceCalculations');
        $salesChannelCollection = $this->salesChannelRepository->search(
            $salesChannelCriteria,
            Context::createDefaultContext()
        )->getEntities();
        if ($salesChannelCollection->count() > 0) {
            $this->lengowLog->write(
                LengowLog::CODE_EXPORT,
                $this->lengowLog->encodeMessage('log.export.default_shipping_method_used', [
                    'shipping_method_name' => $salesChannelCollection->first()->getShippingMethod()->getName()
                ]),
                $this->exportConfiguration['log_output']
            );
            return $salesChannelCollection->first()->getShippingMethod();
        }
        return null;
    }

    /**
     * Get all ACTIVE custom fields
     * Format is 'CustomFieldSetName'_'CustomFieldName'
     *
     * @return array
     */
    private function getAllCustomHeaderField() : array
    {
        $fields = [];
        $customFieldSetCriteria = new Criteria();
        $customFieldSetCriteria->addFilter(new EqualsFilter('active', 1));
        $customFieldSetCriteria->addAssociation('customFieldSet');
        $searchResult = $this->customFieldRepository->search($customFieldSetCriteria, Context::createDefaultContext());
        if ($searchResult->count() > 0) {
            foreach ($searchResult as $customField) {
                $fields[] = 'custom_' . $customField->getName();
            }
        }
        return $fields;
    }

    /**
     * Get all properties fields
     * Format is 'prop'_'fieldName'
     *
     * @return array
     */
    private function getAllPropertiesHeaderField($salesChannelId) : array
    {
        $fields = [];
        $salesChannelCriteria = new Criteria();
        $salesChannelCriteria->addFilter(new EqualsFilter('id', $salesChannelId));
        $salesChannel = $this->salesChannelRepository->search($salesChannelCriteria, Context::createDefaultContext());
        if ($salesChannel->count() > 0) {
            $languageId = $salesChannel->first()->getLanguageId();
            $propertyGroupTranslationCriteria = new Criteria();
            $propertyGroupTranslationCriteria->addFilter(new EqualsFilter('languageId', $languageId));
            $searchResult = $this->propertyGroupTranslationRepository
                ->search($propertyGroupTranslationCriteria, Context::createDefaultContext());
            if ($searchResult->count() > 0) {
                foreach ($searchResult as $propertiesField) {
                    $fields[] = 'prop_' . $propertiesField->getName();
                }
            }
        }
        return $fields;
    }

}
