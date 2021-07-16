<?php declare(strict_types=1);

namespace Lengow\Connector\Service;

use Lengow\Connector\Util\EnvironmentInfoProvider;

/**
 * Class LengowToolbox
 * @package Lengow\Connector\Service
 */
class LengowToolbox
{
    /* Toolbox GET params */
    public const PARAM_TOKEN = 'token';
    public const PARAM_TOOLBOX_ACTION = 'toolbox_action';
    public const PARAM_DATE = 'date';
    public const PARAM_TYPE = 'type';

    /* Toolbox Actions */
    public const ACTION_DATA = 'data';
    public const ACTION_LOG = 'log';

    /* Data type */
    public const DATA_TYPE_ALL = 'all';
    public const DATA_TYPE_CHECKLIST = 'checklist';
    public const DATA_TYPE_CHECKSUM = 'checksum';
    public const DATA_TYPE_CMS = 'cms';
    public const DATA_TYPE_LOG = 'log';
    public const DATA_TYPE_PLUGIN = 'plugin';
    public const DATA_TYPE_OPTION = 'option';
    public const DATA_TYPE_SHOP = 'shop';
    public const DATA_TYPE_SYNCHRONIZATION = 'synchronization';

    /* Toolbox Data  */
    public const CHECKLIST = 'checklist';
    public const CHECKLIST_CURL_ACTIVATED = 'curl_activated';
    public const CHECKLIST_SIMPLE_XML_ACTIVATED = 'simple_xml_activated';
    public const CHECKLIST_JSON_ACTIVATED = 'json_activated';
    public const CHECKLIST_MD5_SUCCESS = 'md5_success';
    public const PLUGIN = 'plugin';
    public const PLUGIN_CMS_VERSION = 'cms_version';
    public const PLUGIN_VERSION = 'plugin_version';
    public const PLUGIN_DEBUG_MODE_DISABLE = 'debug_mode_disable';
    public const PLUGIN_WRITE_PERMISSION = 'write_permission';
    public const PLUGIN_SERVER_IP = 'server_ip';
    public const PLUGIN_AUTHORIZED_IP_ENABLE = 'authorized_ip_enable';
    public const PLUGIN_AUTHORIZED_IPS = 'authorized_ips';
    public const PLUGIN_TOOLBOX_URL = 'toolbox_url';
    public const SYNCHRONIZATION = 'synchronization';
    public const SYNCHRONIZATION_CMS_TOKEN = 'cms_token';
    public const SYNCHRONIZATION_CRON_URL = 'cron_url';
    public const SYNCHRONIZATION_NUMBER_ORDERS_IMPORTED = 'number_orders_imported';
    public const SYNCHRONIZATION_NUMBER_ORDERS_WAITING_SHIPMENT = 'number_orders_waiting_shipment';
    public const SYNCHRONIZATION_NUMBER_ORDERS_IN_ERROR = 'number_orders_in_error';
    public const SYNCHRONIZATION_SYNCHRONIZATION_IN_PROGRESS = 'synchronization_in_progress';
    public const SYNCHRONIZATION_LAST_SYNCHRONIZATION = 'last_synchronization';
    public const SYNCHRONIZATION_LAST_SYNCHRONIZATION_TYPE = 'last_synchronization_type';
    public const CMS_OPTIONS = 'cms_options';
    public const SHOPS = 'shops';
    public const SHOP_ID = 'shop_id';
    public const SHOP_NAME = 'shop_name';
    public const SHOP_DOMAIN_URL = 'domain_url';
    public const SHOP_TOKEN = 'shop_token';
    public const SHOP_FEED_URL = 'feed_url';
    public const SHOP_ENABLED = 'enabled';
    public const SHOP_CATALOG_IDS = 'catalog_ids';
    public const SHOP_NUMBER_PRODUCTS_AVAILABLE = 'number_products_available';
    public const SHOP_NUMBER_PRODUCTS_EXPORTED = 'number_products_exported';
    public const SHOP_LAST_EXPORT = 'last_export';
    public const SHOP_OPTIONS = 'shop_options';
    public const CHECKSUM = 'checksum';
    public const CHECKSUM_AVAILABLE = 'available';
    public const CHECKSUM_SUCCESS = 'success';
    public const CHECKSUM_NUMBER_FILES_CHECKED = 'number_files_checked';
    public const CHECKSUM_NUMBER_FILES_MODIFIED = 'number_files_modified';
    public const CHECKSUM_NUMBER_FILES_DELETED = 'number_files_deleted';
    public const CHECKSUM_FILE_MODIFIED = 'file_modified';
    public const CHECKSUM_FILE_DELETED = 'file_deleted';
    public const LOGS = 'logs';

