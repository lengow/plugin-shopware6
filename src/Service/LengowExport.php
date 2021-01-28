<?php declare(strict_types=1);

namespace Lengow\Connector\Service;

use \Exception;
use Doctrine\DBAL\Connection as DatabaseConnexion;
use Shopware\Core\Checkout\Shipping\ShippingMethodEntity;
use Shopware\Core\Content\Product\Aggregate\ProductConfiguratorSetting\ProductConfiguratorSettingCollection;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Content\Product\ProductCollection;
use Shopware\Core\Content\Property\PropertyGroupCollection;
use Shopware\Core\Content\Property\PropertyGroupEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\ContainsFilter;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\Currency\CurrencyEntity;
use Shopware\Core\System\CustomField\CustomFieldCollection;
use Shopware\Core\System\CustomField\CustomFieldEntity;
use Shopware\Core\System\Language\LanguageCollection;
use Shopware\Core\System\Language\LanguageEntity;
use Shopware\Core\System\SalesChannel\SalesChannelCollection;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Lengow\Connector\Exception\LengowException;

/**
 * Class LengowExport
 * @package Lengow\Connector\Service
 */
class LengowExport
{
    /**
     * @var string manual export type
     */
    public const TYPE_MANUAL = 'manual';

    /**
     * @var string cron export type
     */
    public const TYPE_CRON = 'cron';

    /**
     * @var LengowConfiguration Lengow configuration service
     */
    private $lengowConfiguration;

    /**
     * @var EntityRepositoryInterface productRepository
     */
    private $salesChannelRepository;

    /**
     * @var EntityRepositoryInterface productRepository
     */
    private $lengowProductRepository;

    /**
     * @var EntityRepositoryInterface shopware currency repository
     */
    private $currencyRepository;

    /**
     * @var EntityRepositoryInterface shopware languages repository
     */
    private $languageRepository;

    /**
     * @var EntityRepositoryInterface shopware custom field repository
     */
    private $customFieldRepository;

    /**
     * @var EntityRepositoryInterface shopware product configurator setting repository
     */
    private $productConfiguratorSettingRepository;

    /**
     * @var EntityRepositoryInterface shopware property group repository
     */
    private $propertyGroupRepository;

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
     * @var DatabaseConnexion Doctrine database connexion service
     */
    private $connexion;

    /**
     * @var array all available params for export
     */
    public static $exportParams = [
        'mode',
        'format',
        'stream',
        'offset',
        'limit',
        'selection',
        'out_of_stock',
        'product_ids',
        'variation',
        'inactive',
        'sales_channel_id',
        'currency',
        'language',
        'log_output',
        'update_export_date',
        'get_params',
    ];

    /**
     * @var array default fields
     */
    public static $defaultFields = [
        'id' => 'id',
        'sku' => 'sku',
        'sku_supplier' => 'sku_supplier',
        'ean' => 'ean',
        'name' => 'name',
        'quantity' => 'quantity',
        'status' => 'status',
        'category' => 'category',
        'url' => 'url',
        'price_excl_tax' => 'price_excl_tax',
        'price_incl_tax' => 'price_incl_tax',
        'price_before_discount_excl_tax' => 'price_before_discount_excl_tax',
        'price_before_discount_incl_tax' => 'price_before_discount_incl_tax',
        'discount_amount' => 'discount_amount',
        'discount_percent' => 'discount_percent',
        'discount_start_date' => 'discount_start_date',
        'discount_end_date' => 'discount_end_date',
        'shipping_method' => 'shipping_method',
        'shipping_cost' => 'shipping_cost',
        'shipping_delay' => 'shipping_delay',
        'size_unit' => 'size_unit',
        'weight_unit' => 'weight_unit',
        'weight' => 'weight',
        'width' => 'width',
        'height' => 'height',
        'length' => 'length',
        'minimal_quantity' => 'minimal_quantity',
        'currency' => 'currency',
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
        'parent_id' => 'parent_id',
        'variation' => 'variation',
        'language' => 'language',
        'description_short' => 'description_short',
        'description' => 'description',
        'description_html' => 'description_html',
        'meta_title' => 'meta_title',
        'meta_keyword' => 'meta_keyword',
        'supplier' => 'supplier',
    ];

