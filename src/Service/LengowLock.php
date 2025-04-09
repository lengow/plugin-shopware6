<?php

namespace Lengow\Connector\Service;

use Doctrine\DBAL\Connection;

class LengowLock
{
    private Connection $connection;
    private LengowLog $logger;
    private bool $display = false;

    public function __construct(Connection $connection, LengowLog $logger)
    {
        $this->connection = $connection;
        $this->logger = $logger;
    }

    public function acquireLock(string $lockName, int $timeout = 0): bool
    {
        try {
            $query = "SELECT GET_LOCK(:lockName, :timeout)";
            $lock = $this->connection->fetchOne($query, ['lockName' => $lockName, 'timeout' => $timeout]);

            return (bool) $lock;
        } catch (\Exception $e) {
            $this->logger->write('LOCK', 'Failed to acquire MySQL lock: ' . $e->getMessage(), $this->display);
            return false;
        }
    }

    public function releaseLock(string $lockName): bool
    {
        try {
            $query = "SELECT RELEASE_LOCK(:lockName)";
            $released = $this->connection->fetchOne($query, ['lockName' => $lockName]);

            return (bool) $released;
        } catch (\Exception $e) {
            $this->logger->write('LOCK', 'Failed to release MySQL lock: ' . $e->getMessage(), $this->display);
            return false;
        }
    }

    public function setDisplay(bool $display): void
    {
        $this->display = $display;
    }
}