    /* Toolbox files */
    public const FILE_CHECKMD5 = 'checkmd5.csv';
    public const FILE_TEST = 'test.txt';

    /**
     * @var array valid toolbox actions
     */
    private $toolboxActions = [
        self::ACTION_DATA,
        self::ACTION_LOG,
    ];

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
     * @var LengowLog $lengowLog Lengow log service
     */
    private $lengowLog;

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
     * @param LengowLog $lengowLog Lengow log service
     * @param LengowOrder $lengowOrder Lengow order service
     * @param EnvironmentInfoProvider $environmentInfoProvider Lengow environment info provider utility
     *
     */
    public function __construct(
        LengowConfiguration $lengowConfiguration,
        LengowExport $lengowExport,
        LengowImport $lengowImport,
        LengowLog $lengowLog,
        LengowOrder $lengowOrder,
        EnvironmentInfoProvider $environmentInfoProvider
    )
    {
        $this->lengowConfiguration = $lengowConfiguration;
        $this->lengowExport = $lengowExport;
        $this->lengowImport = $lengowImport;
        $this->lengowLog = $lengowLog;
        $this->lengowOrder = $lengowOrder;
        $this->environmentInfoProvider = $environmentInfoProvider;
    }

    /**
     * Get all toolbox data
     *
     * @param string $type Toolbox data type
     *
     * @return array
     */
    public function getData($type = self::DATA_TYPE_CMS): array
    {
        switch ($type) {
            case self::DATA_TYPE_ALL:
                return $this->getAllData();
            case self::DATA_TYPE_CHECKLIST:
                return $this->getChecklistData();
            case self::DATA_TYPE_CHECKSUM:
                return $this->getChecksumData();
            case self::DATA_TYPE_LOG:
                return $this->getLogData();
            case self::DATA_TYPE_OPTION:
                return $this->getOptionData();
            case self::DATA_TYPE_PLUGIN:
                return $this->getPluginData();
            case self::DATA_TYPE_SHOP:
                return $this->getShopData();
            case self::DATA_TYPE_SYNCHRONIZATION:
                return $this->getSynchronizationData();
            default:
            case self::DATA_TYPE_CMS:
                return $this->getCmsData();
        }
    }

    /**
     * Download log file individually or globally
     *
     * @param string|null $fileName name of file to download
     */
    public function downloadLog(string $fileName = null): void
    {
        $this->lengowLog->download($fileName);
    }

    /**
     * Is toolbox action
     *
     * @param string $action toolbox action
     *
     * @return bool
     */
    public function isToolboxAction(string $action): bool
    {
        return in_array($action, $this->toolboxActions, true);
    }

    /**
     * Check if PHP Curl is activated
     *
     * @return bool
     */
    public static function isCurlActivated(): bool
    {
        return function_exists('curl_version');
    }

    /**
     * Get array of requirements
     *
     * @return array
     */
    private function getAllData(): array
    {
        return [
            self::CHECKLIST => $this->getChecklistData(),
            self::PLUGIN => $this->getPluginData(),
            self::SYNCHRONIZATION => $this->getSynchronizationData(),
            self::CMS_OPTIONS => $this->lengowConfiguration->getAllValues(null, true),
            self::SHOPS => $this->getShopData(),
            self::CHECKSUM => $this->getChecksumData(),
            self::LOGS => $this->getLogData(),
        ];
    }

    /**
     * Get cms data
     *
     * @return array
     */
    private function getCmsData(): array
    {
        return [
            self::CHECKLIST => $this->getChecklistData(),
            self::PLUGIN => $this->getPluginData(),
            self::SYNCHRONIZATION => $this->getSynchronizationData(),
            self::CMS_OPTIONS => $this->lengowConfiguration->getAllValues(null, true),
        ];
    }