    /**
     * @var SalesChannelEntity Shopware sales channel entity
     */
    private $salesChannel;

    /**
     * @var bool see log or not
     */
    private $logOutput = false;

    /**
     * @var array all export configuration parameters
     */
    private $exportConfiguration;

    /**
     * @var int counter for simple product
     */
    private $simpleProductCounter = 0;

    /**
     * @var int counter for parent product
     */
    private $parentProductCounter = 0;

    /**
     * @var int counter for child product
     */
    private $childProductCounter = 0;

    /**
     * LengowExport constructor
     * @param LengowConfiguration $lengowConfiguration lengow config access
     * @param EntityRepositoryInterface $salesChannelRepository sales channel repository
     * @param EntityRepositoryInterface $shippingMethodRepository, shipping method repository
     * @param EntityRepositoryInterface $lengowProductRepository lengow product repository
     * @param EntityRepositoryInterface $currencyRepository currency repository
     * @param EntityRepositoryInterface $languageRepository language repository
     * @param EntityRepositoryInterface $productConfiguratorSettingRepository product configurator setting repository
     * @param EntityRepositoryInterface $customFieldRepository custom field repository
     * @param EntityRepositoryInterface $propertyGroupRepository property group repository
     * @param LengowLog $lengowLog Lengow log service
     * @param LengowFeed $lengowFeed lengow feed service
     * @param LengowProduct $lengowProduct lengow product service
     * @param DatabaseConnexion $connexion doctrine database connexion service
     */
    public function __construct(
        LengowConfiguration $lengowConfiguration,
        EntityRepositoryInterface $salesChannelRepository,
        EntityRepositoryInterface $shippingMethodRepository,
        EntityRepositoryInterface $lengowProductRepository,
        EntityRepositoryInterface $currencyRepository,
        EntityRepositoryInterface $languageRepository,
        EntityRepositoryInterface $productConfiguratorSettingRepository,
        EntityRepositoryInterface $customFieldRepository,
        EntityRepositoryInterface $propertyGroupRepository,
        LengowLog $lengowLog,
        LengowFeed $lengowFeed,
        LengowProduct $lengowProduct,
        DatabaseConnexion $connexion
    )
    {
        $this->lengowConfiguration = $lengowConfiguration;
        $this->salesChannelRepository = $salesChannelRepository;
        $this->lengowProductRepository = $lengowProductRepository;
        $this->currencyRepository = $currencyRepository;
        $this->languageRepository = $languageRepository;
        $this->shippingMethodRepository = $shippingMethodRepository;
        /** export exec repository */
        $this->productConfiguratorSettingRepository = $productConfiguratorSettingRepository;
        $this->customFieldRepository = $customFieldRepository;
        $this->propertyGroupRepository = $propertyGroupRepository;
        $this->lengowFeed = $lengowFeed;
        $this->lengowProduct = $lengowProduct;
        $this->lengowLog = $lengowLog;
        $this->connexion = $connexion;
    }

