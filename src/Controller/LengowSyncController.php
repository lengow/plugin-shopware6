<?php declare(strict_types=1);

namespace Lengow\Connector\Controller;

use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Test\CustomField\CustomFieldRepositoryTest;
use Shopware\Core\System\CustomField\CustomFieldEntity;
use Shopware\Core\System\CustomField\CustomFieldTypes;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\JsonResponse;
use Lengow\Connector\Service\LengowConfiguration;
use Lengow\Connector\Service\LengowSync;
use Lengow\Connector\Util\EnvironmentInfoProvider;

/**
 * Class LengowSyncController
 * @package Lengow\Connector\Controller
 * @Route(defaults={"_routeScope"={"api"}})
 */
class LengowSyncController extends AbstractController
{
    /**
     * @var LengowConfiguration $lengowConfiguration Lengow configuration service
     */
    private $lengowConfiguration;

    /**
     * @var LengowSync $lengowSync Lengow synchronisation service
     */
    private $lengowSync;

    /**
     * @var EnvironmentInfoProvider Environment info provider utility
     */
    private $environmentInfoProvider;

    /**
     * LengowSyncController constructor
     *
     * @param LengowConfiguration $lengowConfiguration Lengow configuration service
     * @param LengowSync $lengowSync lengow sync service
     * @param EnvironmentInfoProvider $environmentInfoProvider Environment info provider utility
     */
    public function __construct(
        LengowConfiguration $lengowConfiguration,
        LengowSync $lengowSync,
        EnvironmentInfoProvider $environmentInfoProvider
    )
    {
        $this->lengowConfiguration = $lengowConfiguration;
        $this->lengowSync = $lengowSync;
        $this->environmentInfoProvider = $environmentInfoProvider;
    }

    /**
     * Get plugin data
     *
     * @Route("/api/_action/lengow/sync/get-plugin-data",
     *      name="api.action.lengow.sync.get-plugin-data",
     *      methods={"GET"})
     * @Route("/api/v{version}/_action/lengow/sync/get-plugin-data",
     *      name="api.action.lengow.sync.get-plugin-data-old",
     *      methods={"GET"})
     *
     * @return JsonResponse
     */
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

    /**
     * Get plugin links
     *
     * @Route("/api/_action/lengow/sync/get-plugin-links",
     *      name="api.action.lengow.sync.get-plugin-links",
     *      methods={"GET"})
     * @Route("/api/v{version}/_action/lengow/sync/get-plugin-links",
     *      name="api.action.lengow.sync.get-plugin-links-old",
     *      methods={"GET"})
     *
     * @return JsonResponse
     */
    public function getPluginLinks(): JsonResponse
    {
        return new JsonResponse([
            'success' => true,
            'links' => $this->lengowSync->getPluginLinks($this->environmentInfoProvider->getLocaleCode()),
        ]);
    }

