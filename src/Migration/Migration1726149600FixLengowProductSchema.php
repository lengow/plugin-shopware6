<?php declare(strict_types=1);

namespace Lengow\Connector\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1726149600FixLengowProductSchema extends MigrationStep
{
    private const LIVE_VERSION_ID = '0fa91ce3e96a4bc2be4bd9ce752c3425';

    public function getCreationTimestamp(): int
    {
        return 1726149600;
    }

    public function update(Connection $connection): void
    {
        $this->addVersionReferenceColumnIfMissing($connection, 'lengow_action', 'order_version_id');
        $this->addVersionReferenceColumnIfMissing($connection, 'lengow_order', 'order_version_id');
        $this->addVersionReferenceColumnIfMissing($connection, 'lengow_order_line', 'order_version_id');
        $this->addVersionReferenceColumnIfMissing($connection, 'lengow_order_line', 'product_version_id');
        $this->addVersionReferenceColumnIfMissing($connection, 'lengow_product', 'product_version_id');

        $primaryKeyExists = (int) $connection->fetchOne(
            'SELECT COUNT(*)
            FROM information_schema.statistics
            WHERE table_schema = DATABASE()
              AND table_name = \'lengow_product\'
              AND index_name = \'PRIMARY\''
        ) > 0;

        if (!$primaryKeyExists) {
            $connection->executeStatement('ALTER TABLE `lengow_product` ADD PRIMARY KEY (`id`)');
        }

        $this->dropLegacyProductForeignKey($connection);
        $this->ensureCompositeProductForeignKey($connection);
    }

    public function updateDestructive(Connection $connection): void
    {
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
              AND table_name = :tableName
              AND column_name = :columnName',
            [
                'tableName' => $tableName,
                'columnName' => $columnName,
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
                  AND table_name = \'lengow_product\'
                  AND referenced_table_name = \'product\'
                GROUP BY constraint_name
            ) AS constraints_map
            WHERE fk_columns = \'product_id\'
              AND ref_columns = \'id\''
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
                  AND table_name = \'lengow_product\'
                  AND referenced_table_name = \'product\'
                GROUP BY constraint_name
            ) AS constraints_map
            WHERE fk_columns = \'product_id,product_version_id\'
              AND ref_columns = \'id,version_id\''
        ) > 0;

        if ($compositeKeyExists) {
            return;
        }

        $indexExists = (int) $connection->fetchOne(
            'SELECT COUNT(*)
            FROM information_schema.statistics
            WHERE table_schema = DATABASE()
              AND table_name = \'lengow_product\'
              AND index_name = \'idx_lengow_product_product_version\''
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