    /**
     * Init LengowExport class
     *
     * @param array $params optional options
     * List params
     * string format           Export Format (csv|yaml|xml|json)
     * bool   stream           Display file when call script (1) | Save File (0)
     * int    offset           From what product export
     * int    limit            The number of product to be exported
     * bool   selection        Export selected product (1) | Export all products (0)
     * bool   out_of_stock     Export product in stock and out stock (1) | Export Only in stock product (0)
     * bool   inactive         Export disabled products (1) | Export only enabled products (0)
     * bool   variation        Export product variations (1) | Export only parent product (0)
     * string product_ids      Ids product to export
     * int    sales_channel_id Id of sales channel
     * string currency         Currency for export
     * string language         Language for export
     * bool   log_output       See logs (only when stream = 0) (1) | no logs (0)
     */
    public function init(array $params = []): void
    {
        $salesChannelId = $params['sales_channel_id'] ?? '';
        $this->salesChannel = $this->getExportSalesChannel($salesChannelId);
        $stream = $params['stream'] ?? true;
        $this->logOutput = $stream ? false : ($params['log_output'] ?? false);
        $updateExportDate = $params['update_export_date'] ?? true;
        $this->exportConfiguration = [
            'format' => $this->setFormat($params['format'] ?? ''),
            'stream' => $stream,
            'type' => $updateExportDate ? self::TYPE_CRON : self::TYPE_MANUAL,
            'update_export_date' => $updateExportDate,
            'offset' => $params['offset'] ?? 0,
            'limit' => $params['limit'] ?? 0,
            'selection' => $params['selection'] ?? $this->lengowConfiguration->get(
                    LengowConfiguration::LENGOW_EXPORT_SELECTION_ENABLED,
                    $salesChannelId
                ),
            'out_of_stock' => $params['out_of_stock'] ?? $this->lengowConfiguration->get(
                    LengowConfiguration::LENGOW_EXPORT_OUT_OF_STOCK_ENABLED,
                    $salesChannelId
                ),
            'inactive' => $params['inactive'] ?? $this->lengowConfiguration->get(
                    LengowConfiguration::LENGOW_EXPORT_DISABLED_PRODUCT,
                    $salesChannelId
                ),
            'variation' => $params['variation'] ?? $this->lengowConfiguration->get(
                    LengowConfiguration::LENGOW_EXPORT_VARIATION_ENABLED,
                    $salesChannelId
                ),
            'product_ids' => $this->setProductIds($params['product_ids'] ?? false),
            'currency' => $params['currency'] ?? null,
            'language' => $params['language'] ?? null,
        ];
    }

