<?php

/**
 * Protocols Manager - Profile Rights Management
 *
 * Manages plugin rights for each GLPI profile.
 *
 * @package   glpi\protocolsmanager
 */
class PluginProtocolsmanagerProfile extends CommonDBTM
{
    /** @var array<string,string> Profile rights handled by this plugin */
    private static $rightFields = [
        'plugin_conf' => 'Plugin configuration',
        'tab_access'  => 'Protocols manager tab access'
    ];

    /**
     * Add the "Protocols manager" tab to GLPI profiles
     */
    public function getTabNameForItem(CommonGLPI $item, $withtemplate = 0): string
    {
        return self::createTabEntry(__('Protocols manager', 'protocolsmanager'));
    }

    /**
     * Display the content of the profile rights tab
     */
    public static function displayTabContentForItem(CommonGLPI $item, $tabnum = 1, $withtemplate = 0): bool
    {
        self::showRightsForm($item->getID());
        return true;
    }

    /**
     * Render the rights form for a given profile
     */
    private static function showRightsForm(int $profile_id): void
    {
        global $CFG_GLPI, $DB;

        // Default rights (none checked)
        $rights    = array_fill_keys(array_keys(self::$rightFields), '');
        $edit_flag = 1; // insert by default

        // Load existing rights if any
        // Mise à jour de la syntaxe de $DB->request
        $req = $DB->request([
            'FROM' => 'glpi_plugin_protocolsmanager_profiles',
            'WHERE' => ['profile_id' => $profile_id]
        ]);
        if ($row = $req->current()) {
            foreach (self::$rightFields as $field => $_) {
                $rights[$field] = $row[$field] ?? '';
            }
            $edit_flag = 0; // update mode
        }

        // Note : $CFG_GLPI['root_doc'] est correct ici, mais le formulaire pointe vers un script
        // qui devrait être dans /plugins/protocolsmanager/front/profile.form.php
        // La documentation indique que les URL /plugins/... sont gérées.
        echo "<form name='profiles' action='{$CFG_GLPI['root_doc']}/plugins/protocolsmanager/front/profile.form.php' method='post'>";
        echo "<div class='center'>";
        echo "<table class='tab_cadre_fixehov'>";
        echo "<tr class='tab_bg_5'><th colspan='2'>" . __('Protocols manager', 'protocolsmanager') . "</th></tr>";

        foreach (self::$rightFields as $field => $label) {
            echo "<tr class='tab_bg_2'><td width='30%'>" . __($label, 'protocolsmanager') . "</td><td>";
            Html::showCheckbox([
                'name'    => $field,
                'checked' => ($rights[$field] === 'w'),
                'value'   => 'w'
            ]);
            echo "</td></tr>";
        }

        echo "<tr class='tab_bg_5'><th colspan='2'>";
        echo "<input type='submit' class='submit' name='update' value='" . __('Save', 'protocolsmanager') . "'>";
        echo Html::hidden('profile_id', ['value' => $profile_id]);
        echo Html::hidden('edit_flag', ['value' => $edit_flag]);
        echo "</th></tr>";

        echo "</table>";
        Html::closeForm();
        echo "</div>";
    }

    /**
     * Update rights for a profile
     */
    public static function updateRights(): void
    {
        global $DB;

        $data = [
            'profile_id'   => (int)$_POST['profile_id'],
            'plugin_conf'  => $_POST['plugin_conf'] ?? '',
            'tab_access'   => $_POST['tab_access'] ?? ''
        ];
        
        // C'est correct. $DB->insert et $DB->update gèrent la protection SQL.
        // Pas besoin de addslashes() sur les données de $_POST.
        if ((int)$_POST['edit_flag'] === 1) {
            $DB->insert('glpi_plugin_protocolsmanager_profiles', $data);
        } else {
            $DB->update('glpi_plugin_protocolsmanager_profiles', $data, [
                'profile_id' => (int)$_POST['profile_id']
            ]);
        }
    }

	public static function currentUserHasRight(string $right): bool
	{
		global $DB;
	
		$profile_id = $_SESSION['glpiactiveprofile']['id'] ?? 0;
		if ($profile_id <= 0) {
			return false;
		}
	
        // Mise à jour de la syntaxe de $DB->request
		$res = $DB->request(
			['FROM' => 'glpi_plugin_protocolsmanager_profiles', 'WHERE' => ['profile_id' => $profile_id]]
		);
	
		if ($row = $res->current()) {
			return !empty($row[$right]) && $row[$right] === 'w';
		}
	
		return false;
	}
	
}
?>