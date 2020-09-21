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
use Shopware\Core\System\Language\LanguageCollection;
use Shopware\Core\System\SalesChannel\Aggregate\SalesChannelDomain\SalesChannelDomainCollection;
use Lengow\Connector\Connector;

/**
 * Class EnvironmentInfoProvider
 * @package Lengow\Connector\Util
 */
class EnvironmentInfoProvider
{
    /**
     * @var string plugin name
     */
    public const PLUGIN_NAME = 'Connector';

    /**
     * @var Kernel $kernel
     */
    private $kernel;

    /**
     * @var EntityRepositoryInterface language repository
     */
    private $languageRepository;

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
     * @param EntityRepositoryInterface $languageRepository language repository
     * @param EntityRepositoryInterface $salesChannelRepository sales channel repository
     * @param EntityRepositoryInterface $salesChannelDomainRepository sales channel domain repository
     * @param EntityRepositoryInterface $paymentMethodRepository payment method repository
     */
    public function __construct(
        Kernel $kernel,
        EntityRepositoryInterface $languageRepository,
        EntityRepositoryInterface $salesChannelRepository,
        EntityRepositoryInterface $salesChannelDomainRepository,
        EntityRepositoryInterface $paymentMethodRepository
    )
    {
        $this->kernel = $kernel;
        $this->languageRepository = $languageRepository;
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
        // TODO get current context - don't create new context
        $context = Context::createDefaultContext();
        $languageId = $context->getLanguageId();
        $criteria = new Criteria([$languageId]);
        $criteria->addAssociation('locale');
        /** @var LanguageCollection $languageCollection */
        $languageCollection = $this->languageRepository->search($criteria, $context)->getEntities();
        $language = $languageCollection->get($languageId);
        if ($language === null) {
            return 'en-GB';
        }
        $locale = $language->getLocale();
        if (!$locale) {
            return 'en-GB';
        }
        return $locale->getCode();
    }

    /**
     * Get plugin path
     *
     * @return string
     */
    public function getPluginPath(): string
    {
        if ($this->kernel->getPluginLoader()->getPluginInstance(Connector::class)) {
            return $this->kernel->getPluginLoader()->getPluginInstance(Connector::class)->getPath();
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
        return $this->kernel->getPluginLoader()->getPluginInstance(Connector::class)->getBasePath();
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
}
