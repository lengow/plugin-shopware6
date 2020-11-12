<?php declare(strict_types=1);

namespace Lengow\Connector\Factory;

use Lengow\Connector\Components\LengowMarketplace;
use Lengow\Connector\Exception\LengowException;
use Lengow\Connector\Service\LengowAction;
use Lengow\Connector\Service\LengowConfiguration;
use Lengow\Connector\Service\LengowLog;
use Lengow\Connector\Service\LengowSync;

/**
 * Class LengowMarketplaceFactory
 * @package Lengow\Connector\Factory
 */
class LengowMarketplaceFactory
{
    /**
     * @var LengowSync Lengow sync service
     */
    private $lengowSync;

    /**
     * @var LengowLog Lengow log service
     */
    private $lengowLog;

    /**
     * @var LengowAction Lengow action service
     */
    private $lengowAction;

    /**
     * @var LengowConfiguration Lengow configuration service
     */
    private $lengowConfiguration;

    /**
     * @var array|false all marketplaces allowed for an account ID
     */
    private $marketplaces = false;

    /**
     * @var array marketplace registers
     */
    private $registers;

    /**
     * LengowMarketplaceFactory Construct
     *
     * @param LengowSync $lengowSync Lengow sync service
     * @param LengowLog $lengowLog Lengow log service
     * @param LengowAction $lengowAction Lengow action service
     * @param LengowConfiguration $lengowConfiguration Lengow configuration service
     */
    public function __construct(
        LengowSync $lengowSync,
        LengowLog $lengowLog,
        LengowAction $lengowAction,
        LengowConfiguration $lengowConfiguration
    )
    {
        $this->lengowSync = $lengowSync;
        $this->lengowLog = $lengowLog;
        $this->lengowAction = $lengowAction;
        $this->lengowConfiguration = $lengowConfiguration;
    }

    /**
     * Create a new LengowMarketplace instance
     *
     * @param string $marketplaceName Lengow marketplace name
     *
     * @throws LengowException
     * @return LengowMarketplace
     */
    public function create(string $marketplaceName): LengowMarketplace
    {
        $this->loadApiMarketplace();
        if (!isset($this->marketplaces->{$marketplaceName})) {
            throw new LengowException(
                $this->lengowLog->encodeMessage('lengow_log.exception.marketplace_not_present', [
                    'marketplace_name' => $marketplaceName
                ])
            );
        }
        if (!isset($this->registers[$marketplaceName])) {
            $this->registers[$marketplaceName] = new LengowMarketplace(
                $marketplaceName,
                $this->marketplaces->{$marketplaceName},
                $this->lengowAction,
                $this->lengowLog,
                $this->lengowConfiguration
            );
        }
        return $this->registers[$marketplaceName];
    }

    /**
     * Load the json configuration of all marketplaces
     */
    private function loadApiMarketplace(): void
    {
        if (!$this->marketplaces) {
            $this->marketplaces = $this->lengowSync->getMarketplaces();
        }
    }
}
