<?php declare(strict_types=1);

namespace Lengow\Connector\Service;

/**
 * Class LengowCatalog
 * @package Lengow\Connector\Service
 */
class LengowCatalog
{
    /**
     * @var LengowConfiguration Lengow configuration service
     */
    private $lengowConfiguration;

    /**
     * @var LengowConnector Lengow connector service
     */
    private $lengowConnector;

    /**
     * @var LengowLog Lengow log service
     */
    private $lengowLog;

    /**
     * LengowCatalog constructor
     *
     * @param LengowConfiguration $lengowConfiguration Lengow configuration service
     * @param LengowConnector $lengowConnector Lengow connector service
     * @param LengowLog $lengowLog Lengow log service
     */
    public function __construct(
        LengowConfiguration $lengowConfiguration,
        LengowConnector $lengowConnector,
        LengowLog $lengowLog
    )
    {
        $this->lengowConfiguration = $lengowConfiguration;
        $this->lengowConnector = $lengowConnector;
        $this->lengowLog = $lengowLog;
    }

    /**
     * Get all catalogs available in Lengow
     *
     * @return array
     */
    public function getCatalogList(): array
    {
        $catalogList = [];
        $lengowCatalogs = $this->lengowConnector->queryApi(LengowConnector::GET, LengowConnector::API_CMS_CATALOG);
        if (!$lengowCatalogs) {
            return $catalogList;
        }
        foreach ($lengowCatalogs as $catalog) {
            if (!is_object($catalog) || $catalog->shop) {
                continue;
            }
            $name = $catalog->name ?? $this->lengowLog->decodeMessage('lengow_log.connection.catalog', null, [
                'catalog_id' => $catalog->id,
            ]);
            $status = $catalog->is_active
                ? $this->lengowLog->decodeMessage('lengow_log.connection.status_active')
                : $this->lengowLog->decodeMessage('lengow_log.connection.status_draft');
            $label = $this->lengowLog->decodeMessage('lengow_log.connection.catalog_label', null, [
                'catalog_id' => $catalog->id,
                'catalog_name' => $name,
                'nb_products' => $catalog->products ?? 0,
                'catalog_status' => $status,
            ]);
            $catalogList[] = [
                'label' => $label,
                'value' => $catalog->id,
            ];
        }
        return $catalogList;
    }

    /**
     * Link all catalogs by API
     *
     * @param array $catalogsBySalesChannels all catalog ids organised by sales channels
     *
     * @return bool
     */
    public function linkCatalogs(array $catalogsBySalesChannels): bool
    {
        $catalogsLinked = false;
        $hasCatalogToLink = false;
        if (empty($catalogsBySalesChannels)) {
            return $catalogsLinked;
        }
        $linkCatalogData = [
            'cms_token' => $this->lengowConfiguration->getToken(),
            'shops' => [],
        ];
        foreach ($catalogsBySalesChannels as $salesChannelId => $catalogIds) {
            if (empty($catalogIds)) {
                continue;
            }
            $hasCatalogToLink = true;
            $salesChannelToken = $this->lengowConfiguration->getToken($salesChannelId);
            $linkCatalogData['shops'][] = [
                'shop_token' => $salesChannelToken,
                'catalogs_id' => $catalogIds,
            ];
            $this->lengowLog->write(
                LengowLog::CODE_CONNECTION,
                $this->lengowLog->encodeMessage('log.connection.try_link_catalog', [
                    'catalog_ids' => implode(', ', $catalogIds),
                    'shop_token' => $salesChannelToken,
                    'sales_channel_id' => $salesChannelId,
                ])
            );
        }
        if ($hasCatalogToLink) {
            $result = $this->lengowConnector->queryApi(
                LengowConnector::POST,
                LengowConnector::API_CMS_MAPPING,
                [],
                json_encode($linkCatalogData)
            );
            if (isset($result->cms_token)) {
                $catalogsLinked = true;
            }
        }
        return $catalogsLinked;
    }
}
