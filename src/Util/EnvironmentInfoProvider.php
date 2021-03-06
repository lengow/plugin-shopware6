<?php declare(strict_types=1);

namespace Lengow\Connector\Util;

use Shopware\Core\Checkout\Payment\PaymentMethodCollection;
use Shopware\Core\Checkout\Payment\PaymentMethodEntity;
use Shopware\Core\Kernel;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\System\SalesChannel\Aggregate\SalesChannelDomain\SalesChannelDomainCollection;
use Shopware\Core\System\User\UserCollection;
use Shopware\Core\System\User\UserEntity;
use Lengow\Connector\LengowConnector;

/**
 * Class EnvironmentInfoProvider
 * @package Lengow\Connector\Util
 */
class EnvironmentInfoProvider
{
    /* Lengow plugin folders */
    public const FOLDER_CONFIG = 'Config';
    public const FOLDER_EXPORT = 'Export';
    public const FOLDER_LOG = 'Logs';

    /**
     * @var string Default locale code
     */
    public const DEFAULT_LOCALE_CODE = 'en-GB';

    /**
     * @var string plugin name
     */
    public const PLUGIN_NAME = 'LengowConnector';

    /**
     * @var string plugin version
     */
    public const PLUGIN_VERSION = '1.0.2';

    /**
     * @var Kernel $kernel
     */
    private $kernel;

    /**
     * @var EntityRepositoryInterface user repository
     */
    private $userRepository;

    /**
     * @var EntityRepositoryInterface sales channel repository
     */
    private $salesChannelRepository;

    /**
     * @var EntityRepositoryInterface sales channel domain repository
     */
    private $salesChannelDomainRepository;

    /**
     * @var EntityRepositoryInterface payment method repository
     */
    private $paymentMethodRepository;

    /**
     * EnvironmentInfoProvider Construct
     *
     * @param Kernel $kernel Shopware kernel
     * @param EntityRepositoryInterface $userRepository user repository
     * @param EntityRepositoryInterface $salesChannelRepository sales channel repository
     * @param EntityRepositoryInterface $salesChannelDomainRepository sales channel domain repository
     * @param EntityRepositoryInterface $paymentMethodRepository payment method repository
     */
    public function __construct(
        Kernel $kernel,
        EntityRepositoryInterface $userRepository,
        EntityRepositoryInterface $salesChannelRepository,
        EntityRepositoryInterface $salesChannelDomainRepository,
        EntityRepositoryInterface $paymentMethodRepository
    )
    {
        $this->kernel = $kernel;
        $this->userRepository = $userRepository;
        $this->salesChannelRepository = $salesChannelRepository;
        $this->salesChannelDomainRepository = $salesChannelDomainRepository;
        $this->paymentMethodRepository = $paymentMethodRepository;
    }

    /**
     * Get current locale code
     *
     * @return string
     */
    public function getLocaleCode(): string
    {
        $context = Context::createDefaultContext();
        $criteria = new Criteria();
        $criteria->addAssociation('locale');
        /** @var UserCollection $userCollection */
        $userCollection = $this->userRepository->search($criteria, $context)->getEntities();
        if ($userCollection->count() !== 0) {
            /** @var UserEntity $user */
            $user = $userCollection->first();
            if ($user->getLocale()) {
                return $user->getLocale()->getCode();
            }
        }
        return self::DEFAULT_LOCALE_CODE;
    }

    /**
     * Get plugin path
     *
     * @return string
     */
    public function getPluginPath(): string
    {
        if ($this->kernel->getPluginLoader()->getPluginInstance(LengowConnector::class)) {
            return $this->kernel->getPluginLoader()->getPluginInstance(LengowConnector::class)->getPath();
        }
        return '';
    }

    /**
     * Get plugin base path
     *
     * @return string
     */
    public function getPluginBasePath(): string
    {
        return $this->kernel->getPluginLoader()->getPluginInstance(LengowConnector::class)->getBasePath();
    }

