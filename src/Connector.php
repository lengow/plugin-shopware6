<?php declare(strict_types=1);

namespace Lengow\Connector;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Plugin;
use Shopware\Core\Framework\Plugin\Context\InstallContext;
use Shopware\Core\Framework\Plugin\Context\UninstallContext;
use Shopware\Core\Framework\Plugin\Util\PluginIdProvider;
use Shopware\Core\Framework\Plugin\Context\ActivateContext;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\XmlFileLoader;
use Lengow\Connector\Service\LengowPayment;
use Lengow\Connector\Entity\Settings;
use Lengow\Connector\Service\LengowConfiguration;

/**
 * Class Connector
 * @package Lengow\Connector
 */
class Connector extends Plugin
{
    /**
     * {@inheritdoc}
     */
    public function build(ContainerBuilder $container): void
    {
        parent::build($container);

        $loader = new XmlFileLoader($container, new FileLocator(__DIR__ . '/DependencyInjection/'));
        $loader->load('controller.xml');
        $loader->load('entity.xml');
        $loader->load('factory.xml');
        $loader->load('front_controller.xml');
        $loader->load('service.xml');
        $loader->load('subscriber.xml');
        $loader->load('util.xml');
        $loader->load('extension.xml');
    }

    /**
     * @param InstallContext $context
     */
    public function install(InstallContext $context): void
    {
        parent::Install($context);
        $this->addPaymentMethod($context->getContext());
    }

    /**
     * @param ActivateContext $activateContext
     */
    public function activate(ActivateContext $activateContext): void
    {
        LengowConfiguration::createDefaultSalesChannelConfig(
            $this->container->get('sales_channel.repository'),
            $this->container->get('shipping_method.repository'),
            $this->container->get('lengow_settings.repository')
        );
        parent::activate($activateContext);
    }

    /**
     * @param UninstallContext $context
     */
    public function uninstall(UninstallContext $context): void
    {
        $this->setPaymentMethodIsActive(false, $context->getContext());
        // todo drop table lengow_settings
    }

    /**
     * @param Context $context
     */
    private function addPaymentMethod(Context $context): void
    {
        $paymentMethodExists = $this->getPaymentMethodId();
        // Payment method exists already, no need to continue here
        if ($paymentMethodExists) {
            return;
        }
        /** @var PluginIdProvider $pluginIdProvider */
        $pluginIdProvider = $this->container->get(PluginIdProvider::class);
        $pluginId = $pluginIdProvider->getPluginIdByBaseClass(get_class($this), $context);
        $lengowPaymentData = [
            // payment handler will be selected by the identifier
            'handlerIdentifier' => LengowPayment::class,
            'name' => 'Lengow payment',
            'description' => 'Lengow payment, DO NOT activate NOR delete',
            'pluginId' => $pluginId,
            'afterOrderEnabled' => false,
        ];
        /** @var EntityRepositoryInterface $paymentRepository */
        $paymentRepository = $this->container->get('payment_method.repository');
        $paymentRepository->create([$lengowPaymentData], $context);
        // Without this workaround, paymentMethod is activated by default
        $connection = $this->container->get(Connection::class);
        $connection->exec("UPDATE `payment_method` SET `active` = 0 WHERE `id` = UNHEX('{$this->getPaymentMethodId()}');");
    }

    /**
     * @param bool $active
     * @param Context $context
     */
    private function setPaymentMethodIsActive(bool $active, Context $context): void
    {
        /** @var EntityRepositoryInterface $paymentRepository */
        $paymentRepository = $this->container->get('payment_method.repository');
        $paymentMethodId = $this->getPaymentMethodId();
        // Payment does not even exist, so nothing to (de-)activate here
        if (!$paymentMethodId) {
            return;
        }
        $paymentMethod = [
            'id' => $paymentMethodId,
            'active' => $active,
        ];
        $paymentRepository->update([$paymentMethod], $context);
    }

    /**
     * @return string|null
     */
    private function getPaymentMethodId(): ?string
    {
        /** @var EntityRepositoryInterface $paymentRepository */
        $paymentRepository = $this->container->get('payment_method.repository');
        // Fetch ID for update
        $paymentCriteria = (new Criteria())->addFilter(new EqualsFilter('handlerIdentifier', LengowPayment::class));
        $paymentIds = $paymentRepository->searchIds($paymentCriteria, Context::createDefaultContext());
        if ($paymentIds->getTotal() === 0) {
            return null;
        }
        return $paymentIds->getIds()[0];
    }
}
