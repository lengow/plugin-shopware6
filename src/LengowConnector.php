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
use Shopware\Core\Framework\Plugin\Context\UpdateContext;
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
    private const LIVE_VERSION_ID = '0fa91ce3e96a4bc2be4bd9ce752c3425';

    public function getMigrationNamespace(): string
    {
        return 'Lengow\\Connector\\Migration';
    }

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
        $this->ensureShopware67SchemaCompatibility();
        $this->addPaymentMethod($installContext->getContext());
    }

    public function activate(ActivateContext $activateContext): void
    {
        $this->ensureShopware67SchemaCompatibility();
        LengowConfiguration::createDefaultSalesChannelConfig(
            $this->container->get('sales_channel.repository'),
            $this->container->get('shipping_method.repository'),
            $this->container->get('lengow_settings.repository')
        );
        parent::activate($activateContext);
    }

    public function update(UpdateContext $updateContext): void
    {
        $this->ensureShopware67SchemaCompatibility();
        parent::update($updateContext);
    }

    public function uninstall(UninstallContext $uninstallContext): void
    {
        parent::uninstall($uninstallContext);
        $this->setPaymentMethodIsActive(false, $uninstallContext->getContext());

        if (!$uninstallContext->keepUserData()) {
            $connection = $this->container->get(Connection::class);

            $connection->executeStatement('DROP TABLE IF EXISTS `lengow_order`, `lengow_order_line`, `lengow_order_error`, `lengow_action`, `lengow_settings`, `lengow_product`;');

            $connection->executeStatement('
            DELETE FROM state_machine_transition 
            WHERE to_state_id = (
                SELECT id FROM state_machine_state WHERE technical_name = "lengow_technical_error"
            ) OR from_state_id = (
                SELECT id FROM state_machine_state WHERE technical_name = "lengow_technical_error"
            );
        ');

            $connection->executeStatement('
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
            'technicalName' => 'lengow_payment',
            'description' => 'Lengow payment, DO NOT activate NOR delete',
            'pluginId' => $pluginId,
            'afterOrderEnabled' => false,
            'active' => false,
        ];
        /** @var EntityRepository $paymentRepository */
        $paymentRepository = $this->container->get('payment_method.repository');
        $paymentRepository->upsert([$lengowPaymentData], $context);
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

    private function ensureShopware67SchemaCompatibility(): void
    {
        /** @var Connection $connection */
        $connection = $this->container->get(Connection::class);

        $this->addVersionReferenceColumnIfMissing($connection, 'lengow_action', 'order_version_id');
        $this->addVersionReferenceColumnIfMissing($connection, 'lengow_order', 'order_version_id');
        $this->addVersionReferenceColumnIfMissing($connection, 'lengow_order_line', 'order_version_id');
        $this->addVersionReferenceColumnIfMissing($connection, 'lengow_order_line', 'product_version_id');
        $this->addVersionReferenceColumnIfMissing($connection, 'lengow_product', 'product_version_id');

        $primaryKeyExists = (int) $connection->fetchOne(
            'SELECT COUNT(*)
            FROM information_schema.statistics
            WHERE table_schema = DATABASE()
              AND table_name = :table
              AND index_name = :indexName',
            [
                'table' => 'lengow_product',
                'indexName' => 'PRIMARY',
            ]
        ) > 0;
        if (!$primaryKeyExists) {
            $connection->executeStatement('ALTER TABLE `lengow_product` ADD PRIMARY KEY (`id`)');
        }

        $this->dropLegacyProductForeignKey($connection);
        $this->ensureCompositeProductForeignKey($connection);
    }

    private function addVersionReferenceColumnIfMissing(
        Connection $connection,
        string $tableName,
        string $columnName
    ): void {
        $columnExists = (int) $connection->fetchOne(
            'SELECT COUNT(*)
            FROM information_schema.columns
            WHERE table_schema = DATABASE()
              AND table_name = :table
              AND column_name = :column',
            [
                'table' => $tableName,
                'column' => $columnName,
            ]
        ) > 0;
        if ($columnExists) {
            return;
        }

        $connection->executeStatement(sprintf(
            'ALTER TABLE `%s` ADD `%s` BINARY(16) NULL',
            $tableName,
            $columnName
        ));
        $connection->executeStatement(sprintf(
            'UPDATE `%s` SET `%s` = UNHEX(\'%s\') WHERE `%s` IS NULL',
            $tableName,
            $columnName,
            self::LIVE_VERSION_ID,
            $columnName
        ));
        $connection->executeStatement(sprintf(
            'ALTER TABLE `%s` MODIFY `%s` BINARY(16) NOT NULL',
            $tableName,
            $columnName
        ));
    }

    private function dropLegacyProductForeignKey(Connection $connection): void
    {
        $legacyKeys = $connection->fetchFirstColumn(
            'SELECT constraint_name
            FROM (
                SELECT
                    constraint_name,
                    GROUP_CONCAT(column_name ORDER BY ordinal_position) AS fk_columns,
                    GROUP_CONCAT(referenced_column_name ORDER BY ordinal_position) AS ref_columns
                FROM information_schema.key_column_usage
                WHERE table_schema = DATABASE()
                  AND table_name = :table
                  AND referenced_table_name = :refTable
                GROUP BY constraint_name
            ) AS constraints_map
            WHERE fk_columns = :legacyFkColumns
              AND ref_columns = :legacyRefColumns',
            [
                'table' => 'lengow_product',
                'refTable' => 'product',
                'legacyFkColumns' => 'product_id',
                'legacyRefColumns' => 'id',
            ]
        );

        foreach ($legacyKeys as $foreignKey) {
            $connection->executeStatement(sprintf('ALTER TABLE `lengow_product` DROP FOREIGN KEY `%s`', $foreignKey));
        }
    }

    private function ensureCompositeProductForeignKey(Connection $connection): void
    {
        $compositeKeyExists = (int) $connection->fetchOne(
            'SELECT COUNT(*)
            FROM (
                SELECT
                    GROUP_CONCAT(column_name ORDER BY ordinal_position) AS fk_columns,
                    GROUP_CONCAT(referenced_column_name ORDER BY ordinal_position) AS ref_columns
                FROM information_schema.key_column_usage
                WHERE table_schema = DATABASE()
                  AND table_name = :table
                  AND referenced_table_name = :refTable
                GROUP BY constraint_name
            ) AS constraints_map
            WHERE fk_columns = :compositeFkColumns
              AND ref_columns = :compositeRefColumns',
            [
                'table' => 'lengow_product',
                'refTable' => 'product',
                'compositeFkColumns' => 'product_id,product_version_id',
                'compositeRefColumns' => 'id,version_id',
            ]
        ) > 0;

        if ($compositeKeyExists) {
            return;
        }

        $indexExists = (int) $connection->fetchOne(
            'SELECT COUNT(*)
            FROM information_schema.statistics
            WHERE table_schema = DATABASE()
              AND table_name = :table
              AND index_name = :index',
            [
                'table' => 'lengow_product',
                'index' => 'idx_lengow_product_product_version',
            ]
        ) > 0;

        if (!$indexExists) {
            $connection->executeStatement(
                'CREATE INDEX `idx_lengow_product_product_version` ON `lengow_product` (`product_id`, `product_version_id`)'
            );
        }

        $connection->executeStatement(
            'ALTER TABLE `lengow_product`
                ADD CONSTRAINT `fk_lengow_product_product_version`
                FOREIGN KEY (`product_id`, `product_version_id`)
                REFERENCES `product` (`id`, `version_id`)
                ON DELETE CASCADE'
        );
    }
}
