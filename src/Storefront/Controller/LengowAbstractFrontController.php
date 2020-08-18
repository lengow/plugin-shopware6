<?php declare(strict_types=1);

namespace Lengow\Connector\Storefront\Controller;

use Shopware\Storefront\Controller\StorefrontController;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;
use Lengow\Connector\Service\LengowAccess;
use Lengow\Connector\Service\LengowConfiguration;
use Lengow\Connector\Service\LengowMessage;
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
     * @var LengowMessage Lengow message service
     */
    protected $lengowMessage;

    /**
     * LengowAbstractFrontController constructor.
     *
     * @param LengowAccess $lengowAccess Lengow access security service
     * @param LengowConfiguration $lengowConfiguration Lengow configuration accessor service
     * @param LengowMessage $lengowMessage Lengow message service
     */
    public function __construct(
        LengowAccess $lengowAccess,
        LengowConfiguration $lengowConfiguration,
        LengowMessage $lengowMessage
    )
    {
        $this->lengowAccessService = $lengowAccess;
        $this->lengowConfiguration = $lengowConfiguration;
        $this->lengowMessage = $lengowMessage;
    }

    /**
     * @param Request $request Http request
     * @param SalesChannelContext $context Shopware context
     * @param bool $import is call for import or export
     */
    public function checkAccess(Request $request, SalesChannelContext $context, $import = true): void
    {
        $token = $request->query->get('token');
        $salesChannelId = $request->query->get('sales_channel_id');
        if ($import || !$salesChannelId || strlen($salesChannelId) <= 1) {
            $salesChannelId = null;
        }

        if (!$import && !$this->lengowAccessService->checkSalesChannel($salesChannelId)) {
            header('HTTP/1.1 400 Bad Request');
            die(
                $this->lengowMessage->decode(
                    'log.export.specify_sales_channel',
                    LengowTranslation::DEFAULT_ISO_CODE
                )
            );
        }

        if (!$this->lengowAccessService->checkWebserviceAccess($token, $salesChannelId)) {
            if ($this->lengowConfiguration->get('AuthorizedIpListCheckbox')) {
                $errorMessage = $this->lengowMessage->decode(
                    'log.export.unauthorised_ip',
                    LengowTranslation::DEFAULT_ISO_CODE,
                    ['ip' => $_SERVER['REMOTE_ADDR']]
                );
            } else {
                $errorMessage = ($token && $token !== '')
                    ? $this->lengowMessage->decode(
                        'log.export.unauthorised_token',
                        LengowTranslation::DEFAULT_ISO_CODE,
                        ['token' => $token]
                    )
                    : $this->lengowMessage->decode(
                        'log.export.empty_token',
                        LengowTranslation::DEFAULT_ISO_CODE
                    );
            }
            header('HTTP/1.1 403 Forbidden');
            die($errorMessage);
        }
    }

    /**
     * @param Request $request Http request
     *
     * @return array All get parameters in an array
     */
    abstract protected function createGetArgArray(Request $request): array;
}