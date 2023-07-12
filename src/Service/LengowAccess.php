<?php declare(strict_types=1);

namespace Lengow\Connector\Service;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\Uuid\Uuid;

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
    ];

    /**
     * @var LengowConfiguration configuration access service
     */
    private $lengowConfiguration;

    /**
     * @var EntityRepository $salesChannelRepository sales channel repository
     */
    private $salesChannelRepository;

    /**
     * LengowAccess constructor
     *
     * @param LengowConfiguration $lengowConfiguration configuration access service
     * @param EntityRepository $salesChannelRepository shopware sales channel repository
     */
    public function __construct(
        LengowConfiguration $lengowConfiguration,
        EntityRepository $salesChannelRepository
    )
    {
        $this->lengowConfiguration = $lengowConfiguration;
        $this->salesChannelRepository = $salesChannelRepository;
    }

    /**
     * Check if sales channel id exist
     *
     * @param string|null $salesChannelId sales channel id
     *
     * @return string|null
     */
    public function checkSalesChannel(string $salesChannelId = null): ?string
    {
        if ($salesChannelId === null || !Uuid::isValid($salesChannelId)) {
            return null;
        }
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('id', $salesChannelId));
        $result = $this->salesChannelRepository->search(
            $criteria,
            Context::createDefaultContext()
        );
        $salesChannels = $result->getEntities();
        if ($salesChannels->count() > 0) {
            return $salesChannels->first() ? $salesChannels->first()->getName() : null;
        }
        return null;
    }

    /**
     * Check webservice access by token or ip
     *
     * @param string|null $token Authorization token
     * @param string|null $salesChannelId sales channel id
     *
     * @return bool
     */
    public function checkWebserviceAccess(string $token = null, string $salesChannelId = null): bool
    {
        return $this->checkIp($_SERVER['REMOTE_ADDR'])
            || ($token
                && !$this->lengowConfiguration->get(LengowConfiguration::AUTHORIZED_IP_ENABLED)
                && $this->checkToken($token, $salesChannelId)
            );
    }

    /**
     * Check if server ip is authorised
     *
     * @param string $ip ip to check
     *
     * @return bool
     */
    public function checkIp(string $ip): bool
    {
        return in_array($ip, $this->getAuthorizedIps(), true);
    }

    /**
     * Get all authorized ips
     *
     * @return array
     */
    public function getAuthorizedIps(): array
    {
        $authorizedIps = [];
        $ips = $this->lengowConfiguration->get(LengowConfiguration::AUTHORIZED_IPS);
        $ipEnable = $this->lengowConfiguration->get(LengowConfiguration::AUTHORIZED_IP_ENABLED);
        if ($ipEnable && !empty($ips)) {
            foreach ($ips as $ip) {
                $authorizedIps[] = trim(str_replace(["\r\n", ',', '-', '|', ' ', '/'], ';', $ip), ';');
            }
        }
        return array_merge($authorizedIps, self::$ipsLengow);
    }

    /**
     * Check token access
     *
     * @param string $token client token
     * @param string|null $salesChannelId sales channel id
     *
     * @return bool
     */
    public function checkToken(string $token, string $salesChannelId = null): bool
    {
        $configToken = $this->lengowConfiguration->getToken($salesChannelId);
        if ($token && !empty($configToken)) {
            return $configToken === $token;
        }
        return false;
    }
}
