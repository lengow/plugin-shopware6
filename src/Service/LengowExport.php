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
     */
    public function __construct(
        LengowConfiguration $lengowConfiguration,
        EntityRepositoryInterface $productRepository,
        EntityRepositoryInterface $salesChannelRepository,
        EntityRepositoryInterface $categoryRepository,
        EntityRepositoryInterface $lengowProductRepository
    ) {
        $this->lengowConfiguration = $lengowConfiguration;
        $this->productRepository = $productRepository;
        $this->salesChannelRepository = $salesChannelRepository;
        $this->categoryRepository = $categoryRepository;
        $this->lengowProductRepository = $lengowProductRepository;
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

}
