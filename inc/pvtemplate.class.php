<?php

/**
 * Persistent settings for the two protocol templates and shared PDF branding.
 *
 * Inventory report templates remain in glpi_plugin_protocolsmanager_config;
 * this class deliberately keeps those records separate from protocol settings.
 */
class PluginProtocolsmanagerPvtemplate
{
    public const TABLE = 'glpi_plugin_protocolsmanager_settings';
    private const COMMISSION_KEY = 'pv_commission';
    private const DISPOSAL_KEY = 'pv_disposal';
    private const BRANDING_KEY = 'pdf_branding';

    public static function defaults(string $type): array
    {
        if ($type === 'commission') {
            return [
                'title' => 'Procès-verbal de mise en service',
                'form' => [
                    'effective_date' => ['label' => 'Date effective de mise en service', 'enabled' => true],
                    'planned_use' => ['label' => 'Description de l’utilisation prévue', 'enabled' => true],
                    'city' => ['label' => 'Lieu d’établissement du document', 'enabled' => true],
                    'observations' => ['label' => 'Observations éventuelles', 'enabled' => true],
                    'states' => [
                        ['key' => 'installation', 'label' => 'Installée', 'enabled' => true, 'checked' => true],
                        ['key' => 'tested', 'label' => 'Testée', 'enabled' => true, 'checked' => true],
                        ['key' => 'accepted', 'label' => 'Réceptionnée conforme', 'enabled' => true, 'checked' => true],
                        ['key' => 'operational', 'label' => 'Mise en exploitation', 'enabled' => true, 'checked' => true],
                    ],
                    'validators' => [
                        ['key' => 'user_manager', 'label' => 'Responsable utilisateur', 'enabled' => true],
                        ['key' => 'technical_manager', 'label' => 'Responsable technique (si applicable)', 'enabled' => true],
                        ['key' => 'accounting', 'label' => 'Comptabilité', 'enabled' => true],
                        ['key' => 'management', 'label' => 'Direction / Responsable hiérarchique', 'enabled' => true],
                    ],
                    'cities' => [
                        ['key' => 'Eichhoffen', 'label' => 'Eichhoffen', 'enabled' => true],
                        ['key' => 'Barr', 'label' => 'Barr', 'enabled' => true],
                    ],
                ],
                'chapters' => [
                    ['key' => 'identification', 'title' => 'Identification de l’immobilisation', 'enabled' => true,
                        'content' => ''],
                    ['key' => 'commissioning', 'title' => 'Mise en service', 'enabled' => true,
                        'content' => ''],
                    ['key' => 'observations', 'title' => 'Observations éventuelles', 'enabled' => true,
                        'content' => ''],
                    ['key' => 'validation', 'title' => 'Validations', 'enabled' => true,
                        'content' => ''],
                    ['key' => 'model_photo', 'title' => 'Annexes', 'enabled' => true,
                        'content' => '{photo}'],
                ],
                'custom_chapters' => [],
            ];
        }

        if ($type === 'disposal') {
            return [
                'title' => 'Procès-verbal de mise au rebut',
                'form' => [
                    'disposal_date' => ['label' => 'Date de mise au rebut', 'enabled' => true],
                    'reason' => ['label' => 'Motif de la mise au rebut', 'enabled' => true],
                    'method' => ['label' => 'Mode de mise au rebut', 'enabled' => true],
                    'methods' => [
                        ['key' => 'destruction', 'label' => 'Destruction', 'enabled' => true],
                        ['key' => 'don', 'label' => 'Don', 'enabled' => true],
                        ['key' => 'vente_symbolique', 'label' => 'Vente symbolique', 'enabled' => true],
                        ['key' => 'recyclage_deee', 'label' => 'Recyclage DEEE après dépose du disque dur', 'enabled' => true],
                        ['key' => 'autre', 'label' => 'Autre', 'enabled' => true],
                    ],
                    'checks' => [],
                    'validators' => [
                        ['key' => 'applicants', 'label' => 'Demandeurs', 'enabled' => true],
                        ['key' => 'manager', 'label' => 'Manageur', 'enabled' => true],
                        ['key' => 'daf', 'label' => 'DAF', 'enabled' => true],
                        ['key' => 'direction', 'label' => 'Direction', 'enabled' => true],
                        ['key' => 'accounting', 'label' => 'Comptabilité', 'enabled' => true],
                    ],
                ],
                'chapters' => [
                    ['key' => 'general', 'title' => 'Informations générales', 'enabled' => true,
                        'content' => ''],
                    ['key' => 'asset', 'title' => 'Détails de l’immobilisation', 'enabled' => true,
                        'content' => ''],
                    ['key' => 'reason', 'title' => 'Motif de la mise au rebut', 'enabled' => true,
                        'content' => ''],
                    ['key' => 'method', 'title' => 'Mode de mise au rebut', 'enabled' => true,
                        'content' => ''],
                    ['key' => 'checks', 'title' => 'Liste de contrôle', 'enabled' => true,
                        'content' => ''],
                    ['key' => 'model_photo', 'title' => 'Annexes', 'enabled' => true,
                        'content' => '{photo}'],
                    ['key' => 'signature', 'title' => 'Signatures', 'enabled' => true,
                        'content' => ''],
                ],
                'custom_chapters' => [],
            ];
        }

        return [];
    }