    /**
     * Get total export size
     *
     * @return int total export number
     */
    public function getTotalProduct(): int
    {
        $entryPoint = $this->salesChannel->getNavigationCategoryId();
        // if no entry point is found, we can't retrieve the products
        if (!$entryPoint) {
            return 0;
        }
        $products = $this->connexion->fetchAll('
            SELECT DISTINCT id FROM product AS p
            JOIN product_category_tree as pct ON p.id = pct.product_id
            WHERE pct.category_id = "' . hex2bin($entryPoint) . '"
        ');
        return count($products);
    }

    /**
     * Get total number of exported product for initialized salesChannel
     *
     * @return int
     */
    public function getTotalExportedProduct(): int
    {
        return count($this->getProductIdsExport());
    }

    /**
     * execute export
     *
     * @return bool
     */
    public function exec(): bool
    {
        try {
            $this->lengowLog->write(
                LengowLog::CODE_EXPORT,
                $this->lengowLog->encodeMessage('log.export.start', [
                    'type' => $this->exportConfiguration['type'],
                ]),
                $this->logOutput
            );
            if ($this->salesChannel === null) {
                throw new LengowException(
                    $this->lengowLog->encodeMessage('log.export.specify_sales_channel')
                );
            }
            $this->lengowLog->write(
                LengowLog::CODE_EXPORT,
                $this->lengowLog->encodeMessage('log.export.start_for_sales_channel', [
                    'sales_channel_name' => $this->salesChannel->getName(),
                    'sales_channel_id' => $this->salesChannel->getId(),
                ]),
                $this->logOutput
            );
            // get fields to export
            $fields = $this->getHeaderFields();
            $this->lengowFeed->init(
                $this->salesChannel->getId(),
                $this->exportConfiguration['stream'],
                $this->exportConfiguration['format']
            );
            // write headers
            $this->lengowFeed->write(LengowFeed::HEADER, $fields);
            // write body
            $this->writeFieldsData($fields);
            // write footer
            if (!$this->lengowFeed->end()) {
                throw new LengowException(
                    $this->lengowLog->encodeMessage('log.export.error_folder_not_created_or_writable')
                );
            }
            if (!$this->exportConfiguration['stream']) {
                $this->lengowLog->write(
                    LengowLog::CODE_EXPORT,
                    $this->lengowLog->encodeMessage('log.export.generate_feed_available_here', [
                        'sales_channel_name' => $this->salesChannel->getName(),
                        'link_file_export' => $this->lengowFeed->getExportFilePath(),
                    ]),
                    $this->logOutput
                );
            }
            if ($this->exportConfiguration['update_export_date']) {
                $this->lengowConfiguration->set('lengowLastExport', (string) time(), $this->salesChannel->getId());
            }
            $this->lengowLog->write(
                LengowLog::CODE_EXPORT,
                $this->lengowLog->encodeMessage('log.export.end', [
                    'type' => $this->exportConfiguration['type'],
                ]),
                $this->logOutput
            );
        } catch (LengowException $e) {
            $errorMessage = $e->getMessage();
        } catch (Exception $e) {
            $errorMessage = '[Shopware error]: "' . $e->getMessage() . '" ' . $e->getFile() . ' | ' . $e->getLine();
        }
        if (isset($errorMessage)) {
            $decodedMessage = $this->lengowLog->decodeMessage($errorMessage, LengowTranslation::DEFAULT_ISO_CODE);
            $this->lengowLog->write(
                LengowLog::CODE_EXPORT,
                $this->lengowLog->encodeMessage('log.export.export_failed', [
                    'decoded_message' => $decodedMessage,
                ]),
                $this->logOutput
            );
            return false;
        }
        return true;
    }

    /**
     * Get all product ID in export (all exportable products)
     *
     * @param array $LengowSelectionProductIds can be used to narrow the function scope to specific products
     * @return array products ids
     */
    public function getProductIdsExport(array $LengowSelectionProductIds = []): array
    {
        $lengowProductIds = [];
        // function can be use to retrieve specific product
        if ($LengowSelectionProductIds) {
            $lengowProductIds = array_merge($lengowProductIds, $LengowSelectionProductIds);
        } else if ($this->exportConfiguration['selection'] || !empty($this->exportConfiguration['product_ids'])) {
            // if selection is activated or product_ids get argument is used
            $lengowProductIds = $this->getLengowProductIdsToExport();
        }
        $entryPoint = $this->salesChannel->getNavigationCategoryId();
        // if no entry point is found, we can't retrieve the products
        if (!$entryPoint) {
            return [];
        }
        $products = $this->connexion->fetchAll('
            SELECT DISTINCT id, available_stock, active, parent_id
            FROM product AS p
            JOIN product_category_tree as pct
            ON p.id = pct.product_id
            WHERE pct.category_id = "' . hex2bin($entryPoint) . '"
        ');
        // clean result from db before sorting
        foreach ($products as &$product) {
            $product['id'] = (string) bin2hex($product['id']);
            if ($product['parent_id']) {
                $product['parent_id'] = (string) bin2hex($product['parent_id']);
            }
        }
        // unset foreach ref for garbage collector
        unset($product);
        $sortedProductIds = [];
        foreach ($products as $product) {
            // if selection is active and product is not selected or if product is a child / skip it
            if ($product['parent_id'] || ($lengowProductIds && !in_array($product['id'], $lengowProductIds, false))) {
                continue;
            }
            if ($this->isExportableFromSqlResult($product)) {
                $sortedProductIds[] = [
                    'id' => $product['id'],
                    'type' => '',
                ];
                // save product key to add type after attempting children recuperation
                $parentArrayEntry = array_key_last($sortedProductIds);
                // get child count and add child to array
                $nbChildren = $this->getChildProductIdFromSqlResult($product, $products, $sortedProductIds);
                if ($nbChildren > 0) {
                    // product is a parent
                    $this->parentProductCounter++;
                    $sortedProductIds[$parentArrayEntry] = [
                        'id' => $product['id'],
                        'type' => 'parent',
                    ];
                    $this->childProductCounter += $nbChildren;
                } else {
                    // product is a simple
                    $this->simpleProductCounter++;
                    $sortedProductIds[$parentArrayEntry] = [
                        'id' => $product['id'],
                        'type' => 'simple',
                    ];
                }
            }
        }
        return $sortedProductIds;
    }

    /**
     * retrieve all parent products for sales channel
     *
     * @return array
     */
    public function getAllProductIdForSalesChannel(): array
    {
        $entryPoint = $this->salesChannel->getNavigationCategoryId();
        // if no entry point is found, we can't retrieve the products
        if (!$entryPoint) {
            return [];
        }
        $productIds = [];
        $products = $this->connexion->fetchAll('
            SELECT DISTINCT id FROM product AS p
            JOIN product_category_tree as pct ON p.id = pct.product_id
            WHERE pct.category_id = "' . hex2bin($entryPoint) . '" AND p.parent_id IS NULL
        ');
        // clean result from db before sorting
        foreach ($products as $product) {
            $productIds[] = (string) bin2hex($product['id']);
        }
        return $productIds;
    }

    /**
     * return json encoded string with all export parameters
     *
     * @return mixed All export params with example
     */
    public function getExportParams()
    {
        $params = [];
        foreach (self::$exportParams as $param) {
            switch ($param) {
                case 'mode':
                    $authorizedValue = ['size', 'total'];
                    $type = 'string';
                    $example = 'size';
                    break;
                case 'format':
                    $authorizedValue = LengowFeed::$availableFormats;
                    $type = 'string';
                    $example = LengowFeed::FORMAT_CSV;
                    break;
                case 'sales_channel_id':
                    $authorizedValue = $this->getAllSalesChannelAvailableId();
                    $type = 'string';
                    $example = '98432def39fc4624b33213a56b8c944d';
                    break;
                case 'currency':
                    $authorizedValue = $this->getAllCurrenciesAvailable();
                    $type = 'string';
                    $example = 'EUR';
                    break;
                case 'language':
                    $authorizedValue = $this->getAllLanguages();
                    $type = 'string';
                    $example = 'en-GB';
                    break;
                case 'offset':
                case 'limit':
                    $authorizedValue = 'all integers';
                    $type = 'integer';
                    $example = 100;
                    break;
                case 'product_ids':
                    $authorizedValue = 'all strings';
                    $type = 'string';
                    $example = '98432def39fc4624b33213a56b8c944d,98329ef39fc4624b33213r87ds56gh9';
                    break;
                default:
                    $authorizedValue = [0, 1];
                    $type = 'integer';
                    $example = 1;
                    break;
            }
            $params[$param] = [
                'authorized_values' => $authorizedValue,
                'type' => $type,
                'example' => $example,
            ];
        }
        return json_encode($params);
    }

    /**
     * Get sales channel to init export
     *
     * @param string $salesChannelId sales channel id
     *
     * @return SalesChannelEntity|null
     */
    private function getExportSalesChannel(string $salesChannelId): ?SalesChannelEntity
    {
        $context = Context::createDefaultContext();
        $criteria = new Criteria();
        // Use a default sales channel if sales channel id is not valid
        if (Uuid::isValid($salesChannelId)) {
            $criteria->addFilter(new EqualsFilter('id', $salesChannelId));
        }
        $criteria
            ->addAssociation('currency')
            ->addAssociation('shippingMethod')
            ->addAssociation('shippingMethod.prices')
            ->addAssociation('shippingMethod.prices.rule')
            ->addAssociation('shippingMethod.prices.rules.shippingMethodPriceCalculations')
            ->addAssociation('languages')
            ->addAssociation('languages.translationCode');
        /** @var SalesChannelCollection $salesChannelCollection */
        $salesChannelCollection = $this->salesChannelRepository->search($criteria, $context)->getEntities();
        return $salesChannelCollection->count() > 0 ? $salesChannelCollection->first() : null;
    }

    /**
     * Set format feed to export
     *
     * @param string $format requested format
     *
     * @return string
     */
    private function setFormat(string $format): string
    {
        if ($format && in_array($format, LengowFeed::$availableFormats, true)) {
            return $format;
        }
        return LengowFeed::FORMAT_CSV;
    }

    /**
     * Set product ids to export
     *
     * @param bool|array $productIds product ids to export
     *
     * @return array
     */
    private function setProductIds($productIds): array
    {
        $ids = [];
        if ($productIds) {
            $exportedIds = explode(',', $productIds);
            $ids = array_filter($exportedIds, function($id) {
                return Uuid::isValid($id);
            });
        }
        return $ids;
    }

    /**
     * return child count and add all child to array $sortedProductIds from sql result
     *
     * @param array $parent the sql result of parent products query
     * @param array $products the sql result of all products query
     * @param array $sortedProductIds array of sorted products
     * @return int the number of child for the given parent products
     */
    private function getChildProductIdFromSqlResult(array $parent, array $products, array &$sortedProductIds): int
    {
        $childCounter = 0;
        foreach ($products as $product) {
            if ($product['parent_id'] === $parent['id'] && $this->isExportableFromSqlResult($product, $parent)) {
                $childCounter++;
                $sortedProductIds[] = [
                    'id' => $product['id'],
                    'type' => 'child',
                ];
            }
        }
        return $childCounter;
    }

    /**
     * Check if product is exportable from sql query result
     *
     * @param array $productData the sql result of the product query
     * @param array $parentData if the product is a child, active check must be on the parent
     * @return bool
     */
    private function isExportableFromSqlResult(array $productData, array $parentData = []): bool
    {
        if ($parentData) {
            return !(
                (!$this->exportConfiguration['variation'] && !empty($productData['parent_id']))
                || (!$this->exportConfiguration['out_of_stock'] && (int) $productData['available_stock'] <= 0)
                || (!$this->exportConfiguration['inactive'] && !((bool) $parentData['active']))
            );
        }
        return !(
            (!$this->exportConfiguration['variation'] && !empty($productData['parent_id']))
            || (!$this->exportConfiguration['out_of_stock'] && (int) $productData['available_stock'] <= 0)
            || (!$this->exportConfiguration['inactive'] && !((bool) $productData['active']))
        );
    }

    /**
     * Get product id to export if selection or product_ids get argument are active
     *
     * @return array
     */
    private function getLengowProductIdsToExport(): array
    {
        $lengowProductIds = [];
        if ($this->exportConfiguration['selection']) {
            $lengowProductCriteria = new Criteria();
            $lengowProductCriteria->addFilter(new EqualsFilter('salesChannelId', $this->salesChannel->getId()));
            $lengowProductArray = $this->lengowProductRepository
                ->search($lengowProductCriteria, Context::createDefaultContext())
                ->getEntities()
                ->getElements();
            foreach($lengowProductArray as $id => $product) {
                $lengowProductIds[] = $product->getProductId();
            }
            if ($this->exportConfiguration['product_ids']) {
                // search for specific product ids
                $lengowProductIds = array_intersect($lengowProductIds, $this->exportConfiguration['product_ids']);
            }
        } else if (!empty($this->exportConfiguration['product_ids'])) { // search for specific product ids
            $lengowProductIds = $this->exportConfiguration['product_ids'];
        }
        return $lengowProductIds;
    }

    /**
     * Get all sales channels
     *
     * @return array all sales channel ids
     */
    private function getAllSalesChannelAvailableId(): array
    {
        $result = $this->salesChannelRepository->search(
            new Criteria(),
            Context::createDefaultContext()
        );
        $salesChannelCollection = $result->getEntities()->getElements();
        $salesChannels = [];
        foreach ($salesChannelCollection as $salesChannel) {
            $salesChannels[] = $salesChannel->getId();
        }
        return $salesChannels;
    }

    /**
     * get all currencies available
     *
     * @return array all available currencies
     */
    private function getAllCurrenciesAvailable(): array
    {
        $result = $this->currencyRepository->search(
            new Criteria(),
            Context::createDefaultContext()
        );
        $currencyCollection = $result->getEntities()->getElements();
        $currencies = [];
        foreach ($currencyCollection as $currency) {
            $currencies[] = $currency->getIsoCode();
        }
        return $currencies;
    }

    /**
     * Get all Languages available
     *
     * @return array
     */
    private function getAllLanguages(): array
    {
        $criteria = new Criteria();
        $criteria->addAssociation('translationCode');
        $result = $this->languageRepository->search($criteria, Context::createDefaultContext());
        $languageCollection = $result->getEntities()->getElements();
        $languages = [];
        foreach ($languageCollection as $language) {
            if ($language->getTranslationCode() && $language->getTranslationCode()->getCode()) {
                $languages[] = $language->getTranslationCode()->getCode();
            }
        }
        return $languages;
    }

    /**
     * Get all field's headers
     *
     * @return array
     */
    private function getHeaderFields(): array
    {
        $fields = [];
        foreach (self::$defaultFields as $key => $value) {
            $fields[] = $key;
        }
        $fields = array_merge(
            $fields,
            $this->getAllOptionHeaderField(),
            $this->getAllCustomHeaderField(),
            $this->getAllPropertiesHeaderField()
        );
        return $fields;
    }

    /**
     * @param array $headerFields all header field
     *
     * @throws LengowException
     *
     * @return bool
     */
    private function writeFieldsData(array $headerFields): bool
    {
        $numberOfProducts = $displayedProducts = 0;
        // get language, currency and shipping method for export
        $language = $this->getExportLanguage($this->exportConfiguration['language']);
        $currency = $this->getExportCurrency($this->exportConfiguration['currency']);
        $shippingMethod = $this->getExportShippingMethod();
        if ($language === null || $currency === null || $shippingMethod === null) {
            throw new LengowException(
                $this->lengowLog->encodeMessage('log.export.specify_language_shipping_and_currency', [
                    'sales_channel_name' => $this->salesChannel->getName(),
                ])
            );
        }
        // init product service
        $this->lengowProduct->init([
            'sales_channel' => $this->salesChannel,
            'language' => $language,
            'currency' => $currency,
            'shipping_method' => $shippingMethod,
        ]);
        $isFirst = true;
        $this->lengowLog->write(
            LengowLog::CODE_EXPORT,
            $this->lengowLog->encodeMessage('log.export.memory_usage', [
                'memory' => round(memory_get_usage() / 1000000, 2),
            ]),
            $this->logOutput
        );
        $products = $this->getProductIdsExport();
        $this->lengowLog->write(
            LengowLog::CODE_EXPORT,
            $this->lengowLog->encodeMessage('log.export.total_product_exported', [
                'nb_products' => count($products),
                'nb_products_children' => $this->childProductCounter,
                'nb_products_parent' => $this->parentProductCounter,
                'nb_products_simple' => $this->simpleProductCounter,
            ]),
            $this->logOutput
        );
        foreach ($products as $product) {
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
            $this->lengowProduct->load($product['id'], $product['type']);
            $fieldsData = $this->lengowProduct->getData($headerFields);
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
                    $this->logOutput
                );
            }
            gc_collect_cycles();
        }
        $this->lengowLog->write(
            LengowLog::CODE_EXPORT,
            $this->lengowLog->encodeMessage('log.export.memory_usage', [
                'memory' => round(memory_get_usage() / 1000000, 2),
            ]),
            $this->logOutput
        );
        return true;
    }


    /**
     * Get export language
     *
     * @param string|null $languageIso search for a specific language
     *
     * @return LanguageEntity|null
     */
    private function getExportLanguage($languageIso = null): ?LanguageEntity
    {
        $context = Context::createDefaultContext();
        // if language is specified, check if it exist and retrieve it
        if ($languageIso) {
            $criteria = new Criteria();
            $criteria->addFilter(new EqualsFilter('translationCode.code', $languageIso))
                ->addAssociation('translationCode');
            /** @var LanguageCollection $languageCollection */
            $languageCollection = $this->languageRepository->search($criteria, $context)->getEntities();
            if ($languageCollection->count() > 0) {
                $this->lengowLog->write(
                    LengowLog::CODE_EXPORT,
                    $this->lengowLog->encodeMessage('log.export.language_used', [
                        'language_name' => $languageCollection->first()->getName(),
                    ]),
                    $this->logOutput
                );
                return $languageCollection->first();
            }
        }
        // if no language specified, get sales channel's default one
        $criteria = new Criteria();
        $criteria->setIds([$this->salesChannel->getLanguageId()])
            ->addAssociation('translationCode');
        /** @var LanguageCollection $languageCollection */
        $languageCollection = $this->languageRepository->search($criteria, $context)->getEntities();
        if ($languageCollection->count() > 0) {
            $this->lengowLog->write(
                LengowLog::CODE_EXPORT,
                $this->lengowLog->encodeMessage('log.export.default_language_used', [
                    'language_name' => $languageCollection->first()->getName(),
                ]),
                $this->logOutput
            );
            return $languageCollection->first();
        }
        return null;
    }

    /**
     * Get export currency
     *
     * @param null $currencyIso search for a specific currency
     *
     * @return CurrencyEntity|null
     */
    private function getExportCurrency($currencyIso = null): ?CurrencyEntity
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
                    $this->logOutput
                );
                return $currenciesCollection->first();
            }
        }
        // if no currency specified, get sales channel's default one
        if ($this->salesChannel->getCurrency()) {
            $this->lengowLog->write(
                LengowLog::CODE_EXPORT,
                $this->lengowLog->encodeMessage('log.export.default_currency_used', [
                    'currency_name' => $this->salesChannel->getCurrency()->getIsoCode(),
                ]),
                $this->logOutput
            );
            return $this->salesChannel->getCurrency();
        }
        return null;
    }

    /**
     * get shipping method to use for export
     *
     * @return ShippingMethodEntity|null
     */
    private function getExportShippingMethod(): ?ShippingMethodEntity
    {
        // get shipping method from lengow configuration
        $shippingMethodId = $this->lengowConfiguration->get(
            LengowConfiguration::LENGOW_EXPORT_DEFAULT_SHIPPING_METHOD,
            $this->salesChannel->getId()
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
                $this->logOutput
            );
            return $shippingMethodCollection->first();
        }
        // if shipping method selected in configuration is not found, use sales channel default one
        if ($this->salesChannel->getShippingMethod()) {
            $this->lengowLog->write(
                LengowLog::CODE_EXPORT,
                $this->lengowLog->encodeMessage('log.export.default_shipping_method_used', [
                    'shipping_method_name' => $this->salesChannel->getShippingMethod()->getName(),
                ]),
                $this->logOutput
            );
            return $this->salesChannel->getShippingMethod();
        }
        return null;
    }

    /**
     * Get all option fields
     * Format is 'opt'_'fieldName'
     *
     * @return array
     */
    private function getAllOptionHeaderField(): array
    {
        $fields = [];
        $context = Context::createDefaultContext();
        $productConfiguratorSettingCriteria = new Criteria();
        $productConfiguratorSettingCriteria
            ->addAssociation('option')
            ->addAssociation('option.group');
        /** @var ProductConfiguratorSettingCollection $productConfiguratorSettingCollection */
        $productConfiguratorSettingCollection = $this->productConfiguratorSettingRepository
            ->search($productConfiguratorSettingCriteria, $context)
            ->getEntities();
        if ($productConfiguratorSettingCollection->count() > 0) {
            $propertyGroupCollection = $productConfiguratorSettingCollection->getGroupedOptions();
            if ($propertyGroupCollection->count() > 0) {
                /** @var PropertyGroupEntity $propertyGroup */
                foreach ($propertyGroupCollection as $propertyGroup) {
                    $fields[] = 'opt_' . $propertyGroup->getName();
                }
            }
        }
        return $fields;
    }

    /**
     * Get all ACTIVE custom fields
     * Format is 'custom'_'customFieldName'
     *
     * @return array
     */
    private function getAllCustomHeaderField(): array
    {
        $fields = [];
        $context = Context::createDefaultContext();
        $customFieldCriteria = new Criteria();
        $customFieldCriteria->addFilter(new EqualsFilter('active', 1))
            ->addAssociation('customFieldSet');
        /** @var CustomFieldCollection $customFieldCollection */
        $customFieldCollection = $this->customFieldRepository->search($customFieldCriteria, $context)->getEntities();
        if ($customFieldCollection->count() > 0) {
            /** @var CustomFieldEntity $customField */
            foreach ($customFieldCollection as $customField) {
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
    private function getAllPropertiesHeaderField(): array
    {
        $fields = [];
        $context = Context::createDefaultContext();
        $propertyGroupCriteria = new Criteria();
        /** @var PropertyGroupCollection $propertyGroupCollection */
        $propertyGroupCollection = $this->propertyGroupRepository->search($propertyGroupCriteria, $context)
            ->getEntities();
        if ($propertyGroupCollection->count() > 0) {
            /** @var PropertyGroupEntity $propertyGroup */
            foreach ($propertyGroupCollection as $propertyGroup) {
                $fields[] = 'prop_' . $propertyGroup->getName();
            }
        }
        return $fields;
    }
}
