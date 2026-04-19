<?php

// Single source of truth for the plugin version.
// Bump this constant — everything else reads from it automatically.
define('PLUGIN_PROTOCOLSMANAGER_VERSION', '1.7.1.0');

// Plugin version info
function plugin_version_protocolsmanager(): array
{
    return [
        'name'         => __('Protocols manager', 'protocolsmanager'),
        'version'      => PLUGIN_PROTOCOLSMANAGER_VERSION,
        'author'       => 'Mikail',
        'license'      => 'GPLv3+',
        'homepage'     => 'https://github.com/CanMik/protocolsmanager',
        'requirements' => [
            'glpi' => [
                'min' => '10.0.0',
                'max' => '12.0.0'
            ],
            'php'  => [
                'min' => '8.0'
            ]
        ]
    ];
}

// Config check
function plugin_protocolsmanager_check_config(): bool
{
    return true;
}

// Prerequisites check
function plugin_protocolsmanager_check_prerequisites(): bool
{
    // Compatible with GLPI 10.0.x and 11.x
    if (version_compare(GLPI_VERSION, '10.0.0', '<') || version_compare(GLPI_VERSION, '12.0.0', '>=')) {
        if (method_exists('Plugin', 'messageIncompatible')) {
            Plugin::messageIncompatible('core', '10.0.0', '12.0.0');
        } else {
            echo __('This plugin requires GLPI >= 10.0.0 and < 12.0.0', 'protocolsmanager');
        }
        return false;
    }
    return true;
}

// Init plugin hooks
function plugin_init_protocolsmanager(): void
{
    global $PLUGIN_HOOKS, $DB;

    $PLUGIN_HOOKS['csrf_compliant']['protocolsmanager'] = true;

    // The Protocols Manager tab is only shown on User items.
    // Assets (Computer, Phone, etc.) are loaded dynamically from the user's assigned inventory.
    Plugin::registerClass('PluginProtocolsmanagerGenerate', ['addtabon' => ['User']]);

    Plugin::registerClass('PluginProtocolsmanagerProfile', ['addtabon' => ['Profile']]);
    Plugin::registerClass('PluginProtocolsmanagerConfig',  ['addtabon' => ['Config']]);

    // --- SÉCURITÉ : ne pas appeler la table avant qu’elle n’existe ---
    if ($DB->tableExists('glpi_plugin_protocolsmanager_profiles')) {
        if (class_exists('PluginProtocolsmanagerProfile')
            && method_exists('PluginProtocolsmanagerProfile', 'currentUserHasRight')
            && PluginProtocolsmanagerProfile::currentUserHasRight('plugin_conf')) {

            $PLUGIN_HOOKS['menu_toadd']['protocolsmanager']  = ['config' => 'PluginProtocolsmanagerMenu'];
            $PLUGIN_HOOKS['config_page']['protocolsmanager'] = 'front/config.form.php';
        }
    }
}