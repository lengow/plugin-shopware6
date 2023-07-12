<?php declare(strict_types=1);

namespace Lengow\Connector\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;
use Shopware\Core\Framework\Uuid\Uuid;

class Migration1606313662Init extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1606229693;
    }

    public function update(Connection $connection): void
    {
        /**
         * create lengow_order table
         * this table reference 2 shopware base tables :
         * order (FK order_id)
         * sales_channel (FK sales_channel_id)
         */
        $connection->executeUpdate('
            CREATE TABLE IF NOT EXISTS `lengow_order` (
                `id`                   BINARY(16)    NOT NULL,
                `order_id`             BINARY(16)    NULL DEFAULT NULL,
                `order_sku`            VARCHAR(64)   NULL DEFAULT NULL,
                `sales_channel_id`     BINARY(16)    NOT NULL,
                `delivery_address_id`  INTEGER(11)   UNSIGNED NOT NULL,
                `delivery_country_iso` VARCHAR(3)    NULL DEFAULT NULL,
                `marketplace_sku`      VARCHAR(100)  NOT NULL,
                `marketplace_name`     VARCHAR(100)  NOT NULL,
                `marketplace_label`    VARCHAR(100)  NULL DEFAULT NULL,
                `order_lengow_state`   VARCHAR(100)  NOT NULL,
                `order_process_state`  SMALLINT(5)   UNSIGNED NOT NULL,
                `order_date`           DATETIME(3)   NOT NULL,
                `order_item`           SMALLINT(5)   UNSIGNED NULL DEFAULT NULL,
                `order_types`          JSON          NULL DEFAULT NULL,
                `currency`             VARCHAR(3)    NULL DEFAULT NULL,
                `total_paid`           DECIMAL(17,2) NULL DEFAULT NULL,
                `commission`           DECIMAL(17,2) NULL DEFAULT NULL,
                `customer_name`        VARCHAR(255)  NULL DEFAULT NULL,
                `customer_email`       VARCHAR(255)  NULL DEFAULT NULL,
                `customer_vat_number`  VARCHAR(255)  NULL DEFAULT NULL,
                `carrier`              VARCHAR(100)  NULL DEFAULT NULL,
                `carrier_method`       VARCHAR(100)  NULL DEFAULT NULL,
                `carrier_tracking`     VARCHAR(100)  NULL DEFAULT NULL,
                `carrier_id_relay`     VARCHAR(100)  NULL DEFAULT NULL,
                `sent_marketplace`     TINYINT(1)    NOT NULL DEFAULT 0,
                `is_in_error`          TINYINT(1)    NOT NULL DEFAULT 1,
                `is_reimported`        TINYINT(1)    NOT NULL DEFAULT 0,
                `message`              TEXT          NULL DEFAULT NULL,
                `created_at`           DATETIME(3)   NOT NULL,
                `updated_at`           DATETIME(3)   NULL DEFAULT NULL,
                `imported_at`          DATETIME(3)   NULL DEFAULT NULL,
                `extra`                JSON          NULL DEFAULT NULL,
                PRIMARY KEY (`id`),
                INDEX (
                    `order_id`,
                    `order_sku`,
                    `sales_channel_id`,
                    `marketplace_sku`,
                    `marketplace_name`,
                    `marketplace_label`
                )
            ) DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ');

        /**
         * create lengow_order_line table
         * this table reference 2 shopware base tables :
         * order (FK order_id)
         * product (FK product_id)
         */
        $connection->executeUpdate('
            CREATE TABLE IF NOT EXISTS `lengow_order_line` (
                `id`            BINARY(16)   NOT NULL,
                `order_id`      BINARY(16)   NOT NULL,
                `product_id`    BINARY(16)   NOT NULL,
                `order_line_id` VARCHAR(100) NOT NULL,
                `created_at`    DATETIME(3)  NOT NULL,
                `updated_at`    DATETIME(3)  NULL DEFAULT NULL,
                PRIMARY KEY (`id`),
                INDEX (`order_id`)
            ) DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ');

        /**
         * create lengow_order_error table
         * this table reference another lengow table :
         * lengow_order (FK lengow_order_id)
         */
        $connection->executeUpdate('
            CREATE TABLE IF NOT EXISTS `lengow_order_error` (
                `id`              BINARY(16)  NOT NULL,
                `lengow_order_id` BINARY(16)  NOT NULL,
                `message`         TEXT        NULL DEFAULT NULL,
                `type`            INTEGER(11) UNSIGNED NOT NULL,
                `is_finished`     TINYINT(1)  NOT NULL DEFAULT 0,
                `mail`            TINYINT(1)  NOT NULL DEFAULT 0,
                `created_at`      DATETIME(3) NOT NULL,
                `updated_at`      DATETIME(3) NULL DEFAULT NULL,
                PRIMARY KEY (`id`),
                INDEX (`lengow_order_id`)
            ) DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ');

        /**
         * create lengow_action table
         * this table reference 1 shopware base table :
         * order (FK order_id)
         */
        $connection->executeUpdate('
            CREATE TABLE IF NOT EXISTS `lengow_action` (
                `id`             BINARY(16)   NOT NULL,
                `order_id`       BINARY(16)   NOT NULL,
                `action_id`      INTEGER(11)  UNSIGNED NOT NULL,
                `order_line_sku` VARCHAR(100) NULL DEFAULT NULL,
                `action_type`    VARCHAR(32)  NOT NULL,
                `retry`          SMALLINT(5)  UNSIGNED NOT NULL DEFAULT 0,
                `parameters`     JSON         NOT NULL,
                `state`          SMALLINT(5)  UNSIGNED NOT NULL,
                `created_at`     DATETIME(3)  NOT NULL,
                `updated_at`     DATETIME(3)  NULL DEFAULT NULL,
                PRIMARY KEY (`id`),
                INDEX (`order_id`)
            ) DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ');

        /**
         * create lengow_settings table
         * this table reference 1 shopware base table :
         * sales_channel (FK sales_channel_id)
         */
        $connection->executeUpdate('
            CREATE TABLE IF NOT EXISTS `lengow_settings` (
                `id`               BINARY(16)   NOT NULL,
                `sales_channel_id` BINARY(16)   NULL DEFAULT NULL,
                `name`             VARCHAR(100) NOT NULL,
                `value`            VARCHAR(100) NULL DEFAULT NULL,
                `created_at`       DATETIME(3)  NOT NULL,
                `updated_at`       DATETIME(3)  NULL DEFAULT NULL,
                FOREIGN KEY (`sales_channel_id`) REFERENCES sales_channel(`id`) ON DELETE CASCADE,
                PRIMARY KEY (`id`),
                INDEX (`sales_channel_id`)
            ) DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ');

        /**
         * create lengow_product assoc table
         * this table reference 2 shopware base tables :
         * product (FK product_id)
         * sales_channel (FK sales_channel_id)
         */
        $connection->executeUpdate('
            CREATE TABLE IF NOT EXISTS `lengow_product` (
                `id`               BINARY(16)  NOT NULL,
                `product_id`       BINARY(16)  NOT NULL,
                `sales_channel_id` BINARY(16)  NOT NULL,
                `created_at`       DATETIME(3) NOT NULL,
                `updated_at`       DATETIME(3),
                FOREIGN KEY (`sales_channel_id`) REFERENCES sales_channel(`id`),
                FOREIGN KEY (`product_id`)       REFERENCES product(`id`),
                INDEX (`product_id`, `sales_channel_id`)
            ) DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ');

        /**
         * Add lengow_technical_error new order state in state_machine_state table
         * order state are linked to state_machine (order.state here)
         */
        $StateUuid = Uuid::randomHex(); // Generate new uuid for insert
        $connection->executeUpdate('
            INSERT IGNORE INTO state_machine_state VALUES (
                UNHEX("' . $StateUuid . '"),
                "lengow_technical_error",
                (SELECT id FROM state_machine WHERE technical_name = "order.state"),
                NOW(),
                null
            );
        ');

        $statesAvailable = $connection->fetchAllAssociative('
            SELECT id
            FROM state_machine_state
            WHERE state_machine_id IN (
                SELECT id
                FROM state_machine
                WHERE technical_name = "order.state"
            )
        ');
        foreach ($statesAvailable as $state) {
            /**
             * Add all state transition needed for order to go to lengow technical error
             * Once an order is in lengow technical error, it state cannot be changed
             */
            $uuid = Uuid::randomHex(); // Generate new uuid for insert
            $availableStateUuid = Uuid::fromBytesToHex($state['id']);
            $connection->executeUpdate('
                INSERT IGNORE INTO state_machine_transition VALUES (
                    UNHEX("' . $uuid . '"),
                    "technical_error",
                    (
                        SELECT id
                        FROM state_machine
                        WHERE technical_name = "order.state"
                    ),
                    UNHEX("' . $availableStateUuid . '"),
                    (
                        SELECT id
                        FROM state_machine_state
                        WHERE technical_name = "lengow_technical_error"
                    ),
                    null,
                    NOW(),
                    null
                )
            ');
        }
        $uuid = Uuid::randomHex(); // Generate new uuid for insert
        $connection->executeUpdate('
            INSERT IGNORE INTO state_machine_transition VALUES (
                UNHEX("' . $uuid . '"),
                "cancel_technical_error",
                (
                    SELECT id
                    FROM state_machine
                    WHERE technical_name = "order.state"
                ),
                (
                    SELECT id
                    FROM state_machine_state
                    WHERE technical_name = "open"
                    AND state_machine_id = (
                        SELECT id
                        FROM state_machine
                        WHERE technical_name = "order.state"
                    )
                ),
                (
                    SELECT id
                    FROM state_machine_state
                    WHERE technical_name = "lengow_technical_error"
                ),
                null,
                NOW(),
                null
            )
        ');
        $languageAvailable = $connection->fetchAllAssociative('SELECT id FROM `language`');
        foreach ($languageAvailable as $language) {
            $languageId = Uuid::fromBytesToHex($language['id']);
            if ($languageId) {
                $connection->executeUpdate('
                    INSERT IGNORE INTO state_machine_state_translation VALUES (
                        UNHEX("' . $languageId . '"),
                        (
                            SELECT id
                            FROM state_machine_state
                            WHERE technical_name = "lengow_technical_error"
                        ),
                        "Lengow technical error",
                        null,
                        NOW(),
                        null
                    )
                ');
            }
        }
    }

    public function updateDestructive(Connection $connection): void
    {
        $connection->executeUpdate('
            DROP TABLE IF EXISTS
            `lengow_settings`,
            `lengow_product`,
        ');

        // we dont remove lengow_technical_error order state because it can cause
        // inconsistency if it has been used on order that are still in the database
    }
}
