<?php declare(strict_types=1);

namespace Lengow\Connector\Service;

use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\System\SystemConfig\SystemConfigService;

/**
 * Class LengowAccess
 * @package Lengow\Connector\Service
 */
class LengowAccess
{
    /**
     * @var array Lengow Authorized IPs
     */
    protected static $ipsLengow = [
        '127.0.0.1',
        '10.0.4.150',
        '46.19.183.204',
        '46.19.183.217',
        '46.19.183.218',
        '46.19.183.219',
        '46.19.183.222',
        '52.50.58.130',
        '89.107.175.172',
        '89.107.175.185',
        '89.107.175.186',
        '89.107.175.187',
        '90.63.241.226',
        '109.190.189.175',
        '146.185.41.180',
        '146.185.41.177',
        '185.61.176.129',
        '185.61.176.130',
        '185.61.176.131',
        '185.61.176.132',
        '185.61.176.133',
        '185.61.176.134',
        '185.61.176.137',
        '185.61.176.138',
        '185.61.176.139',
        '185.61.176.140',
        '185.61.176.141',
        '185.61.176.142',
        '172.18.0.3', // TODO REMOVE DEV PURPOSE
    ];

    /**
     * @var EntityRepositoryInterface $settingsRepository Lengow settings access
     */
    private $settingsRepository; // TODO use lengowConfig

    /**
     * @var SystemConfigService $systemConfigService Shopware settings access
     */
    private $systemConfigService; // TODO use lengowConfig

    /**
     * LengowAccess constructor
     *
     * @param EntityRepositoryInterface $settingsRepository Lengow settings access
     * @param SystemConfigService $systemConfigService Shopware settings access
     */
    public function __construct(EntityRepositoryInterface $settingsRepository, SystemConfigService $systemConfigService)
    {
        $this->settingsRepository = $settingsRepository; // TODO use lengowConfig
        $this->systemConfigService = $systemConfigService; // TODO use lengowConfig
    }

    /**
     * @param null $salesChannelId sales channel id
     *
     * @return bool
     */
    public function handleSalesChannel($salesChannelId = null): bool
    {
        if ($salesChannelId === null) {
            return false;
        }
        // TODO try to find the shop (howto is still a riddle)
        return true;
    }

    /**
     * @param string $token Authorization token
     * @param null $salesChannelId sales channel id
     *
     * @return bool
     */
    public function checkWebserviceAccess(string $token, $salesChannelId = null) : bool
    {
        if ($this->checkIp($_SERVER['REMOTE_ADDR'])
            || (!$this->systemConfigService->get('Connector.config.AuthorizedIpListCheckbox') // TODO use lengowConfig
                 && $this->checkToken($token, $salesChannelId))) {
            return true;
        }

        return false;
    }

    /**
     * @param string $ip ip to check
     *
     * @return bool
     */
    public function checkIp(string $ip) : bool
    {
        if (in_array($ip, $this->getAuthorizedIps(), true)) {
            return true;
        }
        return false;
    }

    /**
     * @return array authorized ip
     */
    public function getAuthorizedIps() : array
    {
        $ips = $this->systemConfigService->get('Connector.config.AuthorizedIpList'); // TODO use lengowConfig
        $ipEnable = $this->systemConfigService->get('Connector.config.AuthorizedIpListCheckbox'); // TODO use lengowConfig
        if ($ipEnable && $ips !== null) {
            $ips = trim(str_replace(["\r\n", ',', '-', '|', ' '], ';', $ips), ';');
            $ips = explode(';', $ips);
            $authorizedIps = array_merge($ips, self::$ipsLengow);
        } else {
            $authorizedIps = self::$ipsLengow;
        }
        return $authorizedIps;
    }

    /**
     * @param $token client token
     * @param null $salesChannelId sales channel id
     *
     * @return bool
     */
    public function checkToken($token, $salesChannelId = null) : bool
    {
        // TODO ici si le token n'existe pas encore on le créé
        // TODO on va aussi créer un token pour tout les autres shops & un global.
        $globalTokenEntity = $this->settingsRepository->search( // TODO use lengowConfig
            (new Criteria())->addFilter(new EqualsFilter('name', 'lengowGlobalToken')),
            \Shopware\Core\Framework\Context::createDefaultContext()
        );
        $results = (array) $globalTokenEntity->getEntities()->getElements();
        if (!empty($results)) {
            return $results[array_key_first($results)]->getValue() === $token;
        }
        return false;
    }
}