    /**
     * Get array of requirements
     *
     * @return array
     */
    private function getChecklistData(): array
    {
        $checksumData = $this->getChecksumData();
        return [
            self::CHECKLIST_CURL_ACTIVATED => self::isCurlActivated(),
            self::CHECKLIST_SIMPLE_XML_ACTIVATED => $this->isSimpleXMLActivated(),
            self::CHECKLIST_JSON_ACTIVATED  => $this->isJsonActivated(),
            self::CHECKLIST_MD5_SUCCESS => $checksumData[self::CHECKSUM_SUCCESS],
        ];
    }

    /**
     * Get array of plugin data
     *
     * @return array
     */
    private function getPluginData(): array
    {
        return [
            self::PLUGIN_CMS_VERSION => $this->environmentInfoProvider->getVersion(),
            self::PLUGIN_VERSION => $this->environmentInfoProvider->getPluginVersion(),
            self::PLUGIN_DEBUG_MODE_DISABLE => !$this->lengowConfiguration->debugModeIsActive(),
            self::PLUGIN_WRITE_PERMISSION => $this->testWritePermission(),
            self::PLUGIN_SERVER_IP => $_SERVER['SERVER_ADDR'],
            self::PLUGIN_AUTHORIZED_IP_ENABLE => $this->lengowConfiguration->get(
                LengowConfiguration::AUTHORIZED_IP_ENABLED
            ),
            self::PLUGIN_AUTHORIZED_IPS => $this->lengowConfiguration->get(LengowConfiguration::AUTHORIZED_IPS),
            self::PLUGIN_TOOLBOX_URL => $this->lengowConfiguration->getToolboxUrl(),
        ];
    }

    /**
     * Get array of import data
     *
     * @return array
     */
    private function getSynchronizationData(): array
    {
        $lastImport = $this->lengowImport->getLastImport();
        return [
            self::SYNCHRONIZATION_CMS_TOKEN => $this->lengowConfiguration->getToken(),
            self::SYNCHRONIZATION_CRON_URL => $this->lengowConfiguration->getCronUrl(),
            self::SYNCHRONIZATION_NUMBER_ORDERS_IMPORTED => $this->lengowOrder->countOrderImportedByLengow(),
            self::SYNCHRONIZATION_NUMBER_ORDERS_WAITING_SHIPMENT => $this->lengowOrder->countOrderToBeSent(),
            self::SYNCHRONIZATION_NUMBER_ORDERS_IN_ERROR => $this->lengowOrder->countOrderWithError(),
            self::SYNCHRONIZATION_SYNCHRONIZATION_IN_PROGRESS => $this->lengowImport->isInProcess(),
            self::SYNCHRONIZATION_LAST_SYNCHRONIZATION => $lastImport['type'] === 'none' ? 0 : $lastImport['timestamp'],
            self::SYNCHRONIZATION_LAST_SYNCHRONIZATION_TYPE => $lastImport['type'],
        ];
    }

    /**
     * Get array of export data
     *
     * @return array
     */
    private function getShopData(): array
    {
        $exportData = [];
        $salesChannels = $this->environmentInfoProvider->getActiveSalesChannels();
        if ($salesChannels->count() === 0) {
            return $exportData;
        }
        foreach ($salesChannels as $salesChannel) {
            $salesChannelId = $salesChannel->getId();
            $this->lengowExport->init([
                LengowExport::PARAM_SALES_CHANNEL_ID => $salesChannelId,
            ]);
            $lastExport = $this->lengowConfiguration->get(LengowConfiguration::LAST_UPDATE_EXPORT, $salesChannelId);
            $exportData[] = [
                self::SHOP_ID => $salesChannelId,
                self::SHOP_NAME => $salesChannel->getName(),
                self::SHOP_DOMAIN_URL => $this->environmentInfoProvider->getBaseUrl($salesChannelId),
                self::SHOP_TOKEN => $this->lengowConfiguration->getToken($salesChannel->getId()),
                self::SHOP_FEED_URL => $this->lengowConfiguration->getFeedUrl($salesChannel->getId()),
                self::SHOP_ENABLED => $this->lengowConfiguration->salesChannelIsActive($salesChannelId),
                self::SHOP_CATALOG_IDS => $this->lengowConfiguration->getCatalogIds($salesChannelId),
                self::SHOP_NUMBER_PRODUCTS_AVAILABLE => $this->lengowExport->getTotalProduct(),
                self::SHOP_NUMBER_PRODUCTS_EXPORTED => $this->lengowExport->getTotalExportProduct(),
                self::SHOP_LAST_EXPORT => empty($lastExport) ? 0 : (int) $lastExport,
                self::SHOP_OPTIONS => $this->lengowConfiguration->getAllValues($salesChannelId, true),
            ];
        }
        return $exportData;
    }

