<?php declare(strict_types=1);

namespace Lengow\Connector\Util;

use Shopware\Core\Kernel;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\System\Language\LanguageCollection;
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
     * @var EntityRepositoryInterface $languageRepository
     */
    private $languageRepository;

    /**
     * EnvironmentInfoProvider Construct
     *
     * @param Kernel $kernel Shopware kernel
     * @param EntityRepositoryInterface $languageRepository Shopware language repository
     */
    public function __construct(Kernel $kernel, EntityRepositoryInterface $languageRepository)
    {
        $this->kernel = $kernel;
        $this->languageRepository = $languageRepository;
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
}