    public static function settingKey(string $type): string
    {
        return match ($type) {
            'commission' => self::COMMISSION_KEY,
            'disposal' => self::DISPOSAL_KEY,
            'branding' => self::BRANDING_KEY,
            default => '',
        };
    }

    public static function load(string $type): array
    {
        global $DB;
        $key = self::settingKey($type);
        if ($key === '' || !$DB->tableExists(self::TABLE)) {
            return $type === 'branding' ? ['logo' => '', 'accent_color' => '#8A6428'] : self::defaults($type);
        }
        $result = $DB->request([
            'SELECT' => ['setting_value'],
            'FROM' => self::TABLE,
            'WHERE' => ['setting_key' => $key],
            'LIMIT' => 1,
        ]);
        $row = $result->current();
        if (!$row) {
            return $type === 'branding' ? ['logo' => '', 'accent_color' => '#8A6428'] : self::defaults($type);
        }
        $value = json_decode((string)$row['setting_value'], true);
        if (!is_array($value)) {
            return $type === 'branding' ? ['logo' => '', 'accent_color' => '#8A6428'] : self::defaults($type);
        }
        return $value;
    }

    public static function save(string $type, array $value): bool
    {
        global $DB;
        $key = self::settingKey($type);
        if ($key === '' || !$DB->tableExists(self::TABLE)) {
            return false;
        }
        $json = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if (!is_string($json)) {
            return false;
        }
        $result = $DB->request([
            'SELECT' => ['id'],
            'FROM' => self::TABLE,
            'WHERE' => ['setting_key' => $key],
            'LIMIT' => 1,
        ]);
        $row = $result->current();
        if ($row) {
            return (bool)$DB->update(self::TABLE, ['setting_value' => $json], ['id' => (int)$row['id']]);
        }
        return (bool)$DB->insert(self::TABLE, ['setting_key' => $key, 'setting_value' => $json]);
    }

    /** Seed missing singleton settings and inherit shared branding from the first legacy inventory template. */
    public static function seedDefaults(): void
    {
        global $DB;
        if (!$DB->tableExists(self::TABLE)) {
            return;
        }
        foreach (['commission', 'disposal'] as $type) {
            $key = self::settingKey($type);
            if (!self::hasSetting($key)) {
                self::save($type, self::defaults($type));
            }
        }
        if (!self::hasSetting(self::BRANDING_KEY)) {
            $branding = ['logo' => '', 'accent_color' => '#8A6428'];
            if ($DB->tableExists('glpi_plugin_protocolsmanager_config')) {
                $color_found = false;
                $legacy_fields = [];
                foreach (['logo', 'theme_color'] as $field) {
                    if ($DB->fieldExists('glpi_plugin_protocolsmanager_config', $field)) {
                        $legacy_fields[] = $field;
                    }
                }
                if ($legacy_fields !== []) {
                    $templates = $DB->request([
                        'SELECT' => $legacy_fields,
                        'FROM' => 'glpi_plugin_protocolsmanager_config',
                        'ORDER' => ['id ASC'],
                    ]);
                } else {
                    $templates = [];
                }
                foreach ($templates as $template) {
                    if ($branding['logo'] === '' && !empty($template['logo'])) {
                        $candidate = basename((string)$template['logo']);
                        if (defined('GLPI_PICTURE_DIR')) {
                            $candidate_path = GLPI_PICTURE_DIR . '/' . $candidate;
                            if (is_file($candidate_path) && is_readable($candidate_path) && (int)filesize($candidate_path) <= 2097152) {
                                $image = @getimagesize($candidate_path);
                                $mime = is_array($image) ? ($image['mime'] ?? '') : '';
                                $width = is_array($image) ? (int)($image[0] ?? 0) : 0;
                                $height = is_array($image) ? (int)($image[1] ?? 0) : 0;
                                if (in_array($mime, ['image/png', 'image/jpeg'], true)
                                    && $width > 0 && $height > 0 && $width <= 5000 && $height <= 5000
                                    && ($width * $height) <= 25000000) {
                                    $branding['logo'] = $candidate;
                                }
                            }
                        }
                    }
                    if (!$color_found && preg_match('/^#[0-9a-f]{6}$/i', (string)($template['theme_color'] ?? ''))) {
                        $branding['accent_color'] = (string)$template['theme_color'];
                        $color_found = true;
                    }
                    if ($branding['logo'] !== '' && $color_found) {
                        break;
                    }
                }
            }
            self::save('branding', $branding);
        }
    }

    private static function hasSetting(string $key): bool
    {
        global $DB;
        return (bool)$DB->request([
            'SELECT' => ['id'],
            'FROM' => self::TABLE,
            'WHERE' => ['setting_key' => $key],
            'LIMIT' => 1,
        ])->current();
    }
}
