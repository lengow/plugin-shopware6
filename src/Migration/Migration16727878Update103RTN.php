<?php declare(strict_types=1);

namespace Lengow\Connector\Migration;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration16727878Update103RTN extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1624606589;
    }

    /**
     * @throws Exception
     */
    public function update(Connection $connection): void
    {
        $sql = "ALTER TABLE lengow_order
            ADD COLUMN return_tracking_number JSON NULL DEFAULT NULL,
            ADD COLUMN return_carrier VARCHAR(100) NULL DEFAULT NULL"
        ;
        $connection->executeStatement($sql);
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}
