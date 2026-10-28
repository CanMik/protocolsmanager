<?php

class PluginProtocolsmanagerMenu extends CommonGLPI
{
    public static function getMenuName(): string
    {
        return __('Protocols Manager', 'protocolsmanager');
    }

    public static function getIcon(): string
    {
        return 'fas fa-briefcase';
    }

    public static function getMenuContent(): array
    {
        return [
            'title' => self::getMenuName(),
            'page'  => '/plugins/protocolsmanager/front/config.form.php', // Chemin mis à jour
            'icon'  => self::getIcon(),
        ];
    }
}