    /**
     * Get array of export data
     *
     * @return array
     */
    private function getOptionData(): array
    {
        $optionData = [
            self::CMS_OPTIONS => $this->lengowConfiguration->getAllValues(),
            self::SHOP_OPTIONS => [],
        ];
        $salesChannels = $this->environmentInfoProvider->getActiveSalesChannels();
        foreach ($salesChannels as $salesChannel) {
            $optionData[self::SHOP_OPTIONS][] = $this->lengowConfiguration->getAllValues($salesChannel->getId());
        }
        return $optionData;
    }

    /**
     * Get files checksum
     *
     * @return array
     */
    private function getChecksumData(): array
    {
        $fileCounter = 0;
        $fileModified = [];
        $fileDeleted = [];
        $sep = DIRECTORY_SEPARATOR;
        $pluginPath = $this->environmentInfoProvider->getPluginPath();
        $fileName = $pluginPath . $sep . EnvironmentInfoProvider::FOLDER_CONFIG . $sep . self::FILE_CHECKMD5;
        if (file_exists($fileName)) {
            $md5Available = true;
            if (($file = fopen($fileName, 'r')) !== false) {
                while (($data = fgetcsv($file, 1000, '|')) !== false) {
                    $fileCounter++;
                    $shortPath = $data[0];
                    $filePath = $this->environmentInfoProvider->getPluginBasePath() . $data[0];
                    if (file_exists($filePath)) {
                        $fileMd = md5_file($filePath);
                        if ($fileMd !== $data[1]) {
                            $fileModified[] = $shortPath;
                        }
                    } else {
                        $fileDeleted[] = $shortPath;
                    }
                }
                fclose($file);
            }
        } else {
           $md5Available = false;
        }
        $fileModifiedCounter = count($fileModified);
        $fileDeletedCounter = count($fileDeleted);
        return [
            self::CHECKSUM_AVAILABLE => $md5Available,
            self::CHECKSUM_SUCCESS => !$md5Available || !($fileModifiedCounter > 0) || !($fileModifiedCounter > 0),
            self::CHECKSUM_NUMBER_FILES_CHECKED => $fileCounter,
            self::CHECKSUM_NUMBER_FILES_MODIFIED => $fileModifiedCounter,
            self::CHECKSUM_NUMBER_FILES_DELETED => $fileDeletedCounter,
            self::CHECKSUM_FILE_MODIFIED => $fileModified,
            self::CHECKSUM_FILE_DELETED => $fileDeleted,
        ];
    }

    /**
     * Get all log files available
     *
     * @return array
     */
    private function getLogData(): array
    {
        $logs = $this->lengowLog->getPaths();
        if (!empty($logs)) {
            $logs[] = [
                LengowLog::LOG_DATE => null,
                LengowLog::LOG_LINK => $this->lengowConfiguration->getToolboxUrl()
                    . '&' . self::PARAM_TOOLBOX_ACTION . '=' . self::ACTION_LOG,
            ];
        }
        return $logs;
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
    private function testWritePermission(): bool
    {
        $sep = DIRECTORY_SEPARATOR;
        $pluginPath = $this->environmentInfoProvider->getPluginPath();
        $filePath = $pluginPath . $sep . EnvironmentInfoProvider::FOLDER_CONFIG . $sep . self::FILE_TEST;
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
