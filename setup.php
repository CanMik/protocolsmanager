<?php

// Plugin version info
function plugin_version_protocolsmanager(): array
{
    return [
        'name'         => __('Protocols manager', 'protocolsmanager'),
        'version'      => '1.5.7.5',
        'author'       => 'Mikail',
        'license'      => 'GPLv3+',
        'homepage'     => 'https://github.com/CanMik/protocolsmanager',
        'requirements' => [
            'glpi' => [
                'min' => '11.0.0',
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
    // Updated version check for GLPI 11
    if (version_compare(GLPI_VERSION, '11.0.0', '<') || version_compare(GLPI_VERSION, '12.0.0', '>=')) {
        if (method_exists('Plugin', 'messageIncompatible')) {
            Plugin::messageIncompatible('core', '11.0.0', '12.0.0'); // Updated versions
        } else {
            echo __('This plugin requires GLPI >= 11.0.0 and < 12.0.0', 'protocolsmanager'); // Updated message
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
    $PLUGIN_HOOKS['add_css']['protocolsmanager']        = 'css/styles.css';

    // Register tabs for supported item types
    $tabTargets = [
        'User', 'Printer', 'Peripheral', 'Computer',
        'Phone', 'Line', 'Monitor'
    ];
    foreach ($tabTargets as $target) {
        Plugin::registerClass('PluginProtocolsmanagerGenerate', ['addtabon' => [$target]]);
    }

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