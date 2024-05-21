<?php

declare(strict_types=1);

namespace Lengow\Connector\Controller;

use JsonException;
use Shopware\Core\Checkout\Shipping\ShippingMethodEntity;
use Lengow\Connector\Components\LengowMarketplace;
use Lengow\Connector\Exception\LengowException;
use Lengow\Connector\Factory\LengowMarketplaceFactory;
use Lengow\Connector\Service\LengowAction;
use Lengow\Connector\Service\LengowConfiguration;
use Lengow\Connector\Service\LengowOrder;
use Lengow\Connector\Service\LengowSync;
use Lengow\Connector\Util\EnvironmentInfoProvider;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\System\CustomField\CustomFieldTypes;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

#[Route(defaults: ['_routeScope' => ['api']])]
class LengowSyncController extends AbstractController
{
    /**
     * @var LengowConfiguration Lengow configuration service
     */
    private $lengowConfiguration;

    /**
     * @var LengowSync Lengow synchronisation service
     */
    private $lengowSync;

    /**
     * @var EnvironmentInfoProvider Environment info provider utility
     */
    private $environmentInfoProvider;

    /**
     * @var LengowMarketplace Lengow marketplace instance
     */
    private $lengowMarketplace;

    /**
     * @var LengowMarketplaceFactory Lengow marketplace factory
     */
    private $lengowMarketplaceFactory;

    /**
     * LengowSyncController constructor.
     *
     * @param LengowConfiguration     $lengowConfiguration     Lengow configuration service
     * @param LengowSync              $lengowSync              lengow sync service
     * @param EnvironmentInfoProvider $environmentInfoProvider Environment info provider utility
     */
    public function __construct(
        LengowConfiguration $lengowConfiguration,
        LengowSync $lengowSync,
        EnvironmentInfoProvider $environmentInfoProvider,
        LengowMarketplaceFactory $lengowMarketplaceFactory
    ) {
        $this->lengowConfiguration = $lengowConfiguration;
        $this->lengowSync = $lengowSync;
        $this->environmentInfoProvider = $environmentInfoProvider;
        $this->lengowMarketplaceFactory = $lengowMarketplaceFactory;
    }

    //Get plugin data
    #[Route('/api/_action/lengow/sync/get-plugin-data', name: 'api.action.lengow.sync.get-plugin-data', methods: ['GET'])]
    #[Route('/api/v{version}/_action/lengow/sync/get-plugin-data', name: 'api.action.lengow.sync.get-plugin-data-old', methods: ['GET'])]
    public function getPluginData(): JsonResponse
    {
        $pluginData = $this->lengowSync->getPluginData();
        if (!$pluginData) {
            return new JsonResponse(['success' => false]);
        }
        $newVersionIsAvailable = version_compare(EnvironmentInfoProvider::PLUGIN_VERSION, $pluginData['version'], '<');
        $pluginData['new_version_is_available'] = $newVersionIsAvailable;
        $pluginData['show_update_modal'] = $newVersionIsAvailable && $this->showUpdateModal();
        $pluginData['links'] = $this->lengowSync->getPluginLinks($this->environmentInfoProvider->getLocaleCode());

        return new JsonResponse([
            'success' => true,
            'plugin_data' => $pluginData,
        ]);
    }

    //Get plugin links
    #[Route('/api/_action/lengow/sync/get-plugin-links', name: 'api.action.lengow.sync.get-plugin-links', methods: ['GET'])]
    #[Route('/api/v{version}/_action/lengow/sync/get-plugin-links', name: 'api.action.lengow.sync.get-plugin-links-old', methods: ['GET'])]
    public function getPluginLinks(): JsonResponse
    {
        return new JsonResponse([
            'success' => true,
            'links' => $this->lengowSync->getPluginLinks($this->environmentInfoProvider->getLocaleCode()),
        ]);
    }

    //Get Account data
    #[Route('/api/_action/lengow/sync/get-account-status', name: 'api.action.lengow.sync.get-account-status', methods: ['GET'])]
    #[Route('/api/v{version}/_action/lengow/sync/get-account-status', name: 'api.action.lengow.sync.get-account-status-old', methods: ['GET'])]
    public function getAccountStatus(Request $request): JsonResponse
    {
        if ($request->get('force') && 'true' === $request->get('force')) {
            $accountStatus = $this->lengowSync->getAccountStatus(true);
        } else {
            $accountStatus = $this->lengowSync->getAccountStatus();
        }
        if (!$accountStatus) {
            return new JsonResponse(['success' => false]);
        }
        $accountStatus['success'] = true;

        return new JsonResponse($accountStatus);
    }

    //Set back the display date of the update modal by 7 days
    #[Route('/api/v{version}/_action/lengow/sync/remind-me-later', name: 'api.action.lengow.sync.remind-me-later', methods: ['GET'])]
    #[Route('/api/_action/lengow/sync/remind-me-later', name: 'api.action.lengow.sync.remind-me-later-old', methods: ['GET'])]
    public function remindMeLater(): JsonResponse
    {
        $timestamp = time() + (7 * 86400);
        $this->lengowConfiguration->set(LengowConfiguration::LAST_UPDATE_PLUGIN_MODAL, (string) $timestamp);

        return new JsonResponse(['success' => true]);
    }

