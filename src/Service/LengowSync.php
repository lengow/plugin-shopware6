<?php declare(strict_types=1);

namespace Lengow\Connector\Service;

use Lengow\Connector\Exception\LengowException;
use Lengow\Connector\Factory\LengowFileFactory;
use Lengow\Connector\Util\EnvironmentInfoProvider;

/**
 * Class LengowSync
 * @package Lengow\Connector\Service
 */
class LengowSync
{
    /**
     * @var string sync catalog action
     */
    public const SYNC_CATALOG = 'catalog';

    /**
     * @var string sync cms option action
     */
    public const SYNC_CMS_OPTION = 'cms_option';

    /**
     * @var string sync status account action
     */
    public const SYNC_STATUS_ACCOUNT = 'status_account';

    /**
     * @var string sync marketplace action
     */
    public const SYNC_MARKETPLACE = 'marketplace';

    /**
     * @var string sync order action
     */
    public const SYNC_ORDER = 'order';

    /**
     * @var string sync action action
     */
    public const SYNC_ACTION = 'action';

    /**
     * @var string sync plugin version action
     */
    public const SYNC_PLUGIN_DATA = 'plugin';

    /**
     * @var string name of logs folder
     */
    private const CONFIG_FOLDER_NAME = 'Config';

    /**
     * @var string name of logs folder
     */
    private const MARKETPLACE_FILE = 'marketplaces.json';

    /**
     * @var LengowConnector Lengow connector service
     */
    private $lengowConnector;

    /**
     * @var LengowConfiguration Lengow configuration service
     */
    private $lengowConfiguration;

    /**
     * @var LengowLog Lengow log service
     */
    private $lengowLog;

    /**
     * @var LengowFileFactory Lengow file factory
     */
    private $lengowFileFactory;

    /**
     * @var EnvironmentInfoProvider Environment info provider utility
     */
    private $environmentInfoProvider;

    /**
     * @var array cache time for catalog, account status, cms options and marketplace synchronisation
     */
    private $cacheTimes = array(
        self::SYNC_CATALOG => 21600,
        self::SYNC_CMS_OPTION => 86400,
        self::SYNC_STATUS_ACCOUNT => 86400,
        self::SYNC_MARKETPLACE => 43200,
        self::SYNC_PLUGIN_DATA => 86400,
    );

    /**
     * LengowImportOrder Construct
     *
     * @param LengowConnector $lengowConnector Lengow connector service
     * @param LengowConfiguration $lengowConfiguration Lengow configuration service
     * @param LengowLog $lengowLog Lengow log service
     * @param LengowFileFactory $lengowFileFactory Lengow file factory
     * @param EnvironmentInfoProvider $environmentInfoProvider Environment info provider utility
     */
    public function __construct(
        LengowConnector $lengowConnector,
        LengowConfiguration $lengowConfiguration,
        LengowLog $lengowLog,
        LengowFileFactory $lengowFileFactory,
        EnvironmentInfoProvider $environmentInfoProvider
    )
    {
        $this->lengowConnector = $lengowConnector;
        $this->lengowConfiguration = $lengowConfiguration;
        $this->lengowLog = $lengowLog;
        $this->lengowFileFactory = $lengowFileFactory;
        $this->environmentInfoProvider = $environmentInfoProvider;
    }

    /**
     * Get marketplace data
     *
     * @param bool $force force cache update
     * @param bool $logOutput see log or not
     *
     * @return mixed|false
     */
    public function getMarketplaces(bool $force = false, bool $logOutput = false)
    {
        $sep = DIRECTORY_SEPARATOR;
        $filePath = $this->environmentInfoProvider->getPluginPath()
            . $sep . self::CONFIG_FOLDER_NAME . $sep . self::MARKETPLACE_FILE;
        if (!$force) {
            $updatedAt = $this->lengowConfiguration->get('lengowMarketplaceUpdate');
            if ($updatedAt !== null
                && (time() - (int)$updatedAt) < $this->cacheTimes[self::SYNC_MARKETPLACE]
                && file_exists($filePath)
            ) {
                // recovering data with the marketplaces.json file
                $marketplacesData = file_get_contents($filePath);
                if ($marketplacesData) {
                    return json_decode($marketplacesData);
                }
            }
        }
        // recovering data with the API
        $result = $this->lengowConnector->queryApi(
            LengowConnector::GET,
            LengowConnector::API_MARKETPLACE,
            [],
            '',
            $logOutput
        );
        if ($result && is_object($result) && !isset($result->error)) {
            // updated marketplaces.json file
            try {
                $marketplaceFile = $this->lengowFileFactory->create(
                    self::CONFIG_FOLDER_NAME,
                    self::MARKETPLACE_FILE,
                    'w+'
                );
                $marketplaceFile->write(json_encode($result));
                $marketplaceFile->close();
                $this->lengowConfiguration->set('lengowMarketplaceUpdate', (string)time());
            } catch (LengowException $e) {
                $decodedMessage = $this->lengowLog->decodeMessage($e->getMessage());
                $this->lengowLog->write(
                    LengowLog::CODE_IMPORT,
                    $this->lengowLog->encodeMessage('log.import.marketplace_update_failed', [
                        'decoded_message' => $decodedMessage
                    ]),
                    $logOutput
                );
            }
            return $result;
        }
        // if the API does not respond, use marketplaces.json if it exists
        if (file_exists($filePath)) {
            $marketplacesData = file_get_contents($filePath);
            if ($marketplacesData) {
                return json_decode($marketplacesData);
            }
        }
        return false;
    }
}