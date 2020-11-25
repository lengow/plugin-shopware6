<?php declare(strict_types=1);

namespace Lengow\Connector\Service;

use Lengow\Connector\Util\EnvironmentInfoProvider;

/**
 * Class LengowToolbox
 * @package Lengow\Connector\Service
 */
class LengowToolbox
{
    /**
     * @var LengowConfiguration $lengowConfiguration Lengow configuration service
     */
    private $lengowConfiguration;

    /**
     * @var LengowExport $lengowExport Lengow export service
     */
    private $lengowExport;

    /**
     * @var LengowImport $lengowImport Lengow import service
     */
    private $lengowImport;

    /**
     * @var LengowOrder $lengowOrder Lengow order service
     */
    private $lengowOrder;

    /**
     * @var EnvironmentInfoProvider $environmentInfoProvider Environment info provider utility
     */
    private $environmentInfoProvider;

    /**
     * LengowToolbox constructor
     *
     * @param LengowConfiguration $lengowConfiguration Lengow configuration service
     * @param LengowExport $lengowExport Lengow export service
     * @param LengowImport $lengowImport Lengow import service
     * @param LengowOrder $lengowOrder Lengow order service
     * @param EnvironmentInfoProvider $environmentInfoProvider Lengow environment info provider utility
     *
     */
    public function __construct(
        LengowConfiguration $lengowConfiguration,
        LengowExport $lengowExport,
        LengowImport $lengowImport,
        LengowOrder $lengowOrder,
        EnvironmentInfoProvider $environmentInfoProvider
    )
    {
        $this->lengowConfiguration = $lengowConfiguration;
        $this->lengowExport = $lengowExport;
        $this->lengowImport = $lengowImport;
        $this->lengowOrder = $lengowOrder;
        $this->environmentInfoProvider = $environmentInfoProvider;
    }

    /**
     * Get array of requirements
     *
     * @return array
     */
    public function getAllData(): array
    {
        return [
            'checklist' => $this->getChecklistData(),
            'plugin' => $this->getPluginData(),
            'import' => $this->getImportData(),
            'export' => $this->getExportData(),
        ];
    }

    /**
     * Get array of requirements
     *
     * @return array
     */
    public function getChecklistData(): array
    {
        return [
            'curl_activated' => $this->isCurlActivated(),
            'simple_xml_activated' => $this->isSimpleXMLActivated(),
            'json_activated' => $this->isJsonActivated(),
            'md5_success' => true, // TODO add md5 verification
        ];
    }

    /**
     * Get array of plugin data
     *
     * @return array
     */
    public function getPluginData(): array
    {
        return [
            'version' => $this->environmentInfoProvider->getVersion(),
            'plugin_version' => $this->environmentInfoProvider->getPluginVersion(),
            'debug_mode_disable' => !$this->lengowConfiguration->debugModeIsActive(),
            'write_permission' => $this->testWritePermission(),
            'server_ip' => $_SERVER['SERVER_ADDR'],
            'authorized_ip_enable' => $this->lengowConfiguration->get(LengowConfiguration::LENGOW_IP_ENABLED),
            'authorized_ips' => $this->lengowConfiguration->get(LengowConfiguration::LENGOW_AUTHORIZED_IP),
        ];
    }

    /**
     * Get array of import data
     *
     * @return array
     */
    public function getImportData(): array
    {
        $lastImport = $this->lengowImport->getLastImport();
        return [
            'token' => $this->lengowConfiguration->getToken(),
            'cron_url' => $this->lengowConfiguration->getCronUrl(),
            'nb_order_imported' => $this->lengowOrder->countOrderImportedByLengow(),
            'nb_order_to_be_sent' => $this->lengowOrder->countOrderToBeSent(),
            'nb_order_with_error' => $this->lengowOrder->countOrderWithError(),
            'import_is_in_process' => $this->lengowImport->isInProcess(),
            'last_import' => $lastImport['type'] === 'none' ? 0 : $lastImport['timestamp'],
            'last_import_type' => $lastImport['type'],
        ];
    }

    /**
     * Get array of export data
     *
     * @return array
     */
    public function getExportData(): array
    {
        $exportData = [];
        $salesChannels = $this->environmentInfoProvider->getActiveSalesChannels();
        if ($salesChannels->count() === 0) {
            return $exportData;
        }
        foreach ($salesChannels as $salesChannel) {
            $salesChannelId = $salesChannel->getId();
            $this->lengowExport->init($salesChannelId);
            $lastExport = $this->lengowConfiguration->get(LengowConfiguration::LENGOW_LAST_EXPORT, $salesChannelId);
            $exportData[] = [
                'shop_id' => $salesChannelId,
                'shop_name' => $salesChannel->getName(),
                'domain_url' => $this->environmentInfoProvider->getBaseUrl($salesChannelId),
                'enabled' => $this->lengowConfiguration->salesChannelIsActive($salesChannelId),
                'catalog_ids' => $this->lengowConfiguration->getCatalogIds($salesChannelId),
                'total_product_number' => $this->lengowExport->getTotalProduct(),
                'exported_product_number'=> $this->lengowExport->getTotalExportedProduct(),
                'token' => $this->lengowConfiguration->getToken($salesChannel->getId()),
                'feed_url' => $this->lengowConfiguration->getFeedUrl($salesChannel->getId()),
                'last_export' => empty($lastExport) ? 0 : (int) $lastExport,
            ];
        }
        return $exportData;
    }

    /**
     * Check if PHP Curl is activated
     *
     * @return bool
     */
    private function isCurlActivated(): bool
    {
        return function_exists('curl_version');
    }

    /**
     * Check if SimpleXML Extension is activated
     *
     * @return bool
     */
    private function isSimpleXMLActivated(): bool
    {
        return function_exists('simplexml_load_file');
    }

    /**
     * Check if SimpleXML Extension is activated
     *
     * @return bool
     */
    private function isJsonActivated(): bool
    {
        return function_exists('json_decode');
    }

    /**
     * Test write permission for log and export in file
     *
     * @return bool
     */
    public function testWritePermission(): bool
    {
        $sep = DIRECTORY_SEPARATOR;
        $pluginPath = $this->environmentInfoProvider->getPluginPath();
        $filePath = $pluginPath . $sep . LengowLog::LOG_FOLDER_NAME . $sep . 'test.txt';
        try {
            $file = fopen($filePath, 'w+');
            if (!$file) {
                return false;
            }
            unlink($filePath);
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

}
