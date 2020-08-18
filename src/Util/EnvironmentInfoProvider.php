<?php declare(strict_types=1);

namespace Lengow\Connector\Util;

use Shopware\Core\Kernel;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
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
     * @var Kernel $kernel
     */
    private $kernel;

    /**
     * @var EntityRepositoryInterface language repository
     */
    private $languageRepository;

    /**
     * @var EntityRepositoryInterface sales channel domain repository
     */
    private $salesChannelDomainRepository;

    /**
     * EnvironmentInfoProvider Construct
     *
     * @param Kernel $kernel Shopware kernel
     * @param EntityRepositoryInterface $languageRepository language repository
     * @param EntityRepositoryInterface $salesChannelDomainRepository sales channel domain repository
     */
    public function __construct(
        Kernel $kernel,
        EntityRepositoryInterface $languageRepository,
        EntityRepositoryInterface $salesChannelDomainRepository
    )
    {
        $this->kernel = $kernel;
        $this->languageRepository = $languageRepository;
        $this->salesChannelDomainRepository = $salesChannelDomainRepository;
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
        return $this->kernel->getPluginLoader()->getPluginInstance(Connector::class)->getPath();
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
}