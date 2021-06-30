<?php declare(strict_types=1);

namespace Lengow\Connector\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1624606589Update102 extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1624606589;
    }

    public function update(Connection $connection): void
    {
        // modifying the value field to be able to store JSON data
        $connection->executeUpdate('ALTER TABLE `lengow_settings` CHANGE `value` `value` TEXT NULL DEFAULT NULL');
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}
