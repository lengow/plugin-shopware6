<?php declare(strict_types=1);

namespace Lengow\Connector\Service;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\MultiFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\ContainsFilter;
use Shopware\Core\Framework\Uuid\Uuid; // todo remove

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
     * @param EntityRepositoryInterface $lengowProductRepository lengow product repository
     * @param EntityRepositoryInterface $currencyRepository lengow product repository
     * @param EntityRepositoryInterface $languageRepository lengow product repository
     */
    public function __construct(
        LengowConfiguration $lengowConfiguration,
        EntityRepositoryInterface $productRepository,
        EntityRepositoryInterface $salesChannelRepository,
        EntityRepositoryInterface $categoryRepository,
        EntityRepositoryInterface $lengowProductRepository,
        EntityRepositoryInterface $currencyRepository,
        EntityRepositoryInterface $languageRepository
    ) {
        $this->lengowConfiguration = $lengowConfiguration;
        $this->productRepository = $productRepository;
        $this->salesChannelRepository = $salesChannelRepository;
        $this->categoryRepository = $categoryRepository;
        $this->lengowProductRepository = $lengowProductRepository;
        $this->currencyRepository = $currencyRepository;
        $this->languageRepository = $languageRepository;
    }

    /**
     * Init LengowExport class
     *
     * @param string $salesChannelId sales channel id to export
     * @param bool $selection Get arg selection
     * @param bool $outOfStock Get arg ou of stock
     * @param bool $variation Get arg variation
     * @param bool $inactive Get arg inactive
     * @param string $productIds Get arg product_ids
     */
    public function init(
        string $salesChannelId,
        bool $selection,
        bool $outOfStock,
        bool $variation,
        bool $inactive,
        string $productIds
    ): void
    {
        $this->exportConfiguration = [
            'selection' => $selection ?: $this->lengowConfiguration->get('lengowExportSelectionEnabled', $salesChannelId),
            'out_of_stock' => $outOfStock ?: $this->lengowConfiguration->get('lengowExportOutOfStock', $salesChannelId),
            'variation' => $variation ?: $this->lengowConfiguration->get('lengowExportVariation', $salesChannelId),
            'inactive' => $inactive ?: $this->lengowConfiguration->get('lengowExportDisabledProduct', $salesChannelId),
            'product_ids' => $productIds ?: '',
        ];
    }

    /**
     * Get total export size
     *
     * @param string $salesChannelId sales channel id to count
     * @return int total export number
     */
    public function getTotalExport(string $salesChannelId)
    {
        $categoryEntryPoint = $this->getCategoryEntryPoint($salesChannelId);
        $masterCategoryId = $this->getMasterCategory($categoryEntryPoint);

        $productCriteria = new Criteria();
        $productCriteria->addFilter(new ContainsFilter('categoryTree', $masterCategoryId));
        $filteredProducts = $this->productRepository
            ->search($productCriteria, Context::createDefaultContext())
            ->getEntities()
            ->getElements();

        return (count($filteredProducts));
    }


    /**
     * Get all product ID in export
     *
     * @param string $salesChannelId sales channel id
     * @return array products ids
     */
    public function getProductIdsExport(string $salesChannelId) : array
    {
        $categoryEntryPoint = $this->getCategoryEntryPoint($salesChannelId);
        $masterCategoryId = $this->getMasterCategory($categoryEntryPoint);

        if (!$masterCategoryId) {
            return [];
        }
        $productCriteria = new Criteria();
        $productCriteria->addFilter(new ContainsFilter('categoryTree', $masterCategoryId));
        // should count variation ?
        if (!$this->exportConfiguration['variation']) {
            $productCriteria->addFilter(new EqualsFilter('parentId', NULL));
        }
        // should count out of stock ?
        if (!$this->exportConfiguration['out_of_stock']) {
            $productCriteria->addFilter(new EqualsFilter('available', true));
        }
        // should count inactive ?
        if (!$this->exportConfiguration['inactive']) {
            $productCriteria->addFilter(new EqualsFilter('active', true));
        }
        // should count on selection only ?
        if ($this->exportConfiguration['selection']) {
            $lengowProductCriteria = new Criteria();
            $lengowProductCriteria->addFilter(new EqualsFilter('salesChannelId', $salesChannelId));
            $lengowProductArray = $this->lengowProductRepository
                ->search($lengowProductCriteria, Context::createDefaultContext())
                ->getEntities()
                ->getElements();
            $lengowProductIds = [];
            foreach($lengowProductArray as $id => $product) {
                $lengowProductIds[] = $product->getProductId();
            }
            if ($this->exportConfiguration['product_ids']) {
                // search for specific product ids
                $product_ids = explode(',', $this->exportConfiguration['product_ids']);
                $lengowProductIds = array_intersect($product_ids, $lengowProductIds);
            }
            $productCriteria->setIds($lengowProductIds);
        } else if ($this->exportConfiguration['product_ids']) {
            // search for specific product ids
            $productCriteria->setIds(explode(',', $this->exportConfiguration['product_ids']));
        }
        $filteredProducts = $this->productRepository
            ->search($productCriteria, Context::createDefaultContext())
            ->getEntities()
            ->getElements();
        $idsArray = [];
        foreach($filteredProducts as $id => $product) {
            $idsArray[] = $id;
        }
        return $idsArray;
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
    private function getMasterCategory(?string $categoryEntryPoint = null)
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
}