    /**
     * Get plugin dir path
     *
     * @return string
     */
    public function getPluginDir(): string
    {
        return str_replace($this->kernel->getProjectDir(), '', $this->getPluginPath());
    }

    /**
     * Get shopware version
     *
     * @return string
     */
    public function getVersion(): string
    {
        return $this->kernel->getContainer()->getParameter('kernel.shopware_version');
    }

    /**
     * Get plugin version
     *
     * @return string
     */
    public function getPluginVersion() : string
    {
        return self::PLUGIN_VERSION;
    }

    /**
     * Get the base url of the plugin
     *
     * @param string|null $salesChannelId Shopware sales channel id
     * @param string|null $languageId Shopware language id
     *
     * @return string|null
     */
    public function getBaseUrl(string $salesChannelId = null, string $languageId = null): ?string
    {
        $context = Context::createDefaultContext();
        $languageId = $languageId ?? $context->getLanguageId();
        /** @var SalesChannelDomainCollection $salesChannelDomainCollection */
        $salesChannelDomainCollection = $this->salesChannelDomainRepository->search(new Criteria(), $context)
            ->getEntities();
        if ($salesChannelDomainCollection->count() === 0) {
            return null;
        }
        if ($salesChannelId === null) {
            // get first domain available with default language
            $salesChannelDomain = $salesChannelDomainCollection->filterByProperty('languageId', $languageId)->first();
            $baseUrl = $salesChannelDomain
                ? $salesChannelDomain->getUrl()
                : $salesChannelDomainCollection->first()->getUrl();
        } else {
            // get domain by sales channel id and language id
            $salesChannelDomain = $salesChannelDomainCollection->filterByProperty('salesChannelId', $salesChannelId)
                ->filterByProperty('languageId', $languageId)
                ->first();
            if ($salesChannelDomain) {
                $baseUrl = $salesChannelDomain->getUrl();
            } else {
                // get domain by sales channel id
                $salesChannelDomain = $salesChannelDomainCollection->filterByProperty('salesChannelId', $salesChannelId)
                    ->first();
                $baseUrl = $salesChannelDomain ? $salesChannelDomain->getUrl() : null;
            }
            if ($baseUrl === null) {
                return $this->getBaseUrl();
            }
        }
        return $baseUrl;
    }

    /**
     * Get all active Shopware sales channels
     *
     * @return EntityCollection
     */
    public function getActiveSalesChannels(): EntityCollection
    {
        $context = Context::createDefaultContext();
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('active', true));
        return $this->salesChannelRepository->search($criteria, $context)->getEntities();
    }

    /**
     * Get Lengow payment method
     *
     * @return PaymentMethodEntity|null
     */
    public function getLengowPaymentMethod(): ?PaymentMethodEntity
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('plugin.name', self::PLUGIN_NAME));
        /** @var PaymentMethodCollection $paymentMethodCollection */
        $paymentMethodCollection = $this->paymentMethodRepository->search($criteria, Context::createDefaultContext())
            ->getEntities();
        if ($paymentMethodCollection->count() !== 0) {
            return $paymentMethodCollection->first();
        }
        return null;
    }

    /**
     * Get default shipping method for given salesChannel
     *
     * @param string $salesChannelId shopware sales channel id
     * @param EntityRepositoryInterface $shippingMethodRepository shopware shipping method repository
     *
     * @return string
     */
    public static function getShippingMethodDefaultValue(
        string $salesChannelId,
        EntityRepositoryInterface $shippingMethodRepository
    ): string
    {
        $shippingMethodCriteria = new Criteria();
        $shippingMethodCriteria->getAssociation('salesChannel')
            ->addFilter(new EqualsFilter('salesChannel.id', $salesChannelId));
        $result = $shippingMethodRepository
            ->search($shippingMethodCriteria, Context::createDefaultContext());
        if ($result->count() !== 0) {
            return $result->first()->getId();
        }
        return '';
    }
}