    /**
     * Get Account data
     *
     * @Route("/api/_action/lengow/sync/get-account-status",
     *     name="api.action.lengow.sync.get-account-status",
     *     methods={"GET"})
     * @Route("/api/v{version}/_action/lengow/sync/get-account-status",
     *     name="api.action.lengow.sync.get-account-status-old",
     *     methods={"GET"})
     *
     * @param Request $request
     *
     * @return JsonResponse
     */
    public function getAccountStatus(Request $request): JsonResponse
    {
        if ($request->get('force') && $request->get('force') === 'true') {
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

    /**
     * Set back the display date of the update modal by 7 days
     *
     * @Route("/api/v{version}/_action/lengow/sync/remind-me-later",
     *     name="api.action.lengow.sync.remind-me-later",
     *     methods={"GET"})
     * @Route("/api/_action/lengow/sync/remind-me-later",
     *     name="api.action.lengow.sync.remind-me-later-old",
     *     methods={"GET"})
     *
     * @return JsonResponse
     */
    public function remindMeLater(): JsonResponse
    {
        $timestamp = time() + (7 * 86400);
        $this->lengowConfiguration->set(LengowConfiguration::LAST_UPDATE_PLUGIN_MODAL, (string) $timestamp);
        return new JsonResponse(['success' => true]);
    }

    /**
     * Checks if the plugin update modal should be displayed or not
     *
     * @return bool
     */
    private function showUpdateModal(): bool
    {
        $updatedAt = $this->lengowConfiguration->get(LengowConfiguration::LAST_UPDATE_PLUGIN_MODAL);
        if ($updatedAt !== null && (time() - (int) $updatedAt) < 86400) {
            return false;
        }
        $this->lengowConfiguration->set(LengowConfiguration::LAST_UPDATE_PLUGIN_MODAL, (string) time());
        return true;
    }

    /**
     * Create custom field
     *
     * @Route("/api/_action/lengow/sync/create-custom-field",
     *      name="api.action.lengow.sync.create-custom-field",
     *      methods={"GET"})
     * @Route("/api/v{version}/_action/lengow/sync/create-custom-field",
     *      name="api.action.lengow.sync.create-custom-field-old",
     *      methods={"GET"})
     *
     * @param Request $request
     * @param Context $context
     * @return JsonResponse
     */
    public function askToCreateCustomField(Context $context): JsonResponse
    {
        try {
            $success = $this->createCustomField($context, $this->lengowConfiguration);
            if ($success) {
                return new JsonResponse(['success' => false, 'message' => 'Erreur lors de la création du champ personnalisé'], 500);
            }

            return new JsonResponse(['success' => true]);
        } catch (\Exception $e) {
            return new JsonResponse(['success' => false, 'message' => $e->getMessage()], 500);
        } catch (NotFoundExceptionInterface | ContainerExceptionInterface $e) {
            return new JsonResponse(['success' => false, 'message' => 'Erreur lors de l\'accès au conteneur de services'], 500);
        }
    }

    /**
     * Create custom field
     *
     * @param Context $context
     * @param LengowConfiguration $config
     * @return bool True si la création du champ personnalisé a réussi, sinon false
     * @throws \Exception Si une erreur se produit lors de la création ou de la suppression du champ personnalisé
     */
    public function createCustomField(Context $context, LengowConfiguration $config): bool
    {
        /** @var EntityRepository $customFieldSetRepository */
        $customFieldSetRepository = $this->container->get('custom_field_set.repository');
        /** @var EntityRepository $customFieldGetRepository */
        $customFieldGetRepository = $this->container->get('custom_field.repository');
        if (!$config->isSendReturnTrackingNumberEnabled()) {
            $paymentCriteria = (new Criteria())->addFilter(new EqualsFilter('name', 'return_tracking_number'));
            $existingCustomField = $customFieldGetRepository->searchIds($paymentCriteria, $context);

            if ($existingCustomField->getTotal() === 0) {
                try {
                    $customFieldSetRepository->create([
                        [
                            'name' => 'Lengow_Connector_set',
                            'config' => [
                                'label' => [
                                    'en-GB' => 'Return tracking number',
                                    'de-DE' => 'Rücksendekontrollnummer',
                                    Defaults::LANGUAGE_SYSTEM => 'Return tracking number'
                                ]
                            ],
                            'relations' => [
                                [
                                    'entityName' => 'order'
                                ]
                            ],
                            'customFields' => [
                                [
                                    'name' => 'return_tracking_number',
                                    'type' => CustomFieldTypes::TEXT,
                                    'config' => [
                                        'label' => [
                                            'en-GB' => 'Return Tracking Number',
                                            'de-DE' => 'Rücksendungsverfolgungsnummer',
                                            Defaults::LANGUAGE_SYSTEM => 'Return tracking number'
                                        ],
                                        'customFieldPosition' => 1
                                    ]
                                ]
                            ]
                        ]
                    ], $context);
                    return false;
                } catch (\Exception $e) {
                    throw new \Exception('Erreur lors de la création du champ personnalisé : ' . $e->getMessage());
                }
            }
        } else {
            $paymentCriteria = (new Criteria())->addFilter(new EqualsFilter('name', "Lengow_Connector_set"));
            $existingCustomField = $customFieldSetRepository->searchIds($paymentCriteria, $context);
            if ($existingCustomField->getTotal() > 0) {
                try {
                    $customFieldIds = $existingCustomField->getIds();

                    foreach ($customFieldIds as $customFieldId) {
                        $customFieldSetRepository->delete([['id' => $customFieldId]], $context);
                    }
                    echo 'Champ personnalisé supprimé avec succès:' . json_encode($customFieldIds, JSON_THROW_ON_ERROR);
                } catch (\Exception $e) {
                    // La suppression du champ personnalisé a échoué, lever une exception avec le message d'erreur
                    throw new \Exception('Erreur lors de la suppression du champ personnalisé : ' . $e->getMessage());
                }
            }
        }
        return false;
    }
}
