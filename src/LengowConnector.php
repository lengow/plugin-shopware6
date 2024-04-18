<?php declare(strict_types=1);

namespace Lengow\Connector;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Plugin;
use Shopware\Core\Framework\Plugin\Context\InstallContext;
use Shopware\Core\Framework\Plugin\Context\UninstallContext;
use Shopware\Core\Framework\Plugin\Util\PluginIdProvider;
use Shopware\Core\Framework\Plugin\Context\ActivateContext;
use Shopware\Core\Framework\Uuid\Uuid;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Exception\ServiceCircularReferenceException;
use Symfony\Component\DependencyInjection\Exception\ServiceNotFoundException;
use Symfony\Component\DependencyInjection\Loader\XmlFileLoader;
use Lengow\Connector\Service\LengowPayment;
use Lengow\Connector\Entity\Settings;
use Lengow\Connector\Service\LengowConfiguration;

/**
 * Class LengowConnector
 * @package Lengow\Connector
 */
class LengowConnector extends Plugin
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

    public function install(InstallContext $installContext): void
    {
        parent::Install($installContext);
        $this->addPaymentMethod($installContext->getContext());
    }

    public function activate(ActivateContext $activateContext): void
    {
        LengowConfiguration::createDefaultSalesChannelConfig(
            $this->container->get('sales_channel.repository'),
            $this->container->get('shipping_method.repository'),
            $this->container->get('lengow_settings.repository')
        );
        parent::activate($activateContext);
    }

    public function uninstall(UninstallContext $uninstallContext): void
    {
        parent::uninstall($uninstallContext);
        $this->setPaymentMethodIsActive(false, $uninstallContext->getContext());

        if (!$uninstallContext->keepUserData()) {
            $connection = $this->container->get(Connection::class);

            $connection->executeUpdate('DROP TABLE IF EXISTS `lengow_order`, `lengow_order_line`, `lengow_order_error`, `lengow_action`, `lengow_settings`, `lengow_product`;');

            $connection->executeUpdate('
            DELETE FROM state_machine_transition 
            WHERE to_state_id = (
                SELECT id FROM state_machine_state WHERE technical_name = "lengow_technical_error"
            ) OR from_state_id = (
                SELECT id FROM state_machine_state WHERE technical_name = "lengow_technical_error"
            );
        ');

            $connection->executeUpdate('
            DELETE FROM state_machine_state 
            WHERE technical_name = "lengow_technical_error";
        ');
        }
    }

    /**
     * Add Lengow payment method
     *
     * @param Context $context Shopware context
     *
     * @throws serviceCircularReferenceException|ServiceNotFoundException
     */
    private function addPaymentMethod(Context $context): void
    {
        $paymentMethodId = $this->getPaymentMethodId();
        /** @var PluginIdProvider $pluginIdProvider */
        $pluginIdProvider = $this->container->get(PluginIdProvider::class);
        $pluginId = $pluginIdProvider->getPluginIdByBaseClass(get_class($this), $context);
        $lengowPaymentData = [
            'id' => $paymentMethodId ?? Uuid::randomHex(),
            // payment handler will be selected by the identifier
            'handlerIdentifier' => LengowPayment::class,
            'name' => 'Lengow payment',
            'description' => 'Lengow payment, DO NOT activate NOR delete',
            'pluginId' => $pluginId,
            'afterOrderEnabled' => false,
        ];
        /** @var EntityRepository $paymentRepository */
        $paymentRepository = $this->container->get('payment_method.repository');
        $paymentRepository->upsert([$lengowPaymentData], $context);
        // Without this workaround, paymentMethod is activated by default
        $connection = $this->container->get(Connection::class);
        if ($connection) {
            $connection->exec(
                "UPDATE `payment_method` SET `active` = 0 WHERE `id` = UNHEX('{$this->getPaymentMethodId()}');"
            );
        }
    }

    /**
     * Set active for Lengow payment method
     *
     * @param bool $active active or not Lengow payment method
     * @param Context $context Shopware context
     *
     * @throws serviceCircularReferenceException|ServiceNotFoundException
     */
    private function setPaymentMethodIsActive(bool $active, Context $context): void
    {
        /** @var EntityRepository $paymentRepository */
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
     * Get Lengow payment method id
     *
     * @return string|null
     *
     * @throws serviceCircularReferenceException|ServiceNotFoundException
     */
    private function getPaymentMethodId(): ?string
    {
        /** @var EntityRepository $paymentRepository */
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