    /**
     * Checks if the plugin update modal should be displayed or not.
     */
    private function showUpdateModal(): bool
    {
        $updatedAt = $this->lengowConfiguration->get(LengowConfiguration::LAST_UPDATE_PLUGIN_MODAL);
        if (null !== $updatedAt && (time() - (int) $updatedAt) < 86400) {
            return false;
        }
        $this->lengowConfiguration->set(LengowConfiguration::LAST_UPDATE_PLUGIN_MODAL, (string) time());

        return true;
    }

    //Load return tracking numbers for a specific order ID
    #[Route('/api/_action/lengow/sync/load-return-tracking-numbers', name: 'api.action.lengow.sync.load-return-tracking-numbers', methods: ['POST'])]
    #[Route('/api/v{version}/_action/lengow/sync/load-return-tracking-numbers', name: 'api.action.lengow.sync.load-return-tracking-numbers-old', methods: ['POST'])]
    public function loadReturnTrackingNumbers(Request $request, LengowOrder $lengowOrder): JsonResponse
    {
        $orderId = $request->request->get('order_id');

        $returnTrackingNumber = $lengowOrder->getReturnTrackingNumberByOrderId($orderId);

        return new JsonResponse(['success' => true, 'return_tracking_number' => $returnTrackingNumber]);
    }

    //Save return tracking numbers for a specific order ID
    #[Route('/api/_action/lengow/sync/save-return-tracking-numbers', name: 'api.action.lengow.sync.save-return-tracking-numbers', methods: ['POST'])]
    #[Route('/api/v{version}/_action/lengow/sync/save-return-tracking-numbers', name: 'api.action.lengow.sync.save-return-tracking-numbers-old', methods: ['POST'])]
    public function saveReturnTrackingNumbers(Request $request, LengowOrder $lengowOrder): JsonResponse
    {
        $data = json_decode($request->getContent(), true, 512, JSON_THROW_ON_ERROR);

        if (!is_array($data) || !isset($data['order_id']) || !isset($data['return_tracking_numbers'])) {
            return new JsonResponse("Error");
        }

        $orderId = $data['order_id'];
        $returnTrackingNumbers = json_encode($data['return_tracking_numbers']);

        $lengowOrder->updateReturnTrackingNumber($orderId, $returnTrackingNumbers);

        return new JsonResponse(['success' => true]);
    }

    //Load return carrier for a specific order ID
    #[Route('/api/_action/lengow/sync/load-return-carrier', name: 'api.action.lengow.sync.load-return-carrier', methods: ['POST'])]
    #[Route('/api/v{version}/_action/lengow/sync/load-return-carrier', name: 'api.action.lengow.sync.load-return-carrier-old', methods: ['POST'])]
    public function loadReturnCarrier(Request $request, LengowOrder $lengowOrder): JsonResponse
    {
        $orderId = $request->request->get('order_id');

        $returnCarrier = $lengowOrder->getReturnCarrierByOrderId($orderId);

        return new JsonResponse(['success' => true, 'return_carrier' => $returnCarrier]);
    }

    //Save return carrier for a specific order ID
    #[Route('/api/_action/lengow/sync/save-return-carrier', name: 'api.action.lengow.sync.save-return-carrier', methods: ['POST'])]
    #[Route('/api/v{version}/_action/lengow/sync/save-return-carrier', name: 'api.action.lengow.sync.save-return-carrier-old', methods: ['POST'])]
    public function saveReturnCarrier(Request $request, LengowOrder $lengowOrder): JsonResponse
    {
        $data = json_decode($request->getContent(), true, 512, JSON_THROW_ON_ERROR);

        if (!is_array($data) || !isset($data['order_id']) || !isset($data['return_carrier'])) {
            return new JsonResponse($data);
        }

        $orderId = $data['order_id'];
        $returnCarrier = json_encode($data['return_carrier']);
        $lengowOrder->updateReturnCarrier($orderId, $returnCarrier);

        return new JsonResponse(['success' => $data]);
    }

    //Check the marketplace of the order by order ID
    #[Route('/api/_action/lengow/sync/verifyArgRtn', name: 'api.action.lengow.sync.verifyArgRtn', methods: ['POST'])]
    #[Route('/api/v{version}/_action/lengow/sync/verifyArgRtn', name: 'api.action.lengow.sync.verifyArgRtn-old', methods: ['POST'])]
    public function verifyMarketplaceByOrderId(Request $request, LengowOrder $lengowOrder): JsonResponse
    {
        $data = json_decode($request->getContent(), true, 512, JSON_THROW_ON_ERROR);

        if (!is_array($data) || !isset($data['order_id'])) {
            return new JsonResponse(['success' => false, 'message' => 'Invalid request']);
        }

        $orderId = $data['order_id'];

        $order = $lengowOrder->getLengowOrderByOrderId((string)$orderId);
        $action = LengowAction::TYPE_SHIP;
        $lengowMarketplace = $this->lengowMarketplaceFactory->create($order->getMarketplaceName());
        $arg = $lengowMarketplace->getMarketplaceArguments($action);

        $returnTrackingNumberExists = array_key_exists('return_tracking_number', $arg);
        $returnCarrierExists = array_key_exists('return_carrier', $arg);

        return new JsonResponse([
            'success' => true,
            'return_tracking_number_exists' => $returnTrackingNumberExists,
            'return_carrier_exists' => $returnCarrierExists,
        ]);
    }

}
