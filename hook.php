<?php

/**
 * Install the plugin
 */
function plugin_protocolsmanager_install(): bool
{
    global $DB;
    $version   = plugin_version_protocolsmanager();
    $migration = new Migration($version['version']);

    // Helper: create table if not exists
    // MODIFICATION : Le 3ème paramètre $inserts est conservé, mais il attend maintenant
    // un tableau de tableaux de données (pour DB::insert) au lieu de chaînes SQL.
    $createTable = function (string $name, string $schema, array $inserts = []) use ($DB) {
        if (!$DB->tableExists($name)) {
            if (!$DB->query($schema)) {
                Toolbox::logInFile('php-errors', "Error creating table $name: " . $DB->error() . "\n");
                return;
            }
            // MODIFICATION : On utilise DB::insert() qui est sécurisé,
            // $insertData est un tableau associatif [champ => valeur]
            foreach ($inserts as $insertData) {
                if (!$DB->insert($name, $insertData)) {
                    Toolbox::logInFile('php-errors', "Error inserting defaults for $name: " . $DB->error() . "\n");
                }
            }
        }
    };

    // Profiles table
    $createTable(
        'glpi_plugin_protocolsmanager_profiles',
        "CREATE TABLE glpi_plugin_protocolsmanager_profiles (
            id INT(11) NOT NULL AUTO_INCREMENT,
            profile_id INT(11),
            plugin_conf CHAR(1) COLLATE utf8_unicode_ci DEFAULT NULL,
            tab_access CHAR(1) COLLATE utf8_unicode_ci DEFAULT NULL,
            make_access CHAR(1) COLLATE utf8_unicode_ci DEFAULT NULL,
            delete_access CHAR(1) COLLATE utf8_unicode_ci DEFAULT NULL,
            PRIMARY KEY (id)
        ) DEFAULT CHARSET=utf8mb4 COLLATE=utf8_unicode_ci",
        [
            // MODIFICATION : Remplacé le sprintf par un tableau de données
            [
                'profile_id'    => (int)($_SESSION['glpiactiveprofile']['id'] ?? 0),
                'plugin_conf'   => 'w',
                'tab_access'    => 'w',
                'make_access'   => 'w',
                'delete_access' => 'w'
            ]
        ]
    );

    // Config table
    $createTable(
        'glpi_plugin_protocolsmanager_config',
        "CREATE TABLE glpi_plugin_protocolsmanager_config (
            id INT(11) NOT NULL AUTO_INCREMENT,
            name VARCHAR(255),
            title VARCHAR(255),
            font VARCHAR(255),
            fontsize VARCHAR(255),
            logo VARCHAR(255),
            logo_width INT(11) DEFAULT NULL,
            logo_height INT(11) DEFAULT NULL,
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
        ) DEFAULT CHARSET=utf8mb4 COLLATE=utf8_unicode_ci",
        [
            // MODIFICATION : Remplacé la chaîne "INSERT INTO..." par un tableau de données
            [
                'name' => 'Equipment report',
                'title' => 'Certificate of delivery of {owner}',
                'font' => 'Roboto',
                'fontsize' => '9',
                'content' => 'User: \n I have read the terms of use of IT equipment in the Example Company.',
                'footer' => 'Example Company \n Example Street 21 \n 01-234 Example City',
                'city' => 'Example city',
                'serial_mode' => 1,
                'orientation' => 'Portrait',
                'breakword' => 1,
                'email_mode' => 2,
                'author_name' => 'Test Division',
                'author_state' => 1
            ],
            // MODIFICATION : Remplacé la chaîne "INSERT INTO..." par un tableau de données
            [
                'name' => 'Equipment report 2',
                'title' => 'Certificate of delivery of {owner}',
                'font' => 'Roboto',
                'fontsize' => '9',
                'content' => 'User: \n I have read the terms of use of IT equipment in the Example Company.',
                'footer' => 'Example Company \n Example Street 21 \n 01-234 Example City',
                'city' => 'Example city',
                'serial_mode' => 1,
                'orientation' => 'Portrait',
                'breakword' => 1,
                'email_mode' => 2,
                'author_name' => 'Test Division',
                'author_state' => 1
            ]
        ]
    );

    // Email config table
    $createTable(
        'glpi_plugin_protocolsmanager_emailconfig',
        "CREATE TABLE glpi_plugin_protocolsmanager_emailconfig (
            id INT(11) NOT NULL AUTO_INCREMENT,
            tname VARCHAR(255),
            send_user INT(2),
            email_content TEXT,
            email_subject VARCHAR(255),
            email_footer VARCHAR(255),
            recipients VARCHAR(255),
            PRIMARY KEY (id)
        ) DEFAULT CHARSET=utf8mb4 COLLATE=utf8_unicode_ci",
        [
            // MODIFICATION : Remplacé la chaîne "INSERT INTO..." par un tableau de données
            [
                'tname' => 'Email default',
                'send_user' => 2,
                'email_content' => 'Testmail',
                'email_subject' => 'Testmail',
                'recipients' => 'Testmail'
            ]
        ]
    );

    // Protocols table
    $createTable(
        'glpi_plugin_protocolsmanager_protocols',
        "CREATE TABLE glpi_plugin_protocolsmanager_protocols (
            id INT(11) NOT NULL AUTO_INCREMENT,
            name VARCHAR(255),
            user_id INT(11),
            gen_date DATETIME,
            author VARCHAR(255),
            document_id INT(11),
            document_type VARCHAR(255),
            PRIMARY KEY (id)
        ) DEFAULT CHARSET=utf8mb4 COLLATE=utf8_unicode_ci"
        // Pas d'inserts ici, c'est correct
    );

    // Update config table fields if upgrading from older versions
    // Ces requêtes ALTER TABLE sont correctes, elles n'ont pas besoin de changer.
    $fieldsToAdd = [
        'author_name' => "ALTER TABLE glpi_plugin_protocolsmanager_config ADD author_name VARCHAR(255) AFTER email_template",
        'author_state' => "ALTER TABLE glpi_plugin_protocolsmanager_config ADD author_state INT(2) AFTER author_name",
        'title'        => "ALTER TABLE glpi_plugin_protocolsmanager_config ADD title VARCHAR(255) AFTER name",
        'logo_width'   => "ALTER TABLE glpi_plugin_protocolsmanager_config ADD logo_width INT(11) DEFAULT NULL AFTER logo",
        'logo_height'  => "ALTER TABLE glpi_plugin_protocolsmanager_config ADD logo_height INT(11) DEFAULT NULL AFTER logo_width"
    ];
    foreach ($fieldsToAdd as $field => $sql) {
        if (!$DB->fieldExists('glpi_plugin_protocolsmanager_config', $field)) {
            if (!$DB->query($sql)) {
                Toolbox::logInFile('php-errors', "Error adding field $field: " . $DB->error() . "\n");
            }
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

    // C'est correct, DROP TABLE est une requête DDL.
    foreach ($tables as $table) {
        $DB->query("DROP TABLE IF EXISTS `$table`");
    }

    return true;
}