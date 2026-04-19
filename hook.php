<?php

/**
 * GLPI 11 Discovery Wrapper for Install
 */
function plugin_install_protocolsmanager(): bool
{
    return plugin_protocolsmanager_install();
}

/**
 * GLPI 11 Discovery Wrapper for Uninstall
 */
function plugin_uninstall_protocolsmanager(): bool
{
    return plugin_protocolsmanager_uninstall();
}

/**
 * Install the plugin.
 *
 * All integer primary and foreign keys use INT UNSIGNED (no display width)
 * as required by GLPI 11 / MySQL 8. Boolean-like flags use TINYINT UNSIGNED.
 */
function plugin_protocolsmanager_install(): bool
{
    global $DB;
    $version   = plugin_version_protocolsmanager();
    $migration = new Migration($version['version']);

    // Helper: create table + optional seed rows if the table doesn't exist yet
    $createTable = function (string $name, string $schema, array $inserts = []) use ($DB): void {
        if (!$DB->tableExists($name)) {
            $DB->doQuery($schema);
            foreach ($inserts as $insert) {
                $DB->doQuery($insert);
            }
        }
    };

    // ── Profiles ──────────────────────────────────────────────────────────
    $createTable(
        'glpi_plugin_protocolsmanager_profiles',
        "CREATE TABLE `glpi_plugin_protocolsmanager_profiles` (
            `id`            INT UNSIGNED     NOT NULL AUTO_INCREMENT,
            `profile_id`    INT UNSIGNED     DEFAULT NULL,
            `plugin_conf`   CHAR(1)          COLLATE utf8mb4_unicode_ci DEFAULT NULL,
            `tab_access`    CHAR(1)          COLLATE utf8mb4_unicode_ci DEFAULT NULL,
            `make_access`   CHAR(1)          COLLATE utf8mb4_unicode_ci DEFAULT NULL,
            `delete_access` CHAR(1)          COLLATE utf8mb4_unicode_ci DEFAULT NULL,
            PRIMARY KEY (`id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        [
            sprintf(
                "INSERT INTO `glpi_plugin_protocolsmanager_profiles`
                    (`profile_id`, `plugin_conf`, `tab_access`, `make_access`, `delete_access`)
                 VALUES (%d, 'w', 'w', 'w', 'w')",
                (int)($_SESSION['glpiactiveprofile']['id'] ?? 0)
            )
        ]
    );

    // ── Config (templates) ────────────────────────────────────────────────
    $createTable(
        'glpi_plugin_protocolsmanager_config',
        "CREATE TABLE `glpi_plugin_protocolsmanager_config` (
            `id`             INT UNSIGNED         NOT NULL AUTO_INCREMENT,
            `name`           VARCHAR(255)         DEFAULT NULL,
            `title`          VARCHAR(255)         DEFAULT NULL,
            `font`           VARCHAR(255)         DEFAULT NULL,
            `fontsize`       VARCHAR(10)          DEFAULT NULL,
            `logo`           VARCHAR(255)         DEFAULT NULL,
            `logo_width`     INT UNSIGNED         DEFAULT NULL,
            `logo_height`    INT UNSIGNED         DEFAULT NULL,
            `content`        TEXT,
            `footer`         TEXT,
            `city`           VARCHAR(255)         DEFAULT NULL,
            `serial_mode`    TINYINT UNSIGNED     DEFAULT 1,
            `column1`        VARCHAR(255)         DEFAULT NULL,
            `column2`        VARCHAR(255)         DEFAULT NULL,
            `orientation`    VARCHAR(10)          DEFAULT NULL,
            `breakword`      TINYINT UNSIGNED     DEFAULT 1,
            `email_mode`     TINYINT UNSIGNED     DEFAULT 2,
            `upper_content`  TEXT,
            `email_template` INT UNSIGNED         DEFAULT NULL,
            `author_name`    VARCHAR(255)         DEFAULT NULL,
            `author_state`   TINYINT UNSIGNED     DEFAULT 1,
            `date_format`    TINYINT UNSIGNED     DEFAULT 0,
            PRIMARY KEY (`id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        [
            "INSERT INTO `glpi_plugin_protocolsmanager_config`
                (`name`, `title`, `font`, `fontsize`, `content`, `footer`, `city`,
                 `serial_mode`, `orientation`, `breakword`, `email_mode`, `author_name`, `author_state`)
             VALUES
                ('Equipment report',
                 'Certificate of delivery of {owner}',
                 'Helvetica', '9',
                 'User: \\n I have read the terms of use of IT equipment in the Example Company.',
                 'Example Company \\n Example Street 21 \\n 01-234 Example City',
                 'Example city', 1, 'Portrait', 1, 2, 'Test Division', 1)",
            "INSERT INTO `glpi_plugin_protocolsmanager_config`
                (`name`, `title`, `font`, `fontsize`, `content`, `footer`, `city`,
                 `serial_mode`, `orientation`, `breakword`, `email_mode`, `author_name`, `author_state`)
             VALUES
                ('Equipment report 2',
                 'Certificate of delivery of {owner}',
                 'Helvetica', '9',
                 'User: \\n I have read the terms of use of IT equipment in the Example Company.',
                 'Example Company \\n Example Street 21 \\n 01-234 Example City',
                 'Example city', 1, 'Portrait', 1, 2, 'Test Division', 1)"
        ]
    );

    // ── Email config ──────────────────────────────────────────────────────
    $createTable(
        'glpi_plugin_protocolsmanager_emailconfig',
        "CREATE TABLE `glpi_plugin_protocolsmanager_emailconfig` (
            `id`            INT UNSIGNED     NOT NULL AUTO_INCREMENT,
            `tname`         VARCHAR(255)     DEFAULT NULL,
            `send_user`     TINYINT UNSIGNED DEFAULT 2,
            `email_content` TEXT,
            `email_subject` VARCHAR(255)     DEFAULT NULL,
            `email_footer`  VARCHAR(255)     DEFAULT NULL,
            `recipients`    TEXT             DEFAULT NULL,
            PRIMARY KEY (`id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        [
            "INSERT INTO `glpi_plugin_protocolsmanager_emailconfig`
                (`tname`, `send_user`, `email_content`, `email_subject`, `recipients`)
             VALUES
                ('Email default', 2, 'Testmail', 'Testmail', 'Testmail')"
        ]
    );

    // ── Protocols ─────────────────────────────────────────────────────────
    // NOTE: DATETIME is deprecated in GLPI 11 — use TIMESTAMP instead.
    // GLPI raises a WARNING for DATETIME columns and requires running
    // "php bin/console migration:timestamps" to migrate them.
    // This table uses TIMESTAMP from the start to avoid the warning.
    $createTable(
        'glpi_plugin_protocolsmanager_protocols',
        "CREATE TABLE `glpi_plugin_protocolsmanager_protocols` (
            `id`            INT UNSIGNED     NOT NULL AUTO_INCREMENT,
            `name`          VARCHAR(255)     DEFAULT NULL,
            `user_id`       INT UNSIGNED     DEFAULT NULL,
            `gen_date`      TIMESTAMP        NULL DEFAULT NULL,
            `author`        VARCHAR(255)     DEFAULT NULL,
            `document_id`   INT UNSIGNED     DEFAULT NULL,
            `document_type` VARCHAR(255)     DEFAULT NULL,
            PRIMARY KEY (`id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );

    // ── Migration: add fields missing from older installs ─────────────────
    $fieldsToAdd = [
        'author_name'   => "ALTER TABLE `glpi_plugin_protocolsmanager_config` ADD `author_name` VARCHAR(255) DEFAULT NULL AFTER `email_template`",
        'author_state'  => "ALTER TABLE `glpi_plugin_protocolsmanager_config` ADD `author_state` TINYINT UNSIGNED DEFAULT 1 AFTER `author_name`",
        'title'         => "ALTER TABLE `glpi_plugin_protocolsmanager_config` ADD `title` VARCHAR(255) DEFAULT NULL AFTER `name`",
        'logo_width'    => "ALTER TABLE `glpi_plugin_protocolsmanager_config` ADD `logo_width` INT UNSIGNED DEFAULT NULL AFTER `logo`",
        'logo_height'   => "ALTER TABLE `glpi_plugin_protocolsmanager_config` ADD `logo_height` INT UNSIGNED DEFAULT NULL AFTER `logo_width`",
        'date_format'   => "ALTER TABLE `glpi_plugin_protocolsmanager_config` ADD `date_format` TINYINT UNSIGNED DEFAULT 0 AFTER `author_state`",
    ];

    foreach ($fieldsToAdd as $field => $sql) {
        if (!$DB->fieldExists('glpi_plugin_protocolsmanager_config', $field)) {
            $DB->doQuery($sql);
        }
    }

    // ── Migration: recipients VARCHAR(255) → TEXT on existing installs ─────
    if ($DB->tableExists('glpi_plugin_protocolsmanager_emailconfig')) {
        $col = $DB->request([
            'SELECT' => ['DATA_TYPE'],
            'FROM'   => 'information_schema.COLUMNS',
            'WHERE'  => [
                'TABLE_SCHEMA' => new \QueryExpression('DATABASE()'),
                'TABLE_NAME'   => 'glpi_plugin_protocolsmanager_emailconfig',
                'COLUMN_NAME'  => 'recipients',
            ],
        ])->current();
        if ($col && strtolower($col['DATA_TYPE'] ?? '') === 'varchar') {
            $DB->doQuery("ALTER TABLE `glpi_plugin_protocolsmanager_emailconfig`
                          MODIFY `recipients` TEXT DEFAULT NULL");
        }
    }

    // ── Migration: DATETIME → TIMESTAMP on existing installs ──────────────
    // GLPI 11 deprecates DATETIME. Migrate gen_date if it is still DATETIME.
    if ($DB->tableExists('glpi_plugin_protocolsmanager_protocols')) {
        $col = $DB->request([
            'SELECT' => ['DATA_TYPE'],
            'FROM'   => 'information_schema.COLUMNS',
            'WHERE'  => [
                'TABLE_SCHEMA' => new \QueryExpression('DATABASE()'),
                'TABLE_NAME'   => 'glpi_plugin_protocolsmanager_protocols',
                'COLUMN_NAME'  => 'gen_date',
            ],
        ])->current();
        if ($col && strtolower($col['DATA_TYPE'] ?? '') === 'datetime') {
            $DB->doQuery("ALTER TABLE `glpi_plugin_protocolsmanager_protocols`
                          MODIFY `gen_date` TIMESTAMP NULL DEFAULT NULL");
        }
    }

    $migration->executeMigration();

    return true;
}

/**
 * Uninstall the plugin — drops all plugin tables.
 */
function plugin_protocolsmanager_uninstall(): bool
{
    global $DB;

    $tables = [
        'glpi_plugin_protocolsmanager_protocols',
        'glpi_plugin_protocolsmanager_config',
        'glpi_plugin_protocolsmanager_profiles',
        'glpi_plugin_protocolsmanager_emailconfig',
    ];

    // Remove logo files uploaded to GLPI_PICTURE_DIR.
    if ($DB->tableExists('glpi_plugin_protocolsmanager_config')) {
        foreach ($DB->request(['SELECT' => ['logo'], 'FROM' => 'glpi_plugin_protocolsmanager_config']) as $row) {
            if (!empty($row['logo'])) {
                $logoPath = GLPI_PICTURE_DIR . '/' . $row['logo'];
                if (file_exists($logoPath)) {
                    @unlink($logoPath);
                }
            }
        }
    }

    // Remove generated PDF files from GLPI_UPLOAD_DIR.
    if ($DB->tableExists('glpi_plugin_protocolsmanager_protocols')) {
        foreach ($DB->request(['SELECT' => ['name'], 'FROM' => 'glpi_plugin_protocolsmanager_protocols']) as $row) {
            if (!empty($row['name']) && $row['name'] !== 'pending') {
                $pdfPath = GLPI_UPLOAD_DIR . '/' . $row['name'];
                if (file_exists($pdfPath)) {
                    @unlink($pdfPath);
                }
            }
        }
    }

    foreach ($tables as $table) {
        if ($DB->tableExists($table)) {
            $DB->doQuery("DROP TABLE IF EXISTS `{$table}`");
        }
    }

    return true;
}

/**
 * Returns the rights row for a profile, or [] if the table doesn't exist yet.
 */
function plugin_protocolsmanager_getRights(?int $profile_id = null): array
{
    global $DB;

    if (!$DB->tableExists('glpi_plugin_protocolsmanager_profiles')) {
        return [];
    }

    return $DB->request([
        'FROM'  => 'glpi_plugin_protocolsmanager_profiles',
        'WHERE' => ['profile_id' => $profile_id ?? 0],
    ])->current() ?: [];
}
