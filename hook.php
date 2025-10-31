<?php

/**
 * Install the plugin
 */
function plugin_protocolsmanager_install(): bool
{
    global $DB;
    $version    = plugin_version_protocolsmanager();
    $migration  = new Migration($version['version']);

    // Helper: create table if not exists
    $createTable = function (string $name, string $schema, array $inserts = []) use ($DB) {
        if (!$DB->tableExists($name)) {
            $DB->doQuery($schema, $DB->error());
            foreach ($inserts as $insert) {
                $DB->doQuery($insert, $DB->error());
            }
        }
    };

    // Profiles table
    $createTable(
        'glpi_plugin_protocolsmanager_profiles',
        "CREATE TABLE glpi_plugin_protocolsmanager_profiles (
            id INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
            profile_id INT(11) UNSIGNED,
            plugin_conf CHAR(1) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
            tab_access CHAR(1) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
            make_access CHAR(1) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
            delete_access CHAR(1) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
            PRIMARY KEY (id)
        ) DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        [
            sprintf(
                "INSERT INTO glpi_plugin_protocolsmanager_profiles (profile_id, plugin_conf, tab_access, make_access, delete_access)
                 VALUES (%d, 'w', 'w', 'w', 'w')",
                $_SESSION['glpiactiveprofile']['id'] ?? 0
            )
        ]
    );

    // Config table
    $createTable(
        'glpi_plugin_protocolsmanager_config',
        "CREATE TABLE glpi_plugin_protocolsmanager_config (
            id INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
            name VARCHAR(255),
            title VARCHAR(255),
            font VARCHAR(255),
            fontsize VARCHAR(255),
            logo VARCHAR(255),
            logo_width INT(11) UNSIGNED DEFAULT NULL,
            logo_height INT(11) UNSIGNED DEFAULT NULL,
            content TEXT,
            footer TEXT,
            city VARCHAR(255),
            serial_mode INT(2),
            column1 VARCHAR(255),
            column2 VARCHAR(255),
            orientation VARCHAR(10),
            breakword INT(2),
            email_mode INT(2),
            upper_content TEXT,
            email_template INT(2),
            author_name VARCHAR(255),
            author_state INT(2),
            PRIMARY KEY (id)
        ) DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        [
            "INSERT INTO glpi_plugin_protocolsmanager_config
                (name, title, font, fontsize, content, footer, city, serial_mode, orientation, breakword, email_mode, author_name, author_state)
             VALUES
                ('Equipment report',
                 'Certificate of delivery of {owner}',
                 'Roboto',
                 '9',
                 'User: \\n I have read the terms of use of IT equipment in the Example Company.',
                 'Example Company \\n Example Street 21 \\n 01-234 Example City',
                 'Example city',
                 1,
                 'Portrait',
                 1,
                 2,
                 'Test Division',
                 1)",
            "INSERT INTO glpi_plugin_protocolsmanager_config
                (name, title, font, fontsize, content, footer, city, serial_mode, orientation, breakword, email_mode, author_name, author_state)
             VALUES
                ('Equipment report 2',
                 'Certificate of delivery of {owner}',
                 'Roboto',
                 '9',
                 'User: \\n I have read the terms of use of IT equipment in the Example Company.',
                 'Example Company \\n Example Street 21 \\n 01-234 Example City',
                 'Example city',
                 1,
                 'Portrait',
                 1,
                 2,
                 'Test Division',
                 1)"
        ]
    );

    // Email config table
    $createTable(
        'glpi_plugin_protocolsmanager_emailconfig',
        "CREATE TABLE glpi_plugin_protocolsmanager_emailconfig (
            id INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
            tname VARCHAR(255),
            send_user INT(2),
            email_content TEXT,
            email_subject VARCHAR(255),
            email_footer VARCHAR(255),
            recipients VARCHAR(255),
            PRIMARY KEY (id)
        ) DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        [
            "INSERT INTO glpi_plugin_protocolsmanager_emailconfig
                (tname, send_user, email_content, email_subject, recipients)
             VALUES
                ('Email default', 2, 'Testmail', 'Testmail', 'Testmail')"
        ]
    );

    // Protocols table
    $createTable(
        'glpi_plugin_protocolsmanager_protocols',
        "CREATE TABLE glpi_plugin_protocolsmanager_protocols (
            id INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
            name VARCHAR(255),
            user_id INT(11) UNSIGNED,
            gen_date DATETIME,
            author VARCHAR(255),
            document_id INT(11) UNSIGNED,
            document_type VARCHAR(255),
            PRIMARY KEY (id)
        ) DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );

    // Add missing fields on update
    $fieldsToAdd = [
        'author_name' => "ALTER TABLE glpi_plugin_protocolsmanager_config ADD author_name VARCHAR(255) AFTER email_template",
        'author_state' => "ALTER TABLE glpi_plugin_protocolsmanager_config ADD author_state INT(2) AFTER author_name",
        'title'        => "ALTER TABLE glpi_plugin_protocolsmanager_config ADD title VARCHAR(255) AFTER name",
        'logo_width'   => "ALTER TABLE glpi_plugin_protocolsmanager_config ADD logo_width INT(11) UNSIGNED DEFAULT NULL AFTER logo",
        'logo_height'  => "ALTER TABLE glpi_plugin_protocolsmanager_config ADD logo_height INT(11) UNSIGNED DEFAULT NULL AFTER logo_width"
    ];

    foreach ($fieldsToAdd as $field => $sql) {
        if (!$DB->fieldExists('glpi_plugin_protocolsmanager_config', $field)) {
            $DB->doQuery($sql, $DB->error());
        }
    }

    $migration->executeMigration();

    return true;
}

/**
 * Uninstall the plugin
 */
function plugin_protocolsmanager_uninstall(): bool
{
    global $DB;
    $tables = [
        'glpi_plugin_protocolsmanager_protocols',
        'glpi_plugin_protocolsmanager_config',
        'glpi_plugin_protocolsmanager_profiles',
        'glpi_plugin_protocolsmanager_emailconfig'
    ];

    foreach ($tables as $table) {
        if ($DB->tableExists($table)) {
            $DB->query("DROP TABLE IF EXISTS `$table`");
        }
    }

    return true;
}

/**
 * Safe loader to prevent early table access
 */
function plugin_protocolsmanager_getRights(?int $profile_id = null)
{
    global $DB;
    if (!$DB->tableExists('glpi_plugin_protocolsmanager_profiles')) {
        return []; // Avoid query before installation
    }

    return $DB->request([
        'FROM'  => 'glpi_plugin_protocolsmanager_profiles',
        'WHERE' => ['profile_id' => $profile_id ?? 0]
    ])->current();
}