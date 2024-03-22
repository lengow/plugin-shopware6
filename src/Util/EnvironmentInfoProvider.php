<?php declare(strict_types=1);

namespace Lengow\Connector\Util;

use Shopware\Core\Checkout\Payment\PaymentMethodCollection;
use Shopware\Core\Checkout\Payment\PaymentMethodEntity;
use Shopware\Core\Kernel;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
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

    /* Lengow actions controller */
    public const ACTION_EXPORT = 'export';
    public const ACTION_CRON = 'cron';
    public const ACTION_TOOLBOX = 'toolbox';

    /* Field database actions */
    public const FIELD_REQUIRED = 'required';
    public const FIELD_CAN_BE_UPDATED = 'updated';

    /* Date formats */
    public const DATE_FULL = 'Y-m-d H:i:s';
    public const DATE_DAY = 'Y-m-d';
    public const DATE_ISO_8601 = 'c';

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
    public const PLUGIN_VERSION = '1.2.1';

    /**
     * @var string Name of Lengow front controller
     */
    public const LENGOW_CONTROLLER = 'lengow';

    /**
     * @var Kernel $kernel
     */
    private $kernel;

    /**
     * @var EntityRepository user repository
     */
    private $userRepository;

    /**
     * @var EntityRepository sales channel repository
     */
    private $salesChannelRepository;

    /**
     * @var EntityRepository sales channel domain repository
     */
    private $salesChannelDomainRepository;

    /**
     * @var EntityRepository payment method repository
     */
    private $paymentMethodRepository;

    /**
     * EnvironmentInfoProvider Construct
     *
     * @param Kernel $kernel Shopware kernel
     * @param EntityRepository $userRepository user repository
     * @param EntityRepository $salesChannelRepository sales channel repository
     * @param EntityRepository $salesChannelDomainRepository sales channel domain repository
     * @param EntityRepository $paymentMethodRepository payment method repository
     */
    public function __construct(
        Kernel $kernel,
        EntityRepository $userRepository,
        EntityRepository $salesChannelRepository,
        EntityRepository $salesChannelDomainRepository,
        EntityRepository $paymentMethodRepository
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
        $pluginInstance = $this->kernel->getPluginLoader()->getPluginInstance(LengowConnector::class);
        return $pluginInstance ? $pluginInstance->getBasePath() : '';
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
            if ($salesChannelDomain) {
                $baseUrl = $salesChannelDomain->getUrl();
            } else {
                $baseUrl = $salesChannelDomainCollection->first()
                    ? $salesChannelDomainCollection->first()->getUrl()
                    : '';
            }
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
     * @param EntityRepository $shippingMethodRepository shopware shipping method repository
     *
     * @return string
     */
    public static function getShippingMethodDefaultValue(
        string $salesChannelId,
        EntityRepository $shippingMethodRepository
    ): string
    {
        $shippingMethodCriteria = new Criteria();
        $shippingMethodCriteria->getAssociation('salesChannel')
            ->addFilter(new EqualsFilter('salesChannel.id', $salesChannelId));
        $result = $shippingMethodRepository
            ->search($shippingMethodCriteria, Context::createDefaultContext());
        if ($result->count() !== 0) {
            return $result->first() ? $result->first()->getId() : '';
        }
        return '';
    }
}
