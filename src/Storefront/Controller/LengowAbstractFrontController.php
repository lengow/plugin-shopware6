<?php declare(strict_types=1);

namespace Lengow\Connector\Storefront\Controller;

use Shopware\Storefront\Controller\StorefrontController;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;
use Lengow\Connector\Service\LengowAccess;
use Lengow\Connector\Service\LengowConfiguration;

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
     * LengowAbstractFrontController constructor.
     *
     * @param LengowAccess $lengowAccess Lengow access security service
     * @param LengowConfiguration $lengowConfiguration Lengow configuration accessor service
     */
    public function __construct(LengowAccess $lengowAccess, LengowConfiguration $lengowConfiguration)
    {
        $this->lengowAccessService = $lengowAccess;
        $this->lengowConfiguration = $lengowConfiguration;
    }

    /**
     * @param Request $request Http request
     * @param SalesChannelContext $context Shopware context
     * @param bool $import is call for import or export
     */
    public function checkAccess(Request $request, SalesChannelContext $context, $import = true) : void {

        $token = $request->query->get('token');
        $salesChannelId = $request->query->get('sales_channel_id');
        if (!$salesChannelId || strlen($salesChannelId) <= 1) {
            $salesChannelId = null;
        }

        if (!$import && !$this->lengowAccessService->checkSalesChannel($salesChannelId)) {
            header('HTTP/1.1 400 Bad Request');
            die('no sales channel specified or invalid sales channel uuid'); // TODO add trad here
        }

        if (!$this->lengowAccessService->checkWebserviceAccess($token, $salesChannelId, $import))
        {
            if ($this->lengowConfiguration->get('AuthorizedIpListCheckbox')) {
                $errorMessage = 'unauthorised IP: ' . $_SERVER['REMOTE_ADDR'];
            } else {
                $errorMessage = ($token && $token !== '')
                    ? 'unauthorised access for this token: ' . $token
                    : 'unauthorised access: token parameter is empty'; // TODO add trad here
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