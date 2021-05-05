<?php declare(strict_types=1);

namespace Lengow\Connector\Storefront\Controller;

use Shopware\Storefront\Controller\StorefrontController;
use Symfony\Component\HttpFoundation\Request;
use Lengow\Connector\Service\LengowAccess;
use Lengow\Connector\Service\LengowConfiguration;
use Lengow\Connector\Service\LengowExport;
use Lengow\Connector\Service\LengowLog;
use Lengow\Connector\Service\LengowTranslation;

/**
 * Class LengowAbstractFrontController
 * @package Lengow\Connector\Storefront\Controller
 */
abstract class LengowAbstractFrontController extends StorefrontController
{
    /**
     * @var LengowAccess Lengow access security service
     */
    protected $lengowAccessService;

    /**
     * @var LengowConfiguration Lengow configuration accessor service
     */
    protected $lengowConfiguration;

    /**
     * @var LengowLog Lengow log service
     */
    protected $lengowLog;

    /**
     * LengowAbstractFrontController constructor
     *
     * @param LengowAccess $lengowAccess Lengow access security service
     * @param LengowConfiguration $lengowConfiguration Lengow configuration accessor service
     * @param LengowLog $lengowLog Lengow log service
     */
    public function __construct(
        LengowAccess $lengowAccess,
        LengowConfiguration $lengowConfiguration,
        LengowLog $lengowLog
    )
    {
        $this->lengowAccessService = $lengowAccess;
        $this->lengowConfiguration = $lengowConfiguration;
        $this->lengowLog = $lengowLog;
    }

    /**
     * Check access by token or ip
     *
     * @param Request $request Http request
     *
     * @return string|null
     */
    public function checkAccess(Request $request): ?string
    {
        $errorMessage = null;
        $token = $request->query->get(LengowExport::PARAM_TOKEN);
        $salesChannelId = $request->query->get(LengowExport::PARAM_SALES_CHANNEL_ID);
        if (!$this->lengowAccessService->checkWebserviceAccess($token, $salesChannelId)) {
            if ($this->lengowConfiguration->get(LengowConfiguration::AUTHORIZED_IP_ENABLED)) {
                $errorMessage = $this->lengowLog->decodeMessage(
                    'log.export.unauthorised_ip',
                    LengowTranslation::DEFAULT_ISO_CODE,
                    ['ip' => $_SERVER['REMOTE_ADDR']]
                );
            } else {
                $errorMessage = ($token && $token !== '')
                    ? $this->lengowLog->decodeMessage(
                        'log.export.unauthorised_token',
                        LengowTranslation::DEFAULT_ISO_CODE,
                        ['token' => $token]
                    )
                    : $this->lengowLog->decodeMessage(
                        'log.export.empty_token',
                        LengowTranslation::DEFAULT_ISO_CODE
                    );
            }
        }
        return $errorMessage;
    }

    /**
     * Get sale channel name from sales channel id
     *
     * @param Request $request Http request
     *
     * @return string|null
     */
    public function getSalesChannelName(Request $request): ?string
    {
        $salesChannelId = $request->query->get(LengowExport::PARAM_SALES_CHANNEL_ID);
        if (!$salesChannelId || strlen($salesChannelId) <= 1) {
            $salesChannelId = null;
        }
        return $this->lengowAccessService->checkSalesChannel($salesChannelId);
    }

    /**
     * @param Request $request Http request
     *
     * @return array
     */
    abstract protected function createGetArgArray(Request $request): array;
}